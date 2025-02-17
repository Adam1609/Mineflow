<?php


namespace aieuo\mineflow\flowItem\base;


use aieuo\mineflow\exception\InvalidFlowValueException;
use aieuo\mineflow\recipe\Recipe;
use aieuo\mineflow\utils\Scoreboard;

interface ScoreboardFlowItem {

    public function getScoreboardVariableName(string $name = ""): string;

    public function setScoreboardVariableName(string $scoreboard, string $name = ""): void;

    /**
     * @param Recipe $origin
     * @param string $name
     * @return Scoreboard
     * @throws InvalidFlowValueException
     */
    public function getScoreboard(Recipe $origin, string $name = ""): Scoreboard;

    /**
     * @param Scoreboard|null $board
     * @deprecated merge this into getScoreboard()
     */
    public function throwIfInvalidScoreboard(?Scoreboard $board): void;
}