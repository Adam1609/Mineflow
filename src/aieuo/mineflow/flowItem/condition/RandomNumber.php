<?php

namespace aieuo\mineflow\flowItem\condition;

use aieuo\mineflow\exception\InvalidFlowValueException;
use aieuo\mineflow\flowItem\FlowItem;
use aieuo\mineflow\formAPI\CustomForm;
use aieuo\mineflow\formAPI\element\CancelToggle;
use aieuo\mineflow\formAPI\element\mineflow\ExampleNumberInput;
use aieuo\mineflow\formAPI\element\Label;
use aieuo\mineflow\formAPI\Form;
use aieuo\mineflow\recipe\Recipe;
use aieuo\mineflow\utils\Category;
use aieuo\mineflow\utils\Language;

class RandomNumber extends FlowItem implements Condition {

    protected $id = self::RANDOM_NUMBER;

    protected $name = "condition.randomNumber.name";
    protected $detail = "condition.randomNumber.detail";
    protected $detailDefaultReplace = ["min", "max", "value"];

    protected $category = Category::MATH;

    /** @var string */
    private $min;
    /** @var string */
    private $max;
    /** @var string */
    private $value;

    public function __construct(string $min = "", string $max = "", string $value = "") {
        $this->min = $min;
        $this->max = $max;
        $this->value = $value;
    }

    public function setMin(string $min): void {
        $this->min = $min;
    }

    public function getMin(): string {
        return $this->min;
    }

    public function setMax(string $max): void {
        $this->max = $max;
    }

    public function getMax(): string {
        return $this->max;
    }

    public function setValue(string $value): void {
        $this->value = $value;
    }

    public function getValue(): string {
        return $this->value;
    }

    public function isDataValid(): bool {
        return $this->min !== "" and $this->max !== "" and $this->value !== "";
    }

    public function getDetail(): string {
        if (!$this->isDataValid()) return $this->getName();
        return Language::get($this->detail, [$this->getMin(), $this->getMax(), $this->getValue()]);
    }

    public function execute(Recipe $origin): \Generator {
        $this->throwIfCannotExecute();

        $min = $origin->replaceVariables($this->getMin());
        $max = $origin->replaceVariables($this->getMax());
        $value = $origin->replaceVariables($this->getValue());

        if (!is_numeric($min) or !is_numeric($max) or !is_numeric($value)) {
            throw new InvalidFlowValueException($this->getName(), Language::get("action.error.notNumber"));
        }

        yield true;
        return mt_rand(min((int)$min, (int)$max), max((int)$min, (int)$max)) === (int)$value;
    }

    public function getEditForm(array $variables = []): Form {
        return (new CustomForm($this->getName()))
            ->setContents([
                new Label($this->getDescription()),
                new ExampleNumberInput("@condition.randomNumber.form.min", "0", $this->getMin(), true),
                new ExampleNumberInput("@condition.randomNumber.form.max", "10", $this->getMax(), true),
                new ExampleNumberInput("@condition.randomNumber.form.value", "0", $this->getValue(), true),
                new CancelToggle()
            ]);
    }

    public function parseFromFormData(array $data): array {
        return ["contents" => [$data[1], $data[2], $data[3]], "cancel" => $data[4]];
    }

    public function loadSaveData(array $content): FlowItem {
        $this->setMin($content[0]);
        $this->setMax($content[1]);
        $this->setValue($content[2]);
        return $this;
    }

    public function serializeContents(): array {
        return [$this->getMin(), $this->getMax(), $this->getValue()];
    }
}