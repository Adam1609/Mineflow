<?php

namespace aieuo\mineflow\formAPI\element;

use aieuo\mineflow\formAPI\response\CustomFormResponse;
use aieuo\mineflow\Main;
use aieuo\mineflow\utils\Language;
use pocketmine\Player;

class NumberInput extends Input {

    /* @var float|null */
    private $min;
    /* @var float|null */
    private $max;
    /** @var array */
    private $excludes;

    public function __construct(string $text, string $placeholder = "", string $default = "", bool $required = false, ?float $min = null, ?float $max = null, array $excludes = []) {
        parent::__construct($text, $placeholder, $default, $required);

        $this->min = $min;
        $this->max = $max;
        $this->excludes = $excludes;
    }

    public function setMin(?float $min): void {
        $this->min = $min;
    }

    public function getMin(): ?float {
        return $this->min;
    }

    public function setMax(?float $max): void {
        $this->max = $max;
    }

    public function getMax(): ?float {
        return $this->max;
    }

    public function setExcludes(array $exclude): void {
        $this->excludes = $exclude;
    }

    public function getExcludes(): array {
        return $this->excludes;
    }

    public function onFormSubmit(CustomFormResponse $response, Player $player): void {
        parent::onFormSubmit($response, $player);
        $data = $response->getInputResponse();

        if ($data === "" or Main::getVariableHelper()->containsVariable($data)) return;

        if (!is_numeric($data)) {
            $response->addError("@action.error.notNumber");
        } elseif (($min = $this->getMin()) !== null and (float)$data < $min) {
            $response->addError(Language::get("action.error.lessValue", [$min]));
        } elseif (($max = $this->getMax()) !== null and (float)$data > $max) {
            $response->addError(Language::get("action.error.overValue", [$max]));
        } elseif (($excludes = $this->getExcludes()) !== null and in_array((float)$data, $excludes, true)) {
            $response->addError(Language::get("action.error.excludedNumber", [implode(",", $excludes)]));
        }
    }

    public function serializeExtraData(): array {
        return [
            "type" => "number",
            "required" => $this->isRequired(),
            "min" => $this->getMin(),
            "max" => $this->getMax(),
            "excludes" => $this->getExcludes(),
        ];
    }
}