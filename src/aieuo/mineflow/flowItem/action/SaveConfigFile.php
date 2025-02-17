<?php

namespace aieuo\mineflow\flowItem\action;

use aieuo\mineflow\flowItem\base\ConfigFileFlowItem;
use aieuo\mineflow\flowItem\base\ConfigFileFlowItemTrait;
use aieuo\mineflow\flowItem\FlowItem;
use aieuo\mineflow\formAPI\CustomForm;
use aieuo\mineflow\formAPI\element\CancelToggle;
use aieuo\mineflow\formAPI\element\Label;
use aieuo\mineflow\formAPI\element\mineflow\ConfigVariableDropdown;
use aieuo\mineflow\formAPI\Form;
use aieuo\mineflow\recipe\Recipe;
use aieuo\mineflow\utils\Category;
use aieuo\mineflow\utils\Language;

class SaveConfigFile extends FlowItem implements ConfigFileFlowItem {
    use ConfigFileFlowItemTrait;

    protected $id = self::SAVE_CONFIG_FILE;

    protected $name = "action.saveConfigFile.name";
    protected $detail = "action.saveConfigFile.detail";
    protected $detailDefaultReplace = ["config"];

    protected $category = Category::SCRIPT;

    protected $permission = self::PERMISSION_LEVEL_2;

    public function __construct(string $config = "") {
        $this->setConfigVariableName($config);
    }

    public function isDataValid(): bool {
        return $this->getConfigVariableName() !== "";
    }

    public function getDetail(): string {
        if (!$this->isDataValid()) return $this->getName();
        return Language::get($this->detail, [$this->getConfigVariableName()]);
    }

    public function execute(Recipe $origin): \Generator {
        $this->throwIfCannotExecute();

        $config = $this->getConfig($origin);

        $config->save();
        yield true;
    }

    public function getEditForm(array $variables = []): Form {
        return (new CustomForm($this->getName()))
            ->setContents([
                new Label($this->getDescription()),
                new ConfigVariableDropdown($variables, $this->getConfigVariableName()),
                new CancelToggle()
            ]);
    }

    public function parseFromFormData(array $data): array {
        return ["contents" => [$data[1]], "cancel" => $data[2]];
    }

    public function loadSaveData(array $content): FlowItem {
        $this->setConfigVariableName($content[0]);
        return $this;
    }

    public function serializeContents(): array {
        return [$this->getConfigVariableName()];
    }
}