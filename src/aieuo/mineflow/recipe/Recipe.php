<?php

namespace aieuo\mineflow\recipe;

use aieuo\mineflow\exception\FlowItemLoadException;
use aieuo\mineflow\exception\InvalidFlowValueException;
use aieuo\mineflow\flowItem\FlowItem;
use aieuo\mineflow\flowItem\FlowItemContainer;
use aieuo\mineflow\flowItem\FlowItemContainerTrait;
use aieuo\mineflow\Main;
use aieuo\mineflow\trigger\Trigger;
use aieuo\mineflow\trigger\TriggerHolder;
use aieuo\mineflow\trigger\Triggers;
use aieuo\mineflow\utils\Language;
use aieuo\mineflow\utils\Logger;
use aieuo\mineflow\variable\DefaultVariables;
use aieuo\mineflow\variable\DummyVariable;
use aieuo\mineflow\variable\ListVariable;
use aieuo\mineflow\variable\object\EventObjectVariable;
use aieuo\mineflow\variable\ObjectVariable;
use aieuo\mineflow\variable\Variable;
use pocketmine\entity\Entity;
use pocketmine\event\Event;
use pocketmine\Player;
use pocketmine\Server;

class Recipe implements \JsonSerializable, FlowItemContainer {
    use FlowItemContainerTrait {
        getAddingVariablesBefore as traitGetAddingVariableBefore;
    }

    public const TARGET_DEFAULT = 0;
    public const TARGET_SPECIFIED = 1;
    public const TARGET_BROADCAST = 2;
    public const TARGET_RANDOM = 3;
    public const TARGET_NONE = 4;

    /** @var string */
    private $name;
    /* @var string */
    private $author;
    /* @var string */
    private $group;

    /** @var int */
    private $targetType = self::TARGET_DEFAULT;
    /** @var array */
    private $targetOptions = [];
    /** @var Entity|null */
    private $target;

    /** @var Trigger[] */
    private $triggers = [];

    /** @var array */
    private $variables = [];

    /** @var Recipe|null */
    private $sourceRecipe;
    /* @var array */
    private $arguments = [];
    /* @var array */
    private $returnValues = [];

    /** @var null|Event */
    private $event;
    /* @var \Generator */
    private $generator;

    /** @var bool */
    private $waiting = false;
    /* @var bool */
    private $exit = false;
    /* @var bool */
    private $resuming = false;

    /** @var string */
    private $rawData = "";

    public function __construct(string $name, string $group = "", string $author = "") {
        $this->name = $name;
        $this->author = $author;
        $this->group = preg_replace("#/+#", "/", $group);
    }

    public function setName(string $name): void {
        $this->name = $name;
    }

    public function getName(): string {
        return $this->name;
    }

    public function getPathname(): string {
        return empty($this->getGroup()) ? $this->getName() : ($this->getGroup()."/".$this->getName());
    }

    public function getContainerName(): string {
        return $this->getName();
    }

    public function setGroup(string $group): void {
        $this->group = preg_replace("#/+#", "/", $group);
    }

    public function getGroup(): string {
        return $this->group;
    }

    public function setRawData(string $rawData): void {
        $this->rawData = $rawData;
    }

    public function getRawData(): string {
        return $this->rawData;
    }

    public function getDetail(): string {
        $details = [];
        foreach ($this->getTriggers() as $trigger) {
            $details[] = (string)$trigger;
        }
        $details[] = str_repeat("~", 20);
        foreach ($this->getActions() as $action) {
            $details[] = $action->getDetail();
        }
        return implode("\n§f", $details);
    }

    public function setTargetSetting(int $type, array $options = []): void {
        $this->targetType = $type;
        $this->targetOptions = array_merge($this->targetOptions, $options);
    }

    public function getTargetType(): int {
        return $this->targetType;
    }

    public function getTargetOptions(): array {
        return $this->targetOptions;
    }

    public function getTargets(?Entity $player = null): array {
        $targets = [];
        switch ($this->targetType) {
            case self::TARGET_DEFAULT:
                $targets = [$player];
                break;
            case self::TARGET_SPECIFIED:
                $server = Server::getInstance();
                foreach ($this->targetOptions["specified"] as $targetName) {
                    $target = $server->getPlayer($targetName);
                    if (!($target instanceof Player)) continue;
                    $targets[] = $target;
                }
                break;
            case self::TARGET_BROADCAST:
                $targets = Server::getInstance()->getOnlinePlayers();
                break;
            case self::TARGET_RANDOM:
                $onlines = Server::getInstance()->getOnlinePlayers();
                $count = (int)($this->targetOptions["random"] ?? 1);
                if ($count > count($onlines)) $count = count($onlines);
                $keys = $count <= 1 ? [array_rand($onlines)] : array_rand($onlines, $count);
                foreach ($keys as $key) {
                    $targets[] = $onlines[$key];
                }
                break;
            case self::TARGET_NONE:
                $targets = [null];
                break;
        }
        return $targets;
    }

    /**
     * @return Trigger[]
     */
    public function getTriggers(): array {
        return $this->triggers;
    }

    public function addTrigger(Trigger $trigger): void {
        TriggerHolder::getInstance()->addRecipe($trigger, $this);
        $this->triggers[] = $trigger;
    }

    public function setTriggersFromArray(array $triggers): void {
        $eventManager = Main::getEventManager();

        $this->removeTriggerAll();
        foreach ($triggers as $triggerData) {
            if ($triggerData["type"] === Triggers::EVENT) {
                $fullName = $eventManager->getFullName($triggerData["key"]);
                if ($fullName !== null) $triggerData["key"] = $fullName;
            }

            $trigger = Triggers::getTrigger($triggerData["type"], $triggerData["key"], $triggerData["subKey"] ?? "");
            if ($trigger === null) throw new \UnexpectedValueException(Language::get("trigger.notFound", [$triggerData["type"]]));
            $this->addTrigger($trigger);
        }
    }

    public function existsTrigger(Trigger $trigger): bool {
        return in_array($trigger, $this->getTriggers());
    }

    public function removeTrigger(Trigger $trigger): void {
        TriggerHolder::getInstance()->removeRecipe($trigger, $this);
        $index = array_search($trigger, $this->triggers, true);
        unset($this->triggers[$index]);
        $this->triggers = array_values($this->triggers);
    }

    public function removeTriggerAll(): void {
        foreach ($this->getTriggers() as $trigger) {
            $this->removeTrigger($trigger);
        }
    }

    public function setEvent(?Event $event): self {
        $this->event = $event;
        return $this;
    }

    public function getEvent(): ?Event {
        return $this->event;
    }

    public function executeAllTargets(?Entity $player = null, array $variables = [], ?Event $event = null, array $args = []): ?bool {
        $targets = $this->getTargets($player);
        $variables = array_merge($variables, DefaultVariables::getServerVariables());
        if ($event instanceof Event) $variables = array_merge($variables, ["event" => new EventObjectVariable($event, "event")]);
        foreach ($targets as $target) {
            $recipe = clone $this;
            $recipe->setTarget($target)->setEvent($event)
                ->addVariables($target instanceof Entity ? array_merge($variables, DefaultVariables::getEntityVariables($target)) : $variables);
            $recipe->execute($args);
        }
        return true;
    }

    public function execute(array $args = [], bool $first = true): bool {
        $this->applyArguments($args);

        $this->generator = $this->generator ?? $this->executeAll($this, FlowItemContainer::ACTION);
        try {
            if (!$first) $this->generator->next();

            while ($this->generator->valid()) {
                if ($this->exit) {
                    $this->resuming = false;
                    $this->waiting = false;
                    return false;
                }

                $result = $this->generator->current();
                if (!$result and !$this->resuming) {
                    $this->waiting = true;
                    return false;
                }

                if (!$result) {
                    $this->resuming = false;
                }

                $this->generator->next();
            }
        } catch (InvalidFlowValueException $e) {
            if (!empty($e->getMessage())) Logger::warning($e->getMessage(), $this->getTarget());
            Logger::warning(Language::get("recipe.execute.failed", [$this->getPathname(), $e->getName()]), $this->getTarget());
            return false;
        }

        if ($this->sourceRecipe instanceof Recipe) {
            foreach ($this->getReturnValues() as $value) {
                $variable = $this->getVariable($value);
                if ($variable instanceof Variable) $this->sourceRecipe->addVariable($variable);
            }
            $this->sourceRecipe->resume();
        }
        return true;
    }

    public function resume(): void {
        $this->resuming = true;
        if (!$this->waiting) return;
        $this->resuming = false;
        $this->waiting = false;
        $this->execute([], false);
    }

    public function exit(): void {
        $this->exit = true;
    }

    public function applyArguments(array $args): void {
        $helper = Main::getVariableHelper();
        foreach ($this->getArguments() as $i => $argument) {
            if (isset($args[$i])) {
                $arg = $args[$i];
                if ($arg instanceof Variable) {
                    $arg->setName($argument);
                } else {
                    $type = $helper->getType($arg);
                    $arg = Variable::create($helper->currentType($arg), $argument, $type);
                }
                $this->addVariable($arg);
            }
        }
    }

    public function getTarget(): ?Entity {
        return $this->target;
    }

    public function setTarget(?Entity $target): self {
        $this->target = $target;
        return $this;
    }

    public function setArguments(array $arguments): void {
        $this->arguments = $arguments;
    }

    public function getArguments(): array {
        return $this->arguments;
    }

    public function setReturnValues(array $returnValues): void {
        $this->returnValues = $returnValues;
    }

    public function getReturnValues(): array {
        return $this->returnValues;
    }

    public function addVariable(Variable $variable): void {
        $this->variables[$variable->getName()] = $variable;
    }

    public function addVariables(array $variables): void {
        $this->variables = array_merge($this->variables, $variables);
    }

    public function getVariable(string $name): ?Variable {
        $names = explode(".", $name);
        $name = array_shift($names);
        if (!isset($this->variables[$name])) return null;

        $variable = $this->variables[$name];
        foreach ($names as $name1) {
            if (!($variable instanceof ListVariable) and !($variable instanceof ObjectVariable)) return null;
            $variable = $variable->getValueFromIndex($name1);
        }
        return $variable;
    }

    public function getVariables(): array {
        return $this->variables;
    }

    public function removeVariable(string $name): void {
        unset($this->variables[$name]);
    }

    public function replaceVariables(string $text): string {
        return Main::getVariableHelper()->replaceVariablesAndFunctions($text, $this);
    }

    public function getAddingVariablesBefore(FlowItem $flowItem, array $containers, string $type): array {
        $variables = [new DummyVariable("target", DummyVariable::PLAYER)];
        $add = [];
        foreach ($this->getTriggers() as $trigger) {
            $add[] = $trigger->getVariablesDummy();
        }
        $variables = array_merge(array_merge($variables, ...$add), $this->traitGetAddingVariableBefore($flowItem, $containers, $type));
        return $variables;
    }

    public function setSourceRecipe(?Recipe $recipe): self {
        $this->sourceRecipe = $recipe;
        return $this;
    }

    /**
     * @param array $contents
     * @return self
     * @throws FlowItemLoadException|\ErrorException
     */
    public function loadSaveData(array $contents): self {
        foreach ($contents as $i => $content) {
            try {
                $action = FlowItem::loadSaveDataStatic($content);
            } catch (\ErrorException $e) {
                if (strpos($e->getMessage(), "Undefined offset:") !== 0) throw $e;
                throw new FlowItemLoadException(Language::get("recipe.load.failed.action", [$i, $content["id"] ?? "id?", ["recipe.json.key.missing"]]));
            }

            $this->addItem($action, FlowItemContainer::ACTION);
        }
        return $this;
    }

    public function jsonSerialize(): array {
        return [
            "name" => $this->name,
            "group" => $this->group,
            "author" => $this->author,
            "actions" => $this->getActions(),
            "triggers" => $this->triggers,
            "target" => [
                "type" => $this->targetType,
                "options" => $this->targetOptions,
            ],
            "arguments" => $this->getArguments(),
            "returnValues" => $this->getReturnValues(),
        ];
    }

    public function getFileName(string $baseDir): string {
        $group = preg_replace("#[.¥:?<>|*\"]#u", "", $this->getGroup());
        $name = preg_replace("#[.¥/:?<>|*\"]#u", "", $this->getName());
        if (!empty($group)) $baseDir .= $group."/";
        return $baseDir.$name.".json";
    }

    public function save(string $dir): void {
        $path = $this->getFileName($dir);
        if (!file_exists(dirname($path))) @mkdir(dirname($path), 0777, true);

        $json = json_encode($this, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_BIGINT_AS_STRING);
        if ($json === $this->getRawData()) return;

        if (file_put_contents($path, $json) === false) {
            Main::getInstance()->getLogger()->error(Language::get("recipe.save.failed", [$this->getPathname()]));
        }
    }

    public function __clone() {
        $actions = [];
        foreach ($this->getActions() as $k => $action) {
            $actions[$k] = clone $action;
        }
        $this->setItems($actions, FlowItemContainer::ACTION);
    }
}