<?php

namespace aieuo\mineflow\flowItem\action;

use aieuo\mineflow\recipe\Recipe;
use pocketmine\Server;

class BroadcastMessage extends TypeMessage {

    protected $id = self::BROADCAST_MESSAGE;

    protected $name = "action.broadcastMessage.name";
    protected $detail = "action.broadcastMessage.detail";

    public function execute(Recipe $origin): \Generator {
        $this->throwIfCannotExecute();

        $message = $origin->replaceVariables($this->getMessage());
        Server::getInstance()->broadcastMessage($message);
        yield true;
    }
}