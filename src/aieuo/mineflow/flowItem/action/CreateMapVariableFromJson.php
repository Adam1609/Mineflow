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
use aieuo\mineflow\variable\MapVariable;

class CreateMapVariableFromJson extends FlowItem {

    protected $id = self::CREATE_MAP_VARIABLE_FROM_JSON;

    protected $name = "action.createMapVariableFromJson.name";
    protected $detail = "action.createMapVariableFromJson.detail";
    protected $detailDefaultReplace = ["name", "scope", "json"];

    protected $category = Category::VARIABLE;

    /** @var string */
    private $variableName;
    /** @var string */
    private $json;
    /** @var bool */
    private $isLocal;

    public function __construct(string $name = "", string $json = "", bool $local = true) {
        $this->variableName = $name;
        $this->json = $json;
        $this->isLocal = $local;
    }

    public function setVariableName(string $variableName): void {
        $this->variableName = $variableName;
    }

    public function getVariableName(): string {
        return $this->variableName;
    }

    public function setJson(string $json): void {
        $this->json = $json;
    }

    public function getJson(): string {
        return $this->json;
    }

    public function isDataValid(): bool {
        return $this->variableName !== "" and $this->json !== "";
    }

    public function getDetail(): string {
        if (!$this->isDataValid()) return $this->getName();
        return Language::get($this->detail, [$this->getVariableName(), $this->isLocal ? "local" : "global", $this->getJson()]);
    }

    public function execute(Recipe $origin): \Generator {
        $this->throwIfCannotExecute();

        $helper = Main::getVariableHelper();
        $name = $origin->replaceVariables($this->getVariableName());
        $json = $this->getJson();

        $value = json_decode($json, true);
        if ($value === null) {
            throw new InvalidFlowValueException($this->getName(), json_last_error_msg());
        }

        $variable = new MapVariable(Main::getVariableHelper()->toVariableArray($value), $name);

        if ($this->isLocal) {
            $origin->addVariable($variable);
        } else {
            $helper->add($variable);
        }
        yield true;
    }

    public function getEditForm(array $variables = []): Form {
        return (new CustomForm($this->getName()))
            ->setContents([
                new Label($this->getDescription()),
                new ExampleInput("@action.variable.form.name", "aieuo", $this->getVariableName(), true),
                new ExampleInput("@action.variable.form.value", "aeiuo", $this->getJson(), true),
                new Toggle("@action.variable.form.global", !$this->isLocal),
                new CancelToggle()
            ]);
    }

    public function parseFromFormData(array $data): array {
        return ["contents" => [$data[1], $data[2], !$data[3]], "cancel" => $data[4]];
    }

    public function loadSaveData(array $content): FlowItem {
        $this->setVariableName($content[0]);
        $this->setJson($content[1]);
        $this->isLocal = $content[2];
        return $this;
    }

    public function serializeContents(): array {
        return [$this->getVariableName(), $this->getJson(), $this->isLocal];
    }

    public function getAddingVariables(): array {
        return [new DummyVariable($this->getVariableName(), DummyVariable::MAP)];
    }
}