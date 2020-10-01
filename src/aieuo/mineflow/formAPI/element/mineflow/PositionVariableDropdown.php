<?php

namespace aieuo\mineflow\formAPI\element\mineflow;

use aieuo\mineflow\variable\DummyVariable;

class PositionVariableDropdown extends VariableDropdown {

    protected $variableType = DummyVariable::POSITION;

    public function __construct(array $variables = [], string $default = "") {
        parent::__construct("@flowItem.form.target.position", $variables, [DummyVariable::POSITION, DummyVariable::LOCATION, DummyVariable::PLAYER, DummyVariable::ENTITY, DummyVariable::BLOCK], $default);
    }
}