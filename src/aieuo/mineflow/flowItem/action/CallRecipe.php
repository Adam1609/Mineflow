<?php

namespace aieuo\mineflow\flowItem\action;

use aieuo\mineflow\exception\InvalidFlowValueException;
use aieuo\mineflow\flowItem\FlowItem;
use aieuo\mineflow\formAPI\CustomForm;
use aieuo\mineflow\formAPI\element\CancelToggle;
use aieuo\mineflow\formAPI\element\mineflow\ExampleInput;
use aieuo\mineflow\formAPI\element\Label;
use aieuo\mineflow\formAPI\Form;
use aieuo\mineflow\Main;
use aieuo\mineflow\recipe\Recipe;
use aieuo\mineflow\utils\Language;

class CallRecipe extends ExecuteRecipe {

    protected $id = self::CALL_RECIPE;

    protected $name = "action.callRecipe.name";
    protected $detail = "action.callRecipe.detail";

    public function execute(Recipe $origin): \Generator {
        $this->throwIfCannotExecute();

        $name = $origin->replaceVariables($this->getRecipeName());

        $recipeManager = Main::getRecipeManager();
        [$recipeName, $group] = $recipeManager->parseName($name);
        if (empty($group)) $group = $origin->getGroup();

        $recipe = $recipeManager->get($recipeName, $group) ?? $recipeManager->get($recipeName);
        if ($recipe === null) {
            throw new InvalidFlowValueException($this->getName(), Language::get("action.executeRecipe.notFound"));
        }

        $helper = Main::getVariableHelper();
        $args = [];
        $recipe = clone $recipe;
        foreach ($this->getArgs() as $arg) {
            if (!$helper->isVariableString($arg)) {
                $args[] = $helper->replaceVariables($arg, $origin->getVariables());
                continue;
            }
            $arg = $origin->getVariable(substr($arg, 1, -1)) ?? $helper->get(substr($arg, 1, -1)) ?? $arg;
            $args[] = $arg;
        }
        $recipe->setSourceRecipe($origin);
        $recipe->executeAllTargets($origin->getTarget(), [], $origin->getEvent(), $args);
        yield false;
    }

    public function getEditForm(array $variables = []): Form {
        return (new CustomForm($this->getName()))
            ->setContents([
                new Label($this->getDescription()),
                new ExampleInput("@action.executeRecipe.form.name", "aieuo", $this->getRecipeName(), true),
                new ExampleInput("@action.callRecipe.form.args", "{target}, 1, aieuo", implode(", ", $this->getArgs())),
                new CancelToggle()
            ]);
    }

    public function loadSaveData(array $content): FlowItem {
        $this->setRecipeName($content[0]);
        $this->setArgs($content[1]);
        return $this;
    }
}