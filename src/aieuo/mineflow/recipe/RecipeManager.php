<?php

namespace aieuo\mineflow\recipe;

use aieuo\mineflow\exception\FlowItemLoadException;
use aieuo\mineflow\flowItem\action\ExecuteRecipe;
use aieuo\mineflow\flowItem\FlowItemContainer;
use aieuo\mineflow\Main;
use aieuo\mineflow\utils\Language;
use aieuo\mineflow\utils\Logger;

class RecipeManager {

    /** @var Recipe[][] */
    protected $recipes = [];

    /** @var string */
    private $saveDir;

    public function __construct(string $saveDir) {
        $this->saveDir = $saveDir;
        if (!file_exists($this->saveDir)) @mkdir($this->saveDir, 0777, true);
    }

    public function getSaveDir(): string {
        return $this->saveDir;
    }

    public function loadRecipes(): void {
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(
                $this->getSaveDir(),
                \FilesystemIterator::CURRENT_AS_FILEINFO | \FilesystemIterator::KEY_AS_PATHNAME | \FilesystemIterator::SKIP_DOTS
            )
        );
        $files = new \RegexIterator($files, '/\.json$/', \RecursiveRegexIterator::MATCH);

        foreach ($files as $file) {
            /** @var \SplFileInfo $file */
            $pathname = $file->getPathname();
            $group = str_replace(
                ["\\", str_replace("\\", "/", substr($this->getSaveDir(), 0, -1))],
                ["/", ""], $file->getPath());
            if ($group !== "") $group = substr($group, 1);

            $json = file_get_contents($pathname);
            $data = json_decode($json, true);
            if ($data === null) {
                Logger::warning(Language::get("recipe.json.decode.failed", [$pathname, json_last_error_msg()]));
                continue;
            }

            if (!isset($data["name"]) or !isset($data["actions"])) {
                Logger::warning(Language::get("recipe.json.decode.failed", [$pathname, ["recipe.json.key.missing"]]));
                continue;
            }

            $recipe = new Recipe($data["name"], $group, $data["author"] ?? "");
            $recipe->setRawData($json);
            try {
                $recipe->loadSaveData($data["actions"]);
                $recipe->setTargetSetting(
                    $data["target"]["type"] ?? Recipe::TARGET_DEFAULT,
                    $data["target"]["options"] ?? []
                );
                $recipe->setTriggersFromArray($data["triggers"] ?? []);
                $recipe->setArguments($data["arguments"] ?? []);
                $recipe->setReturnValues($data["returnValues"] ?? []);
            } catch (\ErrorException|\UnexpectedValueException $e) {
                Logger::warning(Language::get("recipe.load.failed", [$data["name"], $e->getMessage()]).PHP_EOL);
                continue;
            } catch (FlowItemLoadException $e) {
                Logger::warning(Language::get("recipe.load.failed", [$data["name"], ""]));
                Logger::warning($e->getMessage().PHP_EOL);
                continue;
            }


            $this->add($recipe, false);
        }
    }

    public function exists(string $name, string $group = ""): bool {
        return isset($this->recipes[$group][$name]);
    }

    public function add(Recipe $recipe, bool $createFile = true): void {
        $this->recipes[$recipe->getGroup()][$recipe->getName()] = $recipe;
        if ($createFile and !file_exists($recipe->getFileName($this->getSaveDir()))) {
            $recipe->save($this->getSaveDir());
        }
    }

    public function get(string $name, string $group = ""): ?Recipe {
        return $this->recipes[$group][$name] ?? null;
    }

    public function getByPath(string $name): array {
        if (empty($name)) return $this->getAll();

        $result = [];
        foreach ($this->getAll() as $group => $item) {
            if (strpos($group."/", $name."/") === 0) $result[$group] = $item;
        }
        return $result;
    }

    /**
     * @return Recipe[][]
     */
    public function getAll(): array {
        return $this->recipes;
    }

    public function remove(string $name, string $group = ""): void {
        if (!$this->exists($name, $group)) return;
        unlink($this->get($name, $group)->getFileName($this->getSaveDir()));
        unset($this->recipes[$group][$name]);
    }

    public function saveAll(): void {
        foreach ($this->getAll() as $group) {
            foreach ($group as $recipe) {
                $recipe->save($this->getSaveDir());
            }
        }
    }

    public function getNotDuplicatedName(string $name, string $group = ""): string {
        if (!$this->exists($name, $group)) return $name;
        $count = 2;
        while ($this->exists($name." (".$count.")", $group)) {
            $count++;
        }
        return $name." (".$count.")";
    }

    public function rename(string $recipeName, string $newName, string $group = ""): void {
        if (!$this->exists($recipeName, $group)) return;
        $recipe = $this->get($recipeName, $group);
        $oldPath = $recipe->getFileName($this->getSaveDir());
        $recipe->setName($newName);
        unset($this->recipes[$group][$recipeName]);
        $this->recipes[$group][$newName] = $recipe;
        rename($oldPath, $recipe->getFileName($this->getSaveDir()));
    }

    public function parseName(string $name): array {
        $names = explode("/", $name);
        return [array_pop($names), implode("/", $names)];
    }

    public function getWithLinkedRecipes(FlowItemContainer $recipe, Recipe $origin, bool $base = true): array {
        $recipeManager = Main::getRecipeManager();

        $recipes = [];
        if ($base) $recipes[] = [$origin->getGroup()."/".$origin->getName() => $origin];
        foreach ($recipe->getActions() as $action) {
            if ($action instanceof FlowItemContainer) {
                $links = $this->getWithLinkedRecipes($action, $origin, false);
                $recipes[] = $links;
                continue;
            }

            if ($action instanceof ExecuteRecipe) {
                $name = $origin->replaceVariables($action->getRecipeName());

                [$recipeName, $group] = $recipeManager->parseName($name);
                if (empty($group)) $group = $origin->getGroup();

                $recipe = $recipeManager->get($recipeName, $group);
                if ($recipe === null) $recipe = $recipeManager->get($recipeName, "");
                if ($recipe === null) continue;

                $recipes[] = $this->getWithLinkedRecipes($recipe, $recipe);
            }
        }
        $recipes = array_merge([], ...$recipes);
        return $recipes;
    }
}