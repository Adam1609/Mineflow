<?php

namespace aieuo\mineflow\ui;

use aieuo\mineflow\flowItem\FlowItemContainer;
use aieuo\mineflow\formAPI\CustomForm;
use aieuo\mineflow\formAPI\element\Button;
use aieuo\mineflow\formAPI\element\CancelToggle;
use aieuo\mineflow\formAPI\element\Dropdown;
use aieuo\mineflow\formAPI\element\Input;
use aieuo\mineflow\formAPI\element\Label;
use aieuo\mineflow\formAPI\element\Toggle;
use aieuo\mineflow\formAPI\ListForm;
use aieuo\mineflow\formAPI\ModalForm;
use aieuo\mineflow\Main;
use aieuo\mineflow\recipe\Recipe;
use aieuo\mineflow\ui\trigger\BaseTriggerForm;
use aieuo\mineflow\utils\Language;
use aieuo\mineflow\utils\Session;
use pocketmine\Player;

class RecipeForm {

    public function sendMenu(Player $player, array $messages = []): void {
        (new ListForm("@mineflow.recipe"))
            ->addButtons([
                new Button("@form.back", function () use($player) { (new HomeForm)->sendMenu($player); }),
                new Button("@form.add", function () use($player) { $this->sendAddRecipe($player); }),
                new Button("@form.edit", function () use($player) { $this->sendSelectRecipe($player); }),
                new Button("@form.recipe.menu.recipeList", function () use($player) { $this->sendRecipeList($player); }),
                new Button("@mineflow.export", function () use($player) {
                    (new MineflowForm)->selectRecipe($player, "@form.export.selectRecipe.title", function(Recipe $recipe) use($player) {
                        (new ExportForm())->sendRecipeListByRecipe($player, $recipe);
                    });
                }),
                new Button("@mineflow.import", function () use($player) { (new ImportForm)->sendSelectImportFile($player); }),
            ])->addMessages($messages)->show($player);
    }

    public function sendAddRecipe(Player $player, array $default = []): void {
        $manager = Main::getRecipeManager();
        $name = $manager->getNotDuplicatedName("recipe");

        ($it = new CustomForm("@form.recipe.addRecipe.title"))->setContents([
                new Input("@form.recipe.recipeName", $name, $default[0] ?? ""),
                new Input("@form.recipe.groupName", "", $default[1] ?? ""),
                new CancelToggle(function () use($player) { $this->sendMenu($player); }),
            ])->onReceive(function (Player $player, array $data, string $defaultName) use($it) {
                $manager = Main::getRecipeManager();
                $name = $data[0] === "" ? $defaultName : $data[0];
                $group = $data[1];

                $errors = [];
                if (preg_match("#[.¥/:?<>|*\"]#u", preg_quote($name, "/@#~"))) $errors[] = ["@form.recipe.invalidName", 0];
                if (preg_match("#[.¥:?<>|*\"]#u", preg_quote($group, "/@#~"))) $errors[] = ["@form.recipe.invalidName", 1];
                if (!empty($errors)) {
                    $it->resend($errors);
                    return;
                }

                if ($manager->exists($name, $group)) {
                    $newName = $manager->getNotDuplicatedName($name, $group);
                    (new MineflowForm)->confirmRename($player, $name, $newName,
                        function (string $name) use ($player, $data) {
                            $manager = Main::getRecipeManager();
                            $recipe = new Recipe($name, $data[1], $player->getName());
                            $manager->add($recipe);
                            Session::getSession($player)->set("recipe_menu_prev", function() use($player, $recipe) {
                                $this->sendRecipeList($player, $recipe->getGroup());
                            });
                            $this->sendRecipeMenu($player, $recipe);
                        },
                        function (string $name) use ($it) {
                            $it->resend([[Language::get("form.recipe.exists", [$name]), 0]]);
                        });
                    return;
                }

                $recipe = new Recipe($name, $group, $player->getName());
                if (file_exists($recipe->getFileName($manager->getSaveDir()))) {
                    $it->resend([[Language::get("form.recipe.exists", [$name]), 0]]);
                    return;
                }

                $manager->add($recipe);
                Session::getSession($player)->set("recipe_menu_prev", function() use($player, $recipe) {
                    $this->sendRecipeList($player, $recipe->getGroup());
                });
                $this->sendRecipeMenu($player, $recipe);
            })->addArgs($name)->show($player);
    }

    public function sendSelectRecipe(Player $player, array $default = []): void {
        (new MineflowForm)->selectRecipe($player, "@form.recipe.select.title",
            function (Recipe $recipe) use($player) {
                Session::getSession($player)->set("recipe_menu_prev", function() use($player, $recipe) {
                    $this->sendRecipeList($player, $recipe->getGroup());
                });
                $this->sendRecipeMenu($player, $recipe);
            },
            function () use($player) {
                $this->sendMenu($player);
            }, $default);
    }

    public function sendRecipeList(Player $player, string $path = ""): void {
        $manager = Main::getRecipeManager();
        $recipeGroups = $manager->getByPath($path);
        $buttons = [new Button("@form.back")];
        $recipes = $recipeGroups[$path] ?? [];
        foreach ($recipes as $recipe) {
            $buttons[] = new Button($recipe->getName());
        }
        unset($recipeGroups[$path]);

        $groups = [];
        foreach ($recipeGroups as $group => $value) {
            if ($path !== "") {
                $name = explode("/", str_replace($path."/", "", $group))[0];
            } else {
                $name = explode("/", $group)[0];
            }

            if (!isset($groups[$name])) {
                $buttons[] = new Button("[$name]");
                $groups[$name] = $path !== "" ? $path."/".$name : $name;
            }
        }
        $recipeGroups = array_merge($recipes, array_values($groups));

        (new ListForm("@form.recipe.recipeList.title"))
            ->addButtons($buttons)
            ->onReceive(function (Player $player, int $data, string $path, array $recipes) {
                if ($data === 0) {
                    if ($path === "") {
                        $this->sendMenu($player);
                        return;
                    }
                    $paths = explode("/", $path);
                    array_pop($paths);
                    $this->sendRecipeList($player, implode("/", $paths));
                    return;
                }
                $data--;

                $recipe = array_values($recipes)[$data];
                if ($recipe instanceof Recipe) {
                    Session::getSession($player)->set("recipe_menu_prev", function() use($player, $path) {
                        $this->sendRecipeList($player, $path);
                    });
                    $this->sendRecipeMenu($player, $recipe);
                    return;
                }
                $this->sendRecipeList($player, $recipe);
            })->addArgs($path, $recipeGroups)->show($player);
    }

    public function sendRecipeMenu(Player $player, Recipe $recipe, array $messages = []): void {
        $detail = trim($recipe->getDetail());
        (new ListForm(Language::get("form.recipe.recipeMenu.title", [$recipe->getPathname()])))
            ->setContent(empty($detail) ? "@recipe.noActions" : $detail)
            ->addButtons([
                new Button("@form.back"),
                new Button("@action.edit"),
                new Button("@form.recipe.recipeMenu.changeName"),
                new Button("@form.recipe.recipeMenu.execute"),
                new Button("@form.recipe.recipeMenu.setTrigger"),
                new Button("@form.recipe.args.return.set"),
                new Button("@form.recipe.changeTarget"),
                new Button("@form.recipe.recipeMenu.save"),
                new Button("@mineflow.export"),
                new Button("@form.delete"),
            ])->onReceive(function (Player $player, int $data, Recipe $recipe) {
                switch ($data) {
                    case 0:
                        $prev = Session::getSession($player)->get("recipe_menu_prev");
                        is_callable($prev) ? $prev($player) : $this->sendMenu($player);
                        break;
                    case 1:
                        Session::getSession($player)->set("parents", []);
                        (new FlowItemContainerForm)->sendActionList($player, $recipe, FlowItemContainer::ACTION);
                        break;
                    case 2:
                        $this->sendChangeName($player, $recipe);
                        break;
                    case 3:
                        $recipe->executeAllTargets($player);
                        break;
                    case 4:
                        $this->sendTriggerList($player, $recipe);
                        break;
                    case 5:
                        (new ListForm("@form.recipe.args.return.set"))
                            ->setButtons([
                                new Button("@form.back"),
                                new Button("@form.recipe.args.set"),
                                new Button("@form.recipe.returnValue.set"),
                            ])->onReceive(function (Player $player, int $data, Recipe $recipe) {
                                switch ($data) {
                                    case 0:
                                        $this->sendRecipeMenu($player, $recipe);
                                        break;
                                    case 1:
                                        $this->sendSetArgs($player, $recipe);
                                        break;
                                    case 2:
                                        $this->sendSetReturns($player, $recipe);
                                        break;
                                }
                            })->addArgs($recipe)->show($player);
                        break;
                    case 6:
                        $this->sendChangeTarget($player, $recipe);
                        break;
                    case 7:
                        $recipe->save(Main::getRecipeManager()->getSaveDir());
                        $this->sendRecipeMenu($player, $recipe, ["@form.recipe.recipeMenu.save.success"]);
                        break;
                    case 8:
                        (new ExportForm)->sendRecipeListByRecipe($player, $recipe);
                        break;
                    case 9:
                        (new ModalForm(Language::get("form.recipe.delete.title", [$recipe->getName()])))
                            ->setContent(Language::get("form.delete.confirm", [$recipe->getName()]))
                            ->onYes(function() use ($player, $recipe) {
                                $manager = Main::getRecipeManager();
                                $recipe->removeTriggerAll();
                                $manager->remove($recipe->getName(), $recipe->getGroup());
                                $this->sendMenu($player, ["@form.deleted"]);
                            })->onNo(function() use($player, $recipe) {
                                $this->sendRecipeMenu($player, $recipe, ["@form.cancelled"]);
                            })->show($player);
                        break;
                }
            })->addArgs($recipe)->addMessages($messages)->show($player);
    }

    public function sendChangeName(Player $player, Recipe $recipe): void {
        $form = new CustomForm(Language::get("form.recipe.changeName.title", [$recipe->getName()]));
        $form->setContents([
                new Label("@form.recipe.changeName.content0"),
                new Input("@form.recipe.changeName.content1", "", $recipe->getName(), true),
                new CancelToggle(function () use($player, $recipe) { $this->sendRecipeMenu($player, $recipe, ["@form.cancelled"]); })
            ])->onReceive(function (Player $player, array $data, Recipe $recipe) use($form) {
                $manager = Main::getRecipeManager();
                if ($manager->exists($data[1], $recipe->getGroup())) {
                    $newName = $manager->getNotDuplicatedName($data[1], $recipe->getGroup());
                    (new MineflowForm)->confirmRename($player, $data[1], $newName,
                        function (string $name) use ($player, $recipe) {
                            $manager = Main::getRecipeManager();
                            $manager->rename($recipe->getName(), $name, $recipe->getGroup());
                            $this->sendRecipeMenu($player, $recipe);
                        },
                        function (string $name) use ($form) {
                            $form->resend([[Language::get("form.recipe.exists", [$name]), 1]]);
                        });
                    return;
                }
                $manager->rename($recipe->getName(), $data[1], $recipe->getGroup());
                $this->sendRecipeMenu($player, $recipe, ["@form.recipe.changeName.success"]);
            })->addArgs($recipe)->show($player);
    }

    public function sendTriggerList(Player $player, Recipe $recipe, array $messages = []): void {
        $triggers = $recipe->getTriggers();

        $buttons = [new Button("@form.back"), new Button("@trigger.add")];
        foreach ($triggers as $trigger) {
            $buttons[] = new Button((string)$trigger);
        }

        (new ListForm(Language::get("form.recipe.triggerList.title", [$recipe->getName()])))
            ->addButtons($buttons)
            ->onReceive(function (Player $player, int $data, Recipe $recipe, array $triggers) {
                if ($data === 0) {
                    $this->sendRecipeMenu($player, $recipe);
                    return;
                }
                if ($data === 1) {
                    (new BaseTriggerForm)->sendSelectTriggerType($player, $recipe);
                    return;
                }
                $data -= 2;

                $trigger = $triggers[$data];
                (new BaseTriggerForm)->sendAddedTriggerMenu($player, $recipe, $trigger);
            })->addArgs($recipe, $triggers)->addMessages($messages)->show($player);
    }

    public function sendSetArgs(Player $player, Recipe $recipe, array $messages = []): void {
        $contents = [new Toggle("@form.exit")];
        foreach ($recipe->getArguments() as $i => $argument) {
            $contents[] = new Input(Language::get("form.recipe.args", [$i]), "", $argument);
        }
        $contents[] = new Input("@form.recipe.args.add");
        (new CustomForm("@form.recipe.args.set"))
            ->setContents($contents)
            ->onReceive(function (Player $player, array $data, Recipe $recipe) {
                if ($data[0]) {
                    $this->sendRecipeMenu($player, $recipe);
                    return;
                }

                $arguments = [];
                for ($i = 1, $iMax = count($data); $i < $iMax; $i++) {
                    if ($data[$i] !== "") $arguments[] = $data[$i];
                }
                $recipe->setArguments($arguments);
                $this->sendSetArgs($player, $recipe, ["@form.changed"]);
            })->onClose(function (Player $player, Recipe $recipe) {
                $this->sendRecipeMenu($player, $recipe);
            })->addMessages($messages)->addArgs($recipe)->show($player);
    }

    public function sendSetReturns(Player $player, Recipe $recipe, array $messages = []): void {
        $contents = [new Toggle("@form.exit")];
        foreach ($recipe->getReturnValues() as $i => $value) {
            $contents[] = new Input(Language::get("form.recipe.returnValue", [$i]), "", $value);
        }
        $contents[] = new Input("@form.recipe.returnValue.add");
        (new CustomForm("@form.recipe.returnValue.set"))
            ->setContents($contents)
            ->onReceive(function (Player $player, array $data, Recipe $recipe) {
                if ($data[0]) {
                    $this->sendRecipeMenu($player, $recipe);
                    return;
                }

                $returnValues = [];
                for ($i = 1, $iMax = count($data); $i < $iMax; $i++) {
                    if ($data[$i] !== "") $returnValues[] = $data[$i];
                }
                $recipe->setReturnValues($returnValues);
                $this->sendSetReturns($player, $recipe, ["@form.changed"]);
            })->onClose(function (Player $player, Recipe $recipe) {
                $this->sendRecipeMenu($player, $recipe);
            })->addMessages($messages)->addArgs($recipe)->show($player);
    }

    public function sendChangeTarget(Player $player, Recipe $recipe, array $default = [], array $errors = []): void {
        $default1 = $default[1] ?? ($recipe->getTargetType() === Recipe::TARGET_SPECIFIED ? implode(",", $recipe->getTargetOptions()["specified"]) : "");
        $default2 = $default[2] ?? ($recipe->getTargetType() === Recipe::TARGET_RANDOM ? (string)$recipe->getTargetOptions()["random"] : "");
        (new CustomForm(Language::get("form.recipe.changeTarget.title", [$recipe->getName()])))->setContents([
            new Dropdown("@form.recipe.changeTarget.type", [
                Language::get("form.recipe.target.default"),
                Language::get("form.recipe.target.specified"),
                Language::get("form.recipe.target.all"),
                Language::get("form.recipe.target.random"),
                Language::get("form.recipe.target.none"),
            ], $default[0] ?? $recipe->getTargetType()),
            new Input("@form.recipe.changeTarget.name", "@form.recipe.changeTarget.name.placeholder", $default1),
            new Input("@form.recipe.changeTarget.random", "@form.recipe.changeTarget.random.placeholder", $default2),
            new CancelToggle()
        ])->onReceive(function (Player $player, array $data, Recipe $recipe) {
            if ($data[3]) {
                $this->sendRecipeMenu($player, $recipe, ["@form.cancelled"]);
                return;
            }

            if ($data[0] === 1 and $data[1] === "") {
                $this->sendChangeTarget($player, $recipe, $data, [["@form.insufficient", 1]]);
                return;
            }
            if ($data[0] === 3 and $data[2] === "") {
                $this->sendChangeTarget($player, $recipe, $data, [["@form.insufficient", 2]]);
                return;
            }

            switch ($data[0]) {
                case 1:
                    $recipe->setTargetSetting((int)$data[0], ["specified" => explode(",", $data[1])]);
                    break;
                case 3:
                    $recipe->setTargetSetting((int)$data[0], ["random" => empty($data[2]) ? 1 : (int)$data[2]]);
                    break;
                default:
                    $recipe->setTargetSetting((int)$data[0]);
                    break;
            }
            $this->sendRecipeMenu($player, $recipe, ["@form.changed"]);
        })->addArgs($recipe)->addErrors($errors)->show($player);
    }
}