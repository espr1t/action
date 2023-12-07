<?php
require_once(__DIR__ . "/../config.php");
require_once(__DIR__ . "/../common.php");
require_once(__DIR__ . "/../db/brain.php");

class Solution {
    private ?int $problemId = null;
    private ?int $submitId = null;
    private ?string $name = null;
    private ?string $source = null;
    private ?string $language = null;

    public function getProblemId(): ?int {return $this->problemId;}
    public function getName(): ?string {return $this->name;}
    public function getSubmitId(): ?int {return $this->submitId;}
    public function getSource(): ?string {return $this->source;}
    public function getLanguage(): ?string {return $this->language;}


    public function __construct(int $problemId, string $name, int $submitId, string $source, string $language) {
        $this->problemId = $problemId;
        $this->name = $name;
        $this->submitId = $submitId;
        $this->source = $source;
        $this->language = $language;
    }

    public static function instanceFromArray(array $info): Solution {
        $solution = new Solution(-1, "", -1, "", "");
        $solution->problemId = getIntValue($info, "problemId");
        $solution->name = getStringValue($info, "name");
        $solution->submitId = getIntValue($info, "submitId");
        $solution->source = getStringValue($info, "source");
        $solution->language = getStringValue($info, "language");
        return $solution;
    }

    /** @return Solution[] */
    public static function getProblemSolutions(?int $problemId): array {
        # In case it is a new problem the ID will be null, thus simply return an empty list
        if ($problemId == null) {
            return array();
        }
        return array_map(
            function ($entry) {
                return Solution::instanceFromArray($entry);
            }, Brain::getProblemSolutions($problemId)
        );
    }

}

?>