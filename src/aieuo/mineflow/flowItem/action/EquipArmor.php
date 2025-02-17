<?php

namespace aieuo\mineflow\flowItem\action;

use aieuo\mineflow\flowItem\base\EntityFlowItem;
use aieuo\mineflow\flowItem\base\EntityFlowItemTrait;
use aieuo\mineflow\flowItem\base\ItemFlowItem;
use aieuo\mineflow\flowItem\base\ItemFlowItemTrait;
use aieuo\mineflow\flowItem\FlowItem;
use aieuo\mineflow\formAPI\CustomForm;
use aieuo\mineflow\formAPI\element\CancelToggle;
use aieuo\mineflow\formAPI\element\Dropdown;
use aieuo\mineflow\formAPI\element\Label;
use aieuo\mineflow\formAPI\element\mineflow\EntityVariableDropdown;
use aieuo\mineflow\formAPI\element\mineflow\ItemVariableDropdown;
use aieuo\mineflow\formAPI\Form;
use aieuo\mineflow\recipe\Recipe;
use aieuo\mineflow\utils\Category;
use aieuo\mineflow\utils\Language;
use pocketmine\entity\Living;

class EquipArmor extends FlowItem implements EntityFlowItem, ItemFlowItem {
    use EntityFlowItemTrait, ItemFlowItemTrait;

    protected $id = self::EQUIP_ARMOR;

    protected $name = "action.equipArmor.name";
    protected $detail = "action.equipArmor.detail";
    protected $detailDefaultReplace = ["entity", "item", "index"];

    protected $category = Category::INVENTORY;

    /** @var string */
    private $index;

    private $places = [
        "action.equipArmor.helmet",
        "action.equipArmor.chestplate",
        "action.equipArmor.leggings",
        "action.equipArmor.boots",
    ];

    public function __construct(string $entity = "", string $item = "", string $index = "") {
        $this->setEntityVariableName($entity);
        $this->setItemVariableName($item);
        $this->index = $index;
    }

    public function setIndex(string $health): void {
        $this->index = $health;
    }

    public function getIndex(): string {
        return $this->index;
    }

    public function isDataValid(): bool {
        return $this->getEntityVariableName() !== "" and $this->getItemVariableName() !== "" and $this->index !== "";
    }

    public function getDetail(): string {
        if (!$this->isDataValid()) return $this->getName();
        return Language::get($this->detail, [$this->getEntityVariableName(), $this->getItemVariableName(), Language::get($this->places[$this->getIndex()])]);
    }

    public function execute(Recipe $origin): \Generator {
        $this->throwIfCannotExecute();

        $index = $origin->replaceVariables($this->getIndex());

        $this->throwIfInvalidNumber($index, 0, 3);

        $entity = $this->getEntity($origin);
        $this->throwIfInvalidEntity($entity);

        $item = $this->getItem($origin);

        if ($entity instanceof Living) {
            $entity->getArmorInventory()->setItem((int)$index, $item);
        }
        yield true;
    }

    public function getEditForm(array $variables = []): Form {
        return (new CustomForm($this->getName()))
            ->setContents([
                new Label($this->getDescription()),
                new EntityVariableDropdown($variables, $this->getEntityVariableName()),
                new ItemVariableDropdown($variables, $this->getItemVariableName()),
                new Dropdown("@action.equipArmor.form.index", array_map(function (string $text) {
                    return Language::get($text);
                }, $this->places), (int)$this->getIndex()),
                new CancelToggle()
            ]);
    }

    public function parseFromFormData(array $data): array {
        return ["contents" => [$data[1], $data[2], $data[3]], "cancel" => $data[4]];
    }

    public function loadSaveData(array $content): FlowItem {
        $this->setEntityVariableName($content[0]);
        $this->setItemVariableName($content[1]);
        $this->setIndex((string)$content[2]);
        return $this;
    }

    public function serializeContents(): array {
        return [$this->getEntityVariableName(), $this->getItemVariableName(), $this->getIndex()];
    }
}