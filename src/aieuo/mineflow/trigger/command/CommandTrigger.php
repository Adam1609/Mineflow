<?php

namespace aieuo\mineflow\trigger\command;

use aieuo\mineflow\trigger\Trigger;
use aieuo\mineflow\trigger\Triggers;
use aieuo\mineflow\utils\Language;
use aieuo\mineflow\variable\DefaultVariables;
use aieuo\mineflow\variable\DummyVariable;

class CommandTrigger extends Trigger {

    /**
     * @param string $key
     * @param string $subKey
     * @return self
     */
    public static function create(string $key, string $subKey = ""): Trigger {
        return new CommandTrigger($key, $subKey);
    }

    public function __construct(string $key, string $subKey = "") {
        parent::__construct(Triggers::COMMAND, $key, $subKey);
    }

    /**
     * @param string $command
     * @return array
     * @noinspection PhpMissingParamTypeInspection
     */
    public function getVariables($command): array {
        return DefaultVariables::getCommandVariables($command);
    }

    public function getVariablesDummy(): array {
        return [
            new DummyVariable("cmd", DummyVariable::STRING),
            new DummyVariable("args", DummyVariable::LIST),
        ];
    }

    public function __toString(): string {
        return Language::get("trigger.command.string", [$this->getSubKey()]);
    }
}