<?php


namespace aieuo\mineflow\flowItem\base;


use aieuo\mineflow\exception\InvalidFlowValueException;
use aieuo\mineflow\recipe\Recipe;
use aieuo\mineflow\utils\Language;
use aieuo\mineflow\variable\object\BlockObjectVariable;
use pocketmine\block\Block;

trait BlockFlowItemTrait {

    /* @var string[] */
    private $blockVariableNames = [];

    public function getBlockVariableName(string $name = ""): string {
        return $this->blockVariableNames[$name] ?? "";
    }

    public function setBlockVariableName(string $block, string $name = ""): void {
        $this->blockVariableNames[$name] = $block;
    }

    /**
     * @param Recipe $origin
     * @param string $name
     * @return Block
     * @throws InvalidFlowValueException
     */
    public function getBlock(Recipe $origin, string $name = ""): Block {
        $block = $origin->replaceVariables($rawName = $this->getBlockVariableName($name));

        $variable = $origin->getVariable($block);
        if ($variable instanceof BlockObjectVariable and ($block = $variable->getBlock()) instanceof Block) {
            return $block;
        }

        throw new InvalidFlowValueException($this->getName(), Language::get("action.target.not.valid", [["action.target.require.block"], $rawName]));
    }

    /**
     * @param Block|null $block
     * @deprecated merge this into getBlock()
     */
    public function throwIfInvalidBlock(?Block $block): void {
        if (!($block instanceof Block)) {
            throw new InvalidFlowValueException($this->getName(), Language::get("action.target.not.valid", [["action.target.require.block"], $this->getBlockVariableName()]));
        }
    }
}