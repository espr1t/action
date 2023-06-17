<?php
require_once(__DIR__ . "/../config.php");
require_once(__DIR__ . "/../common.php");
require_once(__DIR__ . "/../db/brain.php");
require_once(__DIR__ . "/grader.php");


class Match {
    private ?int $id = null;
    private ?int $problemId = null;
    private ?int $test = null;
    private ?int $userOne = null;
    private ?int $userTwo = null;
    private ?int $submitOne = null;
    private ?int $submitTwo = null;
    private ?float $scoreOne = null;
    private ?float $scoreTwo = null;
    private ?string $message = null;
    private ?string $replayKey = null;

    public function getId(): ?int {return $this->id;}
    public function getProblemId(): ?int {return $this->problemId;}
    public function getTest(): ?int {return $this->test;}
    public function getUserOne(): ?int {return $this->userOne;}
    public function getUserTwo(): ?int {return $this->userTwo;}
    public function getSubmitOne(): ?int {return $this->submitOne;}
    public function getSubmitTwo(): ?int {return $this->submitTwo;}
    public function getScoreOne(): ?float {return $this->scoreOne;}
    public function getScoreTwo(): ?float {return $this->scoreTwo;}
    public function getMessage(): ?string {return $this->message;}
    public function getReplayKey(): ?string {return $this->replayKey;}

    public function setScoreOne(float $scoreOne): void {$this->scoreOne = $scoreOne;}
    public function setScoreTwo(float $scoreTwo): void {$this->scoreTwo = $scoreTwo;}
    public function setMessage(string $message): void {$this->message = $message;}
    public function setReplayKey(string $replayKey): void {$this->replayKey = $replayKey;}


    public function __construct(int $problemId, int $test, int $userOne, int $userTwo, int $submitOne=-1, int $submitTwo=-1) {
        $this->id = -1;
        $this->problemId = $problemId;
        $this->test = $test;
        $this->userOne = $userOne;
        $this->userTwo = $userTwo;
        $this->submitOne = $submitOne;
        $this->submitTwo = $submitTwo;
        $this->scoreOne = 0.0;
        $this->scoreTwo = 0.0;
        $this->message = "";
        $this->replayKey = "";
    }

    private static function instanceFromArray(array $info): Match {
        $match = new Match(-1, -1, -1, -1);
        $match->id = getIntValue($info, "id");
        $match->problemId = getIntValue($info, "problemId");
        $match->test = getIntValue($info, "test");
        $match->userOne = getIntValue($info, "userOne");
        $match->userTwo = getIntValue($info, "userTwo");
        $match->submitOne = getIntValue($info, "submitOne");
        $match->submitTwo = getIntValue($info, "submitTwo");
        $match->scoreOne = getFloatValue($info, "scoreOne");
        $match->scoreTwo = getFloatValue($info, "scoreTwo");
        $match->message = getStringValue($info, "message");
        $match->replayKey = getStringValue($info, "replayKey");
        return $match;
    }

    static public function getById(int $matchId): ?Match {
        $info = Brain::getMatchById($matchId);
        return $info === null ? null : Match::instanceFromArray($info);
    }

    static public function get(int $problemId, int $test, int $userOne, int $userTwo): ?Match {
        $info = Brain::getMatch($problemId, $test, $userOne, $userTwo);
        return $info === null ? null : Match::instanceFromArray($info);
    }

    /** @return Match[] */
    static public function getGameMatches(int $problemId, int $userId = -1): array {
        return array_map(
            function ($entry) {
                return Match::instanceFromArray($entry);
            }, Brain::getGameMatches($problemId, $userId)
        );
    }

    public function update(): bool {
        return Brain::updateMatch($this);
    }

    public function replay(): bool {
        // Don't support replays of submits against the author's solution as it complicates things
        if ($this->getUserOne() <= 0 || $this->getUserTwo() <= 0)
            return false;

        $problem = Problem::get($this->getProblemId());
        $test = Test::getProblemTests($this->getProblemId())[$this->test];
        $submitOne = Submit::get($this->getSubmitOne());
        $submitTwo = Submit::get($this->getSubmitTwo());
        $gradingData = array(
            "id" => $submitOne->getId(),
            "key" => $submitOne->getKey(),
            "source" => $submitOne->getSource(),
            "language" => $submitOne->getLanguage(),
            "floats" => $problem->getFloats(),
            "problemType" => $problem->getType(),
            "timeLimit" => $problem->getTimeLimit(),
            "memoryLimit" => $problem->getMemoryLimit(),
            "tests" => [
                array(
                    "position" => $test->getPosition(),
                    "inpFile" => $test->getInpFile(),
                    "inpHash" => $test->getInpHash(),
                    "solFile" => $test->getSolFile(),
                    "solHash" => $test->getSolHash(),
                )
            ],
            "testsEndpoint" => $problem->getTestsEndpoint(),
            "updateEndpoint" => $problem->getUpdateEndpoint(),
            "matches" => [
                array(
                    "player_one_id" => $submitOne->getUserId(),
                    "player_one_name" => $submitOne->getUserName(),
                    "player_two_id" => $submitTwo->getUserId(),
                    "player_two_name" => $submitTwo->getUserName(),
                    "language" => $submitTwo->getLanguage(),
                    "source" => $submitTwo->getSource()
                )
            ]
        );
        if ($problem->getTester()) {
            $gradingData["tester"] = md5(file_get_contents($problem->getTesterPath()));
            $gradingData["testerEndpoint"] = $problem->getTesterEndpoint();
        }

        $this->setScoreOne(0);
        $this->setScoreTwo(0);
        $this->setMessage("");
        $this->setReplayKey("");
        $this->update();

        // Record invoking this test run in the logs
        write_log($GLOBALS["LOG_SUBMITS"], "Replaying match {$this->getId()}...");
        $response = Grader::call($GLOBALS["GRADER_ENDPOINT_EVALUATE"], $gradingData, "POST");
        return $response["status"] == 200;
    }
}

?>