<?php

namespace aieuo\mineflow\flowItem\action;

use aieuo\mineflow\exception\InvalidFlowValueException;
use aieuo\mineflow\flowItem\FlowItem;
use aieuo\mineflow\formAPI\CustomForm;
use aieuo\mineflow\formAPI\element\CancelToggle;
use aieuo\mineflow\formAPI\element\mineflow\ExampleInput;
use aieuo\mineflow\formAPI\element\Label;
use aieuo\mineflow\formAPI\element\Toggle;
use aieuo\mineflow\formAPI\Form;
use aieuo\mineflow\Main;
use aieuo\mineflow\recipe\Recipe;
use aieuo\mineflow\utils\Category;
use aieuo\mineflow\utils\Language;
use aieuo\mineflow\variable\DummyVariable;
use aieuo\mineflow\variable\ListVariable;
use aieuo\mineflow\variable\MapVariable;

class DeleteListVariableContent extends FlowItem {

    protected $id = self::DELETE_LIST_VARIABLE_CONTENT;

    protected $name = "action.removeContent.name";
    protected $detail = "action.removeContent.detail";
    protected $detailDefaultReplace = ["name", "scope", "key"];

    protected $category = Category::VARIABLE;

    /** @var string */
    private $variableName;
    /** @var string */
    private $variableKey;
    /** @var bool */
    private $isLocal;

    public function __construct(string $name = "", string $key = "", bool $local = true) {
        $this->variableName = $name;
        $this->variableKey = $key;
        $this->isLocal = $local;
    }

    public function setVariableName(string $variableName): void {
        $this->variableName = $variableName;
    }

    public function getVariableName(): string {
        return $this->variableName;
    }

    public function setKey(string $variableKey): void {
        $this->variableKey = $variableKey;
    }

    public function getKey(): string {
        return $this->variableKey;
    }

    public function isDataValid(): bool {
        return $this->variableName !== "" and $this->variableKey !== "";
    }

    public function getDetail(): string {
        if (!$this->isDataValid()) return $this->getName();
        return Language::get($this->detail, [$this->getVariableName(), $this->isLocal ? "local" : "global", $this->getKey()]);
    }

    public function execute(Recipe $origin): \Generator {
        $this->throwIfCannotExecute();

        $helper = Main::getVariableHelper();
        $name = $origin->replaceVariables($this->getVariableName());
        $key = $origin->replaceVariables($this->getKey());

        $variable = ($this->isLocal ? $origin->getVariable($name) : $helper->get($name)) ?? new MapVariable([], $name);
        if (!($variable instanceof ListVariable)) {
            throw new InvalidFlowValueException(
                $this->getName(), Language::get("action.error", [$this->getName(), ["action.addListVariable.error.existsOtherType", [$name, (string)$variable]]])
            );
        }

        $values = $variable->getValue();
        unset($values[$key]);
        $variable->setValue($values);

        if ($this->isLocal) $origin->addVariable($variable); else $helper->add($variable);
        yield true;
    }

    public function getEditForm(array $variables = []): Form {
        return (new CustomForm($this->getName()))
            ->setContents([
                new Label($this->getDescription()),
                new ExampleInput("@action.variable.form.name", "aieuo", $this->getVariableName(), true),
                new ExampleInput("@action.variable.form.key", "auieo", $this->getKey(), true),
                new Toggle("@action.variable.form.global", !$this->isLocal),
                new CancelToggle()
            ]);
    }

    public function parseFromFormData(array $data): array {
        return ["contents" => [$data[1], $data[2], !$data[3]], "cancel" => $data[4]];
    }

    public function loadSaveData(array $content): FlowItem {
        $this->setVariableName($content[0]);
        $this->setKey($content[1]);
        $this->isLocal = $content[2];
        return $this;
    }

    public function serializeContents(): array {
        return [$this->getVariableName(), $this->getKey(), $this->isLocal];
    }

    public function getAddingVariables(): array {
        return [new DummyVariable($this->getVariableName(), DummyVariable::LIST)];
    }
}