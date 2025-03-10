<?php
declare(strict_types=1);

namespace aieuo\mineflow\flowItem\action;

use aieuo\mineflow\exception\InvalidFlowValueException;
use aieuo\mineflow\flowItem\base\EntityFlowItem;
use aieuo\mineflow\flowItem\base\EntityFlowItemTrait;
use aieuo\mineflow\flowItem\FlowItem;
use aieuo\mineflow\formAPI\CustomForm;
use aieuo\mineflow\formAPI\element\Dropdown;
use aieuo\mineflow\formAPI\element\CancelToggle;
use aieuo\mineflow\formAPI\element\mineflow\EntityVariableDropdown;
use aieuo\mineflow\formAPI\element\mineflow\ExampleInput;
use aieuo\mineflow\formAPI\element\mineflow\ExampleNumberInput;
use aieuo\mineflow\formAPI\element\Label;
use aieuo\mineflow\formAPI\Form;
use aieuo\mineflow\recipe\Recipe;
use aieuo\mineflow\utils\Category;
use aieuo\mineflow\utils\Language;
use aieuo\mineflow\variable\DummyVariable;
use aieuo\mineflow\variable\object\PositionObjectVariable;
use pocketmine\level\Position;
use pocketmine\math\Vector3;

class GetEntitySidePosition extends FlowItem implements EntityFlowItem {
    use EntityFlowItemTrait;

    protected $id = self::GET_ENTITY_SIDE;

    protected $name = "action.getEntitySide.name";
    protected $detail = "action.getEntitySide.detail";
    protected $detailDefaultReplace = ["entity", "direction", "step", "result"];

    protected $category = Category::LEVEL;

    protected $returnValueType = self::RETURN_VARIABLE_NAME;

    /** @var string */
    private $direction;
    /** @var string  */
    private $steps;
    /** @var string  */
    private $resultName;

    public const SIDE_DOWN = "down";
    public const SIDE_UP = "up";
    public const SIDE_NORTH = "north";
    public const SIDE_SOUTH = "south";
    public const SIDE_WEST = "west";
    public const SIDE_EAST = "east";
    public const SIDE_FRONT = "front";
    public const SIDE_BEHIND = "behind";
    public const SIDE_LEFT = "left";
    public const SIDE_RIGHT = "right";

    /** @var string[]  */
    private $directions = [
        self::SIDE_DOWN,
        self::SIDE_UP,
        self::SIDE_NORTH,
        self::SIDE_SOUTH,
        self::SIDE_WEST,
        self::SIDE_EAST,
        self::SIDE_FRONT,
        self::SIDE_BEHIND,
        self::SIDE_LEFT,
        self::SIDE_RIGHT,
    ];

    /** @var array */
    private $vector3SideMap = [
        self::SIDE_DOWN => Vector3::SIDE_DOWN,
        self::SIDE_UP => Vector3::SIDE_UP,
        self::SIDE_NORTH => Vector3::SIDE_NORTH,
        self::SIDE_SOUTH => Vector3::SIDE_SOUTH,
        self::SIDE_WEST => Vector3::SIDE_WEST,
        self::SIDE_EAST => Vector3::SIDE_EAST,
    ];

    /** @var array */
    private $directionSideMap = [
        Vector3::SIDE_EAST,
        Vector3::SIDE_SOUTH,
        Vector3::SIDE_WEST,
        Vector3::SIDE_NORTH,
    ];

    public function __construct(string $entity = "", string $direction = "", string $step = "1", string $result = "pos") {
        $this->setEntityVariableName($entity);
        $this->direction = $direction;
        $this->steps = $step;
        $this->resultName = $result;
    }

    public function setDirection(string $direction): self {
        $this->direction = $direction;
        return $this;
    }

    public function getDirection(): string {
        return $this->direction;
    }

    public function setSteps(string $steps): void {
        $this->steps = $steps;
    }

    public function getSteps(): string {
        return $this->steps;
    }

    public function setResultName(string $resultName): void {
        $this->resultName = $resultName;
    }

    public function getResultName(): string {
        return $this->resultName;
    }

    public function isDataValid(): bool {
        return $this->getEntityVariableName() !== "" and $this->direction !== "" and $this->steps !== "" and $this->resultName !== "";
    }

    public function getDetail(): string {
        if (!$this->isDataValid()) return $this->getName();
        return Language::get($this->detail, [$this->getEntityVariableName(), $this->getDirection(), $this->getSteps(), $this->getResultName()]);
    }

    public function execute(Recipe $origin): \Generator {
        $this->throwIfCannotExecute();

        $entity = $this->getEntity($origin);
        $this->throwIfInvalidEntity($entity);

        $side = $origin->replaceVariables($this->getDirection());
        $step = $origin->replaceVariables($this->getSteps());
        $resultName = $origin->replaceVariables($this->getResultName());

        $this->throwIfInvalidNumber($step);

        $direction = $entity->getDirection();
        $pos = $entity->getPosition()->floor()->add(0.5, 0.5, 0.5);
        switch ($side) {
            case self::SIDE_DOWN:
            case self::SIDE_UP:
            case self::SIDE_NORTH:
            case self::SIDE_SOUTH:
            case self::SIDE_WEST:
            case self::SIDE_EAST:
                $pos = $pos->getSide($this->vector3SideMap[$side], (int)$step);
                break;
            /** @noinspection PhpMissingBreakStatementInspection */
            case self::SIDE_LEFT:
                $direction ++;
            /** @noinspection PhpMissingBreakStatementInspection */
            case self::SIDE_BEHIND:
                $direction ++;
            /** @noinspection PhpMissingBreakStatementInspection */
            case self::SIDE_RIGHT:
                $direction ++;
            case self::SIDE_FRONT:
                $pos = $pos->getSide($this->directionSideMap[$direction % 4], (int)$step);
                break;
            default:
                throw new InvalidFlowValueException($this->getName(), Language::get("action.getEntitySide.direction.notFound", [$side]));
        }

        $origin->addVariable(new PositionObjectVariable(Position::fromObject($pos, $entity->getLevelNonNull()), $resultName));
        yield true;
        return $this->getResultName();
    }

    public function getEditForm(array $variables = []): Form {
        return (new CustomForm($this->getName()))
            ->setContents([
                new Label($this->getDescription()),
                new EntityVariableDropdown($variables, $this->getEntityVariableName()),
                new Dropdown("@action.getEntitySide.form.direction", $this->directions, (int)array_search($this->getDirection(), $this->directions, true)),
                new ExampleNumberInput("@action.getEntitySide.form.steps", "1", $this->getSteps(), true),
                new ExampleInput("@action.form.resultVariableName", "pos", $this->getResultName(), true),
                new CancelToggle()
            ]);
    }

    public function parseFromFormData(array $data): array {
        return ["contents" => [$data[1], $this->directions[$data[2]] ?? "", $data[3], $data[4]], "cancel" => $data[5]];
    }

    public function loadSaveData(array $content): FlowItem {
        $this->setEntityVariableName($content[0]);
        $this->setDirection($content[1]);
        $this->setSteps($content[2]);
        $this->setResultName($content[3]);
        return $this;
    }

    public function serializeContents(): array {
        return [$this->getEntityVariableName(), $this->getDirection(), $this->getSteps(), $this->getResultName()];
    }

    public function getAddingVariables(): array {
        return [new DummyVariable($this->getResultName(), DummyVariable::POSITION)];
    }
}