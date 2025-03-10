<?php

namespace aieuo\mineflow\flowItem;

use aieuo\mineflow\exception\FlowItemLoadException;
use aieuo\mineflow\exception\InvalidFlowValueException;
use aieuo\mineflow\formAPI\CustomForm;
use aieuo\mineflow\formAPI\element\CancelToggle;
use aieuo\mineflow\formAPI\element\Label;
use aieuo\mineflow\formAPI\Form;
use aieuo\mineflow\recipe\Recipe;
use aieuo\mineflow\utils\Language;
use aieuo\mineflow\variable\DummyVariable;
use pocketmine\Player;

abstract class FlowItem implements \JsonSerializable, FlowItemIds {

    /** @var string */
    protected $id;
    /** @var string */
    protected $type;

    /** @var string */
    protected $name;
    /** @var string */
    protected $detail;
    /** @var string[] */
    protected $detailDefaultReplace = [];

    /** @var string */
    protected $category;

    /** @var string */
    private $customName = "";

    public const RETURN_NONE = "none";
    public const RETURN_VARIABLE_NAME = "variableName";
    public const RETURN_VARIABLE_VALUE = "variableValue";

    /** @var string */
    protected $returnValueType = self::RETURN_NONE;

    public const PERMISSION_LEVEL_0 = 0;
    public const PERMISSION_LEVEL_1 = 1;
    public const PERMISSION_LEVEL_2 = 2;
    /** @var int */
    protected $permission = self::PERMISSION_LEVEL_0;

    /* @var FlowItemContainer */
    private $parent;

    public function getId(): string {
        return $this->id;
    }

    public function getName(): string {
        return Language::get($this->name);
    }

    public function getDescription(): string {
        $replaces = array_map(function ($replace) { return "§7<".$replace.">§f"; }, $this->detailDefaultReplace);
        return Language::get($this->detail, $replaces);
    }

    public function getDetail(): string {
        return Language::get($this->detail);
    }

    public function setCustomName(?string $name = null): void {
        $this->customName = $name ?? "";
    }

    public function getCustomName(): string {
        return $this->customName;
    }

    public function getCategory(): string {
        return $this->category;
    }

    public function getPermission(): int {
        return $this->permission;
    }

    public function getReturnValueType(): string {
        return $this->returnValueType;
    }

    public function jsonSerialize(): array {
        $data = [
            "id" => $this->getId(),
            "contents" => $this->serializeContents(),
        ];
        if (!empty($this->getCustomName())) {
            $data["customName"] = $this->getCustomName();
        }
        return $data;
    }

    public function throwIfCannotExecute(): void {
        if (!$this->isDataValid()) {
            $message = Language::get("invalid.contents");
            throw new InvalidFlowValueException($this->getName(), $message);
        }
    }

    public function throwIfInvalidNumber(string $numberStr, ?float $min = null, ?float $max = null, array $exclude = []): void {
        if (!is_numeric($numberStr)) {
            throw new InvalidFlowValueException($this->getName(), Language::get("action.error.notNumber"));
        }
        $number = (float)$numberStr;
        if ($min !== null and $number < $min) {
            throw new InvalidFlowValueException($this->getName(), Language::get("action.error.lessValue", [$min]));
        }
        if ($max !== null and $number > $max) {
            throw new InvalidFlowValueException($this->getName(), Language::get("action.error.overValue", [$max]));
        }
        if (!empty($exclude) and in_array($number, $exclude, true)) {
            throw new InvalidFlowValueException($this->getName(), Language::get("action.error.excludedNumber", [implode(",", $exclude)]));
        }
    }

    public function getEditForm(array $variables = []): Form {
        return (new CustomForm($this->getName()))
            ->setContents([
                new Label($this->getDescription()),
                new CancelToggle()
            ]);
    }

    public function parseFromFormData(array $data): array {
        return ["contents" => [], "cancel" => $data[1]];
    }

    /**
     * @param array $content
     * @return self
     * @throws FlowItemLoadException|\ErrorException
     */
    public static function loadSaveDataStatic(array $content): self {
        switch ($content["id"]) {
            case "addScore":
                $content["id"] = self::REMOVE_SCOREBOARD_SCORE;
                break;
        }
        $action = FlowItemFactory::get($content["id"]);
        if ($action === null) {
            throw new FlowItemLoadException(Language::get("action.not.found", [$content["id"]]));
        }

        $action->setCustomName($content["customName"] ?? "");
        return $action->loadSaveData($content["contents"]);
    }

    public function hasCustomMenu(): bool {
        return false;
    }

    public function sendCustomMenu(Player $player, array $messages = []): void {
    }

    public function allowDirectCall(): bool {
        return true;
    }

    public function setParent(FlowItemContainer $container): self {
        $this->parent = $container;
        return $this;
    }

    public function getParent(): FlowItemContainer {
        return $this->parent;
    }

    /**
     * @return DummyVariable[]
     */
    public function getAddingVariables(): array {
        return [];
    }

    /**
     * @return boolean
     */
    abstract public function isDataValid(): bool;

    /**
     * @return array
     */
    abstract public function serializeContents(): array;

    /**
     * @param array $content
     * @return FlowItem
     * @throws FlowItemLoadException|\ErrorException
     */
    abstract public function loadSaveData(array $content): FlowItem;

    /**
     * @param Recipe $origin
     * @return bool|\Generator
     */
    abstract public function execute(Recipe $origin);
}