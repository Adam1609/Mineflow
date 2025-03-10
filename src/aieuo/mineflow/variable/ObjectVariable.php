<?php

namespace aieuo\mineflow\variable;

class ObjectVariable extends Variable {

    public $type = Variable::OBJECT;

    /* @var string|null $showString */
    private $showString;

    /**
     * @param object $value
     * @param string $name
     * @param string|null $str
     */
    public function __construct(object $value, string $name = "", ?string $str = null) {
        parent::__construct($value, $name);
        $this->showString = $str;
    }

    public function getValue(): object {
        return parent::getValue();
    }

    public function getShowString(): ?string {
        return $this->showString;
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function getValueFromIndex(string $index): ?Variable {
        return null;
    }

    public function isSavable(): bool {
        return false;
    }

    public function toStringVariable(): StringVariable {
        return new StringVariable($this->__toString(), $this->getName());
    }

    public function __toString(): string {
        if (!empty($this->showString)) return (string)$this->showString;
        if (method_exists($this->getValue(), "__toString")) {
            $str = (string)$this->getValue();
        } else {
            $str = (string)get_class($this->getValue());
        }
        return $str;
    }

    public function jsonSerialize(): array {
        return [
            $this->getName(),
            $this->getType(),
            $this->getValue(),
        ];
    }

    public static function fromArray(array $data): ?Variable {
        if (!isset($data["value"])) return null;
        return new self($data["value"], $data["name"] ?? "");
    }

    /**
     * @param string $name
     * @return DummyVariable[]
     */
    public static function getValuesDummy(string $name): array {
        return [];
    }
}