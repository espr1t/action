<?php
require_once(__DIR__ . "/../config.php");
require_once(__DIR__ . "/../common.php");
require_once(__DIR__ . "/../db/brain.php");
require_once(__DIR__ . "/problem.php");
require_once(__DIR__ . "/queue.php");
require_once(__DIR__ . "/test.php");


class Submit {
    private ?int $id = null;
    private ?string $submitted = null;
    private ?float $gradingStart = null;
    private ?float $gradingFinish = null;
    private ?int $userId = null;
    private ?string $userName = null;
    private ?int $problemId = null;
    private ?string $problemName = null;
    private ?string $source = null;
    private ?string $language = null;
    private ?array $results = null;
    private ?array $execTime = null;
    private ?array $execMemory = null;
    private ?string $status = null;
    private ?string $message = null;
    private ?string $info = null;
    private ?bool $full = null;
    private ?string $ip = null;
    private ?string $replayKey = null;

    public function getId(): ?int {return $this->id;}
    public function getSubmitted(): ?string {return $this->submitted;}
    public function getGradingStart(): ?float {return $this->gradingStart;}
    public function getGradingFinish(): ?float {return $this->gradingFinish;}
    public function getUserId(): ?int {return $this->userId;}
    public function getUserName(): ?string {return $this->userName;}
    public function getProblemId(): ?int {return $this->problemId;}
    public function getProblemName(): ?string {return $this->problemName;}
    public function getSource(): ?string {
        if ($this->source === null) {
            $source = Brain::getSource($this->getId());
            $this->source = getStringValue($source, "source");
        }
        return $this->source;
    }
    public function getLanguage(): ?string {return $this->language;}
    /** @return string[] */
    public function getResults(): ?array {return $this->results;}
    /** @return float[] */
    public function getExecTime(): ?array {return $this->execTime;}
    /** @return float[] */
    public function getExecMemory(): ?array {return $this->execMemory;}
    public function getStatus(): ?string {return $this->status;}
    public function getMessage(): ?string {return $this->message;}
    public function getInfo(): ?string {return $this->info;}
    public function getFull(): ?bool {return $this->full;}
    public function getIp(): ?string {return $this->ip;}
    public function getReplayKey(): ?string {return $this->replayKey;}

    public function setResult(int $position, string $result): void {$this->results[$position] = $result;}
    public function setExecTime(int $position, float $value): void {$this->execTime[$position] = $value;}
    public function setExecMemory(int $position, float $value): void {$this->execMemory[$position] = $value;}
    public function setInfo(string $info) : void {$this->info = $info;}
    public function setMessage(string $message) : void {$this->message = $message;}
    public function setStatus(string $status) : void {$this->status = $status;}
    public function setSource(string $source) : void {$this->source = $source;}
    public function setReplayKey(string $replayKey) : void {$this->replayKey = $replayKey;}
    public function setGradingFinish(float $gradingFinish) : void {$this->gradingFinish = $gradingFinish;}

    private static function instanceFromArray(array $info): Submit {
        $submit = new Submit();
        $submit->id = getIntValue($info, "id");
        $submit->submitted = getStringValue($info, "submitted");
        $submit->gradingStart = getFloatValue($info, "gradingStart");
        $submit->gradingFinish = getFloatValue($info, "gradingFinish");
        $submit->userId = getIntValue($info, "userId");
        $submit->userName = getStringValue($info, "userName");
        $submit->problemId = getIntValue($info, "problemId");
        $submit->problemName = getStringValue($info, "problemName");
        $submit->language = getStringValue($info, "language");
        $submit->results = getStringArray($info, "results");
        $submit->execTime = getFloatArray($info, "execTime");
        $submit->execMemory = getFloatArray($info, "execMemory");
        $submit->status = getStringValue($info, "status");
        $submit->message = getStringValue($info, "message");
        $submit->full = getBoolValue($info, "full");
        $submit->ip = getStringValue($info, "ip");
        $submit->info = getStringValue($info, "info");
        $submit->replayKey = getStringValue($info, "replayKey");
        return $submit;
    }

    public static function get(int $id): ?Submit {
        if (!is_int($id)) {
            error_log("ERROR: Could not get submit with id {$id}!");
            return null;
        }
        try {
            $info = Brain::getSubmit($id);
            if ($info === null) {
                error_log("ERROR: Could not get submit with id {$id}!");
                return null;
            }
            return Submit::instanceFromArray($info);
        } catch (Exception $ex) {
            error_log("ERROR: Could not get submit {$id}. Exception: {$ex->getMessage()}");
        }
        return null;
    }

    private static function init(Submit $submit): void {
        $numTests = count(Brain::getProblemTests($submit->problemId));
        $submit->results = array_fill(0, $numTests, $GLOBALS["STATUS_WAITING"]);
        $submit->execTime = array_fill(0, $numTests, 0.0);
        $submit->execMemory = array_fill(0, $numTests, 0.0);
        $submit->status = $GLOBALS["STATUS_WAITING"];
        $submit->gradingStart = microtime(true);
        $submit->gradingFinish = 0.0;
        $submit->message = "";
        $submit->info = "";
        $submit->replayKey = "";
    }

    public static function create(User $user, int $problemId, string $language, string $source, bool $full): Submit {
        $submit = new Submit();
        $problem = Problem::get($problemId);

        // The submission doesn't have an ID until it is inserted in the database
        $submit->id = -1;

        // Populate the static submission info
        $submit->userId = $user->getId();
        $submit->userName = $user->getUsername();
        $submit->problemId = $problem->getId();
        $submit->problemName = $problem->getName();
        $submit->source = $source;
        $submit->language = $language;
        $submit->full = $full;

        // Initialize the volatile submission info
        Submit::init($submit);

        // Mark the time and IP of the submission
        $submit->ip = getUserIP();
        $submit->submitted = date("Y-m-d H:i:s");

        return $submit;
    }

    private function test(): void {
        // TODO: Run the update in a separate process so the user doesn't have to wait for the update
        // Force an update of the queue so the submit may be sent for grading immediately
        Queue::update();
    }

    public function update(): bool {
        return Brain::updateSubmit($this);
    }

    public function regrade(bool $forceUpdate=true): void {
        Submit::init($this);
        $this->update();
        if ($forceUpdate) {
            $this->test();
        }
    }

    public function add(bool $forceUpdate=true): bool {
        $this->id = Brain::addSubmit($this);
        if ($this->getId() <= 0)
            return false;
        Brain::addSource($this);
        if ($forceUpdate) {
            $this->test();
        }
        return true;
    }

    public function getKey(): string {
        return sprintf("%d@%.3f", $this->id, $this->gradingStart);
    }

    public function getGradingData(): array {
        // Get problem, tests, and matches info
        $problem = Problem::get($this->getProblemId());

        // Compile all the data, required by the grader to evaluate the solution
        $data = array(
            "id" => $this->getId(),
            "key" => $this->getKey(),
            "source" => $this->getSource(),
            "language" => $this->getLanguage(),
            "floats" => $problem->getFloats(),
            "problemType" => $problem->getType(),
            "timeLimit" => $problem->getTimeLimit(),
            "memoryLimit" => $problem->getMemoryLimit(),
            "tests" => array_map(
                function (Test $test): array {
                    return array(
                        "position" => $test->getPosition(),
                        "inpFile" => $test->getInpFile(),
                        "inpHash" => $test->getInpHash(),
                        "solFile" => $test->getSolFile(),
                        "solHash" => $test->getSolHash(),
                    );
                }, Test::getProblemTests($problem->getId())
            ),
            "testsEndpoint" => $problem->getTestsEndpoint(),
            "updateEndpoint" => $problem->getUpdateEndpoint()
        );

        // In case of a game, also add "matches" information to the submit data
        if ($problem->getType() == "game") {
            // Each game needs to have a tester, double check this before sending it along
            if (!$problem->getTester()) {
                error_log("ERROR: Problem {$problem->getId()} is a game, but doesn't have a tester! Aborting submit.");
                exit(0);
            }
            $data["matches"] = $this->getGameMatches($problem);
        }

        // Also add the checker or tester if they are present
        if ($problem->getChecker()) {
            $data["checker"] = md5(file_get_contents($problem->getCheckerPath()));
            $data["checkerEndpoint"] = $problem->getCheckerEndpoint();
        }
        if ($problem->getTester()) {
            $data["tester"] = md5(file_get_contents($problem->getTesterPath()));
            $data["testerEndpoint"] = $problem->getTesterEndpoint();
        }
        return $data;
    }

    private function getGameMatches(Problem $problem): array {
        // Create a set of matches to be played (depending on whether the submit is full or not)
        $matches = array();
        $tests = Test::getProblemTests($problem->getId());

        // Partial submission - run only against author's solutions.
        if (!$this->full) {
            // Solutions
            $solutions = Brain::getProblemSolutions($problem->getId());
            $solutionId = 0;
            foreach ($solutions as $solution) {
                $solutionId += 1;
                $solutionSubmitId = getIntValue($solution, "submitId");
                $solutionLanguage = getStringValue($solution, "language");
                $solutionSource = getStringValue($solution, "source");

                // Update match info (add if a new match)
                foreach ($tests as $test) {
                    $match1 = new Match($problem->getId(), $test->getPosition(),
                        $this->getUserId(), -$solutionId, $this->getId(), $solutionSubmitId);
                    $match1->update();
                    $match2 = new Match($problem->getId(), $test->getPosition(),
                        -$solutionId, $this->getUserId(), $solutionSubmitId, $this->getId());
                    $match2->update();
                }

                // Add in match queue for testing
                array_push($matches, array(
                    "player_one_id" => $this->getUserId(),
                    "player_one_name" => $this->getUserName(),
                    "player_two_id" => -$solutionId,
                    "player_two_name" => "author",
                    "language" => $solutionLanguage,
                    "source" => $solutionSource
                ));
            }
        }
        // Full submission - run a game against every other competitor
        else {
            $submits = Submit::getProblemSubmits($problem->getId());
            // Choose only the latest full submissions of each competitor
            $latest = array();
            foreach ($submits as $submit) {
                // Skip partial submits
                if (!$submit->getFull())
                    continue;
                // Skip submits by the current user
                if ($submit->getUserId() == $this->getUserId())
                    continue;

                $key = "User_{$submit->getUserId()}";
                if (!array_key_exists($key, $latest)) {
                    $latest[$key] = $submit;
                } else {
                    if ($latest[$key]->getId() < $submit->getId())
                        $latest[$key] = $submit;
                }
            }

            foreach ($latest as $key => $submit) {
                $numTests = count($tests);
                // Update match info (add if a new match)
                foreach ($tests as $test) {
                    $match1 = new Match($problem->getId(), $test->getPosition(),
                        $this->getUserId(), $submit->getUserId(), $this->getId(), $submit->getId());
                    $match1->update();
                    $match2 = new Match($problem->getId(), $test->getPosition(),
                        $submit->getUserId(), $this->getUserId(), $submit->getId(), $this->getId());
                    $match2->update();
                }

                // Add in match queue for testing
                array_push($matches, array(
                    "player_one_id" => $this->getUserId(),
                    "player_one_name" => $this->getUserName(),
                    "player_two_id" => $submit->getUserId(),
                    "player_two_name" => $submit->getUserName(),
                    "language" => $submit->getLanguage(),
                    "source" => $submit->getSource()
                ));
            }
        }
        return $matches;
    }

    /** @return Submit[] */
    private static function createSubmitObjects($objectsAsArrays): array {
        return array_map(
            function ($entry) {
                return Submit::instanceFromArray($entry);
            }, $objectsAsArrays
        );
    }

    /**
     * @param int $userId
     * @param int $problemId
     * @param string $status
     * @param bool $withSources
     * @return Submit[]
     */
    public static function getAllSubmits(int $userId=-1, int $problemId=-1, string $status="all", bool $withSources=false): array {
        $submits = Submit::createSubmitObjects(Brain::getAllSubmits($userId, $problemId, $status));
        if ($withSources) {
            if ($status != "all") {
                $errorMessage = "Cannot invoke getAllSubmits() having withSources=true when status is not \"all\".";
                error_log($errorMessage);
                print($errorMessage);
                exit();
            }

            $sources = Brain::getAllSources($userId, $problemId);
            if (count($submits) != count($sources)) {
                error_log("ERROR: Number of submits is different than number of sources!");
                return $submits;
            }
            for ($i = 0; $i < count($submits); $i++) {
                if ($submits[$i]->getId() != $sources[$i]["submitId"]) {
                    error_log("ERROR: Mismatch of submit[$i] and source[$i] IDs!");
                    return $submits;
                }
                $submits[$i]->setSource($sources[$i]["source"]);
            }
        }
        return $submits;
    }

    /** @return Submit[] */
    public static function getFirstACSubmits(): array {
        return Submit::createSubmitObjects(Brain::getFirstACSubmits());
    }

    /** @return Submit[] */
    public static function getUserSubmits(int $userId, bool $withSources=false): array {
        return Submit::getAllSubmits($userId, -1, "all", $withSources);
    }

    /** @return Submit[] */
    public static function getProblemSubmits(int $problemId, bool $withSources=false): array {
        return Submit::getAllSubmits(-1, $problemId, "all", $withSources);
    }

    /** @return Submit[] */
    public static function getPendingSubmits(): array {
        return Submit::createSubmitObjects(Brain::getPendingSubmits());
    }

    /** @return Submit[] */
    public static function getLatestSubmits(): array {
        return Submit::createSubmitObjects(Brain::getLatestSubmits());
    }

    public function calcStatus(): string {
        // Handle the case where there are no results (i.e. no tests)
        // This is an exceptional scenario and shouldn't happen, so return INTERNAL_ERROR
        if (count($this->getResults()) == 0)
            return $GLOBALS["STATUS_INTERNAL_ERROR"];

        $passedTests = array_filter($this->getResults(), function($el) {return is_numeric($el);});
        // If all results are numeric, then the problem has been accepted
        if (count($passedTests) == count($this->getResults())) {
            // We may, however, consider it not okay, if some of the results are not full scores
            // On non-relative problems consider the solution to be okay only if it has more than 80% of the points
            // TODO: Remove the 80-percent logic altogether (make all relative problems games)
            $problem = Problem::get($this->getProblemId());
            // TODO: This may be a problem later on. Relative problems have their scores not normalized,
            //       thus may pass this easily, however if their scores are in [0, 1] they will fail here.
            //       Fix this by introducing 'scoring' flag in the problems (absolute/relative).
            if ($problem->getType() != "relative") {
                if (array_sum($this->getResults()) < count($this->getResults()) * 8.0 / 10.0)
                    return $GLOBALS["STATUS_WRONG_ANSWER"];
            }
            return $GLOBALS["STATUS_ACCEPTED"];
        }

        $failedTests = array_filter($this->getResults(), function($el) {return !is_numeric($el) && strlen($el) == 2;});
        // If all tests are processed (either numeric or two-letter), then the grading has been completed
        if (count($passedTests) + count($failedTests) == count($this->getResults()))
            return array_values($failedTests)[0]; // Return the status code of the first error

        // If none of the tests are processed (either numeric or two-letter), return the status of the first test
        if (count($passedTests) + count($failedTests) == 0)
            return $this->getResults()[0];

        // If none of the above, the solution is still being graded
        return $GLOBALS["STATUS_TESTING"];
    }

    /** @return float[] */
    public function calcScores(): array {
        $tests = Test::getProblemTests($this->getProblemId());

        if (count($this->getResults()) != count($tests)) {
            error_log("ERROR: Number of tests of problem {$this->getProblemId()} differs from results in submission {$this->getId()}!");
            return [];
        }

        $scores = [];
        $testScoreSum = 0.0;
        foreach ($tests as $test) {
            $testScoreSum += $test->getScore();
            if (count($this->getResults()) > $test->getPosition()) {
                $result = $this->getResults()[$test->getPosition()];
                // The grader assigns 0/1 value for each test of IOI- and ACM-style problems and [0, 1] real fraction of the score
                // for games and most relative problems. In both cases, multiplying the score of the test by this value is correct.
                // The only exceptions are some relative problems, where the actual score is calculated on the front-end and the
                // raw score is returned. These are handled in games.php.
                array_push($scores, (is_numeric($result) ? $result : 0.0) * $test->getScore());
            }
        }
        // Scale the scores such that their sum is in [0, 100].
        if ($testScoreSum > 0.0) {
            $scores = array_map(function($num) use($testScoreSum) {return 100.0 * $num / $testScoreSum;}, $scores);
        }
        return $scores;
    }

    public function calcScore(): float {
        return array_sum($this->calcScores());
    }

    public function calcProgress(): float {
        if (count($this->getResults()) == 0)
            return 0.0;
        $completed = 0;
        foreach ($this->getResults() as $result) {
            if (is_numeric($result) || strlen($result) == 2) {
                $completed++;
            }
        }
        return 1.0 * $completed / count($this->getResults());
    }
}

?>