<?php
require_once(__DIR__ . "/../config.php");
require_once(__DIR__ . "/../common.php");
require_once(__DIR__ . "/../db/brain.php");

class Test {
    private ?int $id = null;
    private ?int $problemId = null;
    private ?int $position = null;
    private ?string $inpFile = null;
    private ?string $inpHash = null;
    private ?string $solFile = null;
    private ?string $solHash = null;
    private ?int $score = null;

    public function getId(): ?int {return $this->id;}
    public function getProblem(): ?int {return $this->problemId;}
    public function getPosition(): ?int {return $this->position;}
    public function getInpFile(): ?string {return $this->inpFile;}
    public function getInpHash(): ?string {return $this->inpHash;}
    public function getSolFile(): ?string {return $this->solFile;}
    public function getSolHash(): ?string {return $this->solHash;}
    public function getScore(): ?int {return $this->score;}


    public function __construct() {
        $this->id = -1;
        $this->problemId = -1;
        $this->position = -1;
        $this->inpFile = "";
        $this->inpHash = "";
        $this->solFile = "";
        $this->solHash = "";
        $this->score = 0;
    }

    public static function instanceFromArray(array $info): Test {
        $test = new Test();
        $test->id = getIntValue($info, "id");
        $test->problemId = getIntValue($info, "problemId");
        $test->position = getIntValue($info, "position");
        $test->inpFile = getStringValue($info, "inpFile");
        $test->inpHash = getStringValue($info, "inpHash");
        $test->solFile = getStringValue($info, "solFile");
        $test->solHash = getStringValue($info, "solHash");
        $test->score = getIntValue($info, "score");
        return $test;
    }

    /** @return Test[] */
    public static function getProblemTests(?int $problemId): array {
        # In case it is a new problem the ID will be null, thus simply return an empty list
        if ($problemId == null) {
            return array();
        }
        return array_map(
            function ($entry) {
                return Test::instanceFromArray($entry);
            }, Brain::getProblemTests($problemId)
        );
    }
}

?>