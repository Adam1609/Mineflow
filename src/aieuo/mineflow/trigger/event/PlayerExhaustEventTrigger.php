<?php

namespace aieuo\mineflow\trigger\event;

use aieuo\mineflow\variable\DefaultVariables;
use aieuo\mineflow\variable\DummyVariable;
use aieuo\mineflow\variable\NumberVariable;
use pocketmine\event\player\PlayerExhaustEvent;

class PlayerExhaustEventTrigger extends EventTrigger {
    public function __construct(string $subKey = "") {
        parent::__construct(PlayerExhaustEvent::class, $subKey);
    }

    public function getVariables($event): array {
        /** @var PlayerExhaustEvent $event */
        $target = $event->getPlayer();
        $variables = DefaultVariables::getEntityVariables($target);
        $variables["amount"] = new NumberVariable($event->getAmount(), "amount");
        $variables["cause"] = new NumberVariable($event->getCause(), "cause");
        return $variables;
    }

    public function getVariablesDummy(): array {
        return [
            "target" => new DummyVariable("target", DummyVariable::PLAYER),
            "amount" => new DummyVariable("amount", DummyVariable::NUMBER),
            "cause" => new DummyVariable("cause", DummyVariable::NUMBER),
        ];
    }
}