<?php

namespace aieuo\mineflow\flowItem\condition;

use aieuo\mineflow\recipe\Recipe;

class CanAddItem extends TypeItem {

    protected $id = self::CAN_ADD_ITEM;

    protected $name = "condition.canAddItem.name";
    protected $detail = "condition.canAddItem.detail";

    public function execute(Recipe $origin): \Generator {
        $this->throwIfCannotExecute();

        $item = $this->getItem($origin);

        $player = $this->getPlayer($origin);
        $this->throwIfInvalidPlayer($player);

        yield true;
        return $player->getInventory()->canAddItem($item);
    }
}