<?php

namespace aieuo\mineflow\variable;

class StringVariable extends Variable implements \JsonSerializable {

    public $type = Variable::STRING;

    public function getValue(): string {
        return parent::getValue();
    }

    public function append(StringVariable $var, string $resultName = "result"): StringVariable {
        $result = $this->getValue().$var->getValue();
        return new StringVariable($result, $resultName);
    }

    public function replace(StringVariable $var, string $resultName = "result"): StringVariable {
        $result = str_replace($var->getValue(), "", $this->getValue());
        return new StringVariable($result, $resultName);
    }

    public function repeat(StringVariable $var, string $resultName = "result"): StringVariable {
        $result = str_repeat($this->getValue(), (int)$var->getValue());
        return new StringVariable($result, $resultName);
    }

    public function split(StringVariable $var, string $resultName = "result"): ListVariable {
        $result = array_map(function (string $text) {
            return new StringVariable(trim($text));
        }, explode($var->getValue(), $this->getValue()));
        return new ListVariable($result, $resultName);
    }

    public function toStringVariable(): StringVariable {
        return $this;
    }

    public function __toString(): string {
        return $this->getValue();
    }

    public function jsonSerialize(): array {
        return [
            "name" => $this->getName(),
            "type" => $this->getType(),
            "value" => $this->getValue(),
        ];
    }

    public static function fromArray(array $data): ?Variable {
        if (!isset($data["value"])) return null;
        return new self($data["value"], $data["name"] ?? "");
    }
}