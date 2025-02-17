<?php

namespace aieuo\mineflow\flowItem\action;

use aieuo\mineflow\flowItem\base\PlayerFlowItem;
use aieuo\mineflow\flowItem\base\PlayerFlowItemTrait;
use aieuo\mineflow\flowItem\FlowItem;
use aieuo\mineflow\formAPI\CustomForm;
use aieuo\mineflow\formAPI\element\CancelToggle;
use aieuo\mineflow\formAPI\element\Dropdown;
use aieuo\mineflow\formAPI\element\Label;
use aieuo\mineflow\formAPI\element\mineflow\PlayerVariableDropdown;
use aieuo\mineflow\formAPI\Form;
use aieuo\mineflow\recipe\Recipe;
use aieuo\mineflow\utils\Category;
use aieuo\mineflow\utils\Language;

class SetGamemode extends FlowItem implements PlayerFlowItem {
    use PlayerFlowItemTrait;

    protected $id = self::SET_GAMEMODE;

    protected $name = "action.setGamemode.name";
    protected $detail = "action.setGamemode.detail";
    protected $detailDefaultReplace = ["player", "gamemode"];

    protected $category = Category::PLAYER;

    private $gamemodes = [
        "action.gamemode.survival",
        "action.gamemode.creative",
        "action.gamemode.adventure",
        "action.gamemode.spectator"
    ];

    /** @var string */
    private $gamemode;

    public function __construct(string $player = "", string $gamemode = "") {
        $this->setPlayerVariableName($player);
        $this->gamemode = $gamemode;
    }

    public function setGamemode(string $gamemode): void {
        $this->gamemode = $gamemode;
    }

    public function getGamemode(): string {
        return $this->gamemode;
    }

    public function isDataValid(): bool {
        return $this->getPlayerVariableName() !== "" and $this->gamemode !== "";
    }

    public function getDetail(): string {
        if (!$this->isDataValid()) return $this->getName();
        return Language::get($this->detail, [$this->getPlayerVariableName(), Language::get($this->gamemodes[$this->getGamemode()])]);
    }

    public function execute(Recipe $origin): \Generator {
        $this->throwIfCannotExecute();

        $gamemode = $origin->replaceVariables($this->getGamemode());
        $this->throwIfInvalidNumber($gamemode, 0, 3);

        $player = $this->getPlayer($origin);
        $this->throwIfInvalidPlayer($player);

        $player->setGamemode((int)$gamemode);
        yield true;
    }

    public function getEditForm(array $variables = []): Form {
        return (new CustomForm($this->getName()))
            ->setContents([
                new Label($this->getDescription()),
                new PlayerVariableDropdown($variables, $this->getPlayerVariableName()),
                new Dropdown("@action.setGamemode.form.gamemode", array_map(function (string $mode) {
                    return Language::get($mode);
                }, $this->gamemodes), (int)$this->getGamemode()),
                new CancelToggle()
            ]);
    }

    public function parseFromFormData(array $data): array {
        return ["contents" => [$data[1], (string)$data[2]], "cancel" => $data[3]];
    }

    public function loadSaveData(array $content): FlowItem {
        $this->setPlayerVariableName($content[0]);
        $this->setGamemode($content[1]);
        return $this;
    }

    public function serializeContents(): array {
        return [$this->getPlayerVariableName(), $this->getGamemode()];
    }
}