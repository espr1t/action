<?php
require_once(__DIR__ . '/../config.php');
require_once(__DIR__ . '/../common.php');
require_once(__DIR__ . '/../db/brain.php');
require_once(__DIR__ . '/problem.php');
require_once(__DIR__ . '/grader.php');

class Submit {
    public $id = -1;
    public $submitted = '';
    public $graded = 0.0;
    public $userId = -1;
    public $userName = '';
    public $problemId = -1;
    public $problemName = '';
    public $source = '';
    public $language = '';
    public $results = array();
    public $exec_time = array();
    public $exec_memory = array();
    public $progress = 0;
    public $status = -1;
    public $message = '';
    public $full = true;
    public $hidden = false;
    public $ip = '';
    public $info = '';

    public static function newSubmit($user, $problemId, $language, $source, $full, $hidden = false) {
        $brain = new Brain();
        $submit = new Submit();

        $problem = Problem::get($problemId);

        // The submission doesn't have an ID until it is inserted in the database
        $submit->id = -1;

        // Mark the time of the submission
        $submit->submitted = date('Y-m-d H:i:s');
        $submit->graded = 0.0;

        // Populate the remaining submission info
        $submit->userId = $user->id;
        $submit->userName = $user->username;
        $submit->problemId = $problem->id;
        $submit->problemName = $problem->name;
        $submit->source = $source;
        $submit->language = $language;
        $submit->results = array();
        $submit->exec_time = array();
        $submit->exec_memory = array();
        $numTests = count($brain->getProblemTests($problem->id));
        for ($i = 0; $i < $numTests; $i = $i + 1) {
            $submit->results[$i] = $GLOBALS['STATUS_WAITING'];
            $submit->exec_time[$i] = 0;
            $submit->exec_memory[$i] = 0;
        }
        $submit->progress = 0;
        $submit->status = $GLOBALS['STATUS_WAITING'];
        $submit->message = '';

        $submit->full = $full;
        $submit->hidden = $hidden;

        $submit->ip = $_SERVER['REMOTE_ADDR'];
        $submit->info = '';
        return $submit;
    }

    public function reset() {
        $brain = new Brain();
        $this->results = array();
        $this->exec_time = array();
        $this->exec_memory = array();
        $numTests = count($brain->getProblemTests($this->problemId));
        for ($i = 0; $i < $numTests; $i = $i + 1) {
            $this->results[$i] = $GLOBALS['STATUS_WAITING'];
            $this->exec_time[$i] = 0;
            $this->exec_memory[$i] = 0;
        }
        $this->progress = 0;
        $this->status = $GLOBALS['STATUS_WAITING'];
        $this->message = '';
        $this->info = '';
        $brain->updateSubmit($this);
        $brain->erasePending($this->id);
        $brain->eraseLatest($this->id);
    }

    public function update() {
        $brain = new Brain();
        $brain->updateSubmit($this);
    }

    public function write() {
        $brain = new Brain();
        $this->id = $brain->addSubmit($this);
        $brain->addSource($this);
        return $this->id >= 0;
    }

    public function send() {
        // Record the request in the submission queue, if not hidden
        if (!$this->hidden) {
            $brain = new Brain();
            $brain->addPending($this);
        }

        $problem = Problem::get($this->problemId);
        if ($problem->type == 'game') {
            return $this->sendGame($problem);
        } else if ($problem->type == 'relative') {
            return $this->sendTask($problem);
        } else {
            return $this->sendTask($problem);
        }
    }

    private function sendTask($problem) {
        $updateEndpoint = $GLOBALS['WEB_ENDPOINT_UPDATE'];
        $testsEndpoint = sprintf($GLOBALS['WEB_ENDPOINT_TESTS'], $problem->folder);
        $checkerEndpoint = sprintf($GLOBALS['WEB_ENDPOINT_CHECKER'], $problem->folder);

        $brain = new Brain();
        $tests = $brain->getProblemTests($this->problemId);

        // Remove unnecessary data
        for ($i = 0; $i < count($tests); $i = $i + 1) {
            unset($tests[$i]['id']);
            unset($tests[$i]['problem']);
            unset($tests[$i]['score']);
        }

        // Convert strings to numbers where needed
        for ($i = 0; $i < count($tests); $i = $i + 1) {
            $tests[$i]['position'] = intval($tests[$i]['position']);
        }

        // Also add the md5 hash of the checker (if there is one)
        $checkerHash = '';
        $checkerPath = '';
        if ($problem->checker != '') {
            $checkerPath = sprintf('%s/%s/%s/%s', $GLOBALS['PATH_PROBLEMS'], $problem->folder,
                    $GLOBALS['PROBLEM_CHECKER_FOLDER'], $problem->checker);
            $checkerHash = md5(file_get_contents($checkerPath));
            $checkerEndpoint .= $problem->checker;
        }

        // Compile all the data, required by the grader to evaluate the solution
        $data = array(
            'id' => $this->id,
            'source' => $this->source,
            'language' => $this->language,
            'checker' => $checkerHash,
            'floats' => $problem->floats,
            'timeLimit' => $problem->timeLimit,
            'memoryLimit' => $problem->memoryLimit,
            'tests' => $tests,
            'testsEndpoint' => $testsEndpoint,
            'updateEndpoint' => $updateEndpoint,
            'checkerEndpoint' => $checkerEndpoint
        );

        $grader = new Grader();
        return $grader->evaluate($data);
    }

    private function sendGame($problem) {
        $updateEndpoint = $GLOBALS['WEB_ENDPOINT_UPDATE'];
        $testsEndpoint = sprintf($GLOBALS['WEB_ENDPOINT_TESTS'], $problem->folder);
        $testerEndpoint = sprintf($GLOBALS['WEB_ENDPOINT_TESTER'], $problem->folder);

        // Also add the md5 hash of the tester (there should be one)
        assert($problem->tester != '');

        $testerPath = sprintf('%s/%s/%s/%s', $GLOBALS['PATH_PROBLEMS'], $problem->folder,
                $GLOBALS['PROBLEM_TESTER_FOLDER'], $problem->tester);
        $testerHash = md5(file_get_contents($testerPath));
        $testerEndpoint .= $problem->tester;

        // Find all submitted solutions so far
        $brain = new Brain();

        $tests = $brain->getProblemTests($this->problemId);

        // Remove unnecessary data
        for ($i = 0; $i < count($tests); $i = $i + 1) {
            unset($tests[$i]['id']);
            unset($tests[$i]['problem']);
            unset($tests[$i]['score']);
        }

        // Now create a set of matches to be played (depending on whether the submit is full or not)
        $matches = array();

        // Partial submission - run only against author's solutions.
        if (!$this->full) {
            // Solutions
            $solutions = $brain->getProblemSolutions($problem->id);
            $solutionId = 0;
            foreach ($solutions as $solution) {
                $solutionId += 1;

                // Update match info (add if a new match)
                foreach ($tests as $test) {
                    $match = new Match($problem->id, $test['position'],
                            $this->userId, -$solutionId, $this->id, $solution['submitId']);
                    $match->update();
                    swap($match->userOne, $match->userTwo);
                    swap($match->submitOne, $match->submitTwo);
                    $match->update();
                }

                // Add in match queue for testing
                array_push($matches, array(
                    'player_one_id' => $this->userId,
                    'player_one_name' => $this->userName,
                    'player_two_id' => -$solutionId,
                    'player_two_name' => 'author',
                    'language' => $solution['language'],
                    'source' => $solution['source']
                ));
            }
        }
        // Full submission - run a game against every other competitor
        else {
            $submits = $brain->getProblemSubmits($problem->id);
            // Choose only the latest full submissions of each competitor
            $latest = array();
            foreach ($submits as $submit) {
                // Skip partial submits
                if (!boolval($submit['full']))
                    continue;
                // Skip submits by the current user
                if (intval($submit['userId']) == $this->userId)
                    continue;

                $key = 'User_' . $submit['userId'];
                if (!array_key_exists($key, $latest)) {
                    $latest[$key] = intval($submit['id']);
                } else {
                    if ($latest[$key] < intval($submit['id']))
                        $latest[$key] = intval($submit['id']);
                }
            }

            foreach ($latest as $key => $submitId) {
                $submit = Submit::get($submitId);
                if ($submit == null) {
                    error_log('Could not get submit with ID ' . $submitId);
                    continue;
                }
                // Update match info (add if a new match)
                foreach ($tests as $test) {
                    $match = new Match($problem->id, $test['position'],
                            $this->userId, $submit->userId, $this->id, $submit->id);
                    $match->update();
                    swap($match->userOne, $match->userTwo);
                    swap($match->submitOne, $match->submitTwo);
                    $match->update();
                }

                // Add in match queue for testing
                array_push($matches, array(
                    'player_one_id' => $this->userId,
                    'player_one_name' => $this->userName,
                    'player_two_id' => $submit->userId,
                    'player_two_name' => $submit->userName,
                    'language' => $submit->language,
                    'source' => $submit->source
                ));
            }
        }

        // Compile all the data, required by the grader to evaluate the game
        $data = array(
            'id' => $this->id,
            'source' => $this->source,
            'language' => $this->language,
            'matches' => $matches,
            'tester' => $testerHash,
            'floats' => false,
            'timeLimit' => $problem->timeLimit,
            'memoryLimit' => $problem->memoryLimit,
            'tests' => $tests,
            'testsEndpoint' => $testsEndpoint,
            'updateEndpoint' => $updateEndpoint,
            'testerEndpoint' => $testerEndpoint
        );

        $grader = new Grader();
        return $grader->evaluate($data);
    }

    public static function instanceFromArray($info, $source) {
        $submit = new Submit();
        $submit->id = intval(getValue($info, 'id'));
        $submit->submitted = getValue($info, 'submitted');
        $submit->graded = floatval(getValue($info, 'graded'));
        $submit->userId = intval(getValue($info, 'userId'));
        $submit->userName = getValue($info, 'userName');
        $submit->problemId = intval(getValue($info, 'problemId'));
        $submit->problemName = getValue($info, 'problemName');
        $submit->language = getValue($info, 'language');
        $submit->source = getValue($source, 'source');
        $submit->results = explode(',', getValue($info, 'results'));
        $submit->exec_time = explode(',', getValue($info, 'exec_time'));
        $submit->exec_memory = explode(',', getValue($info, 'exec_memory'));
        $submit->status = getValue($info, 'status');
        $submit->message = getValue($info, 'message');
        $submit->full = boolval(getValue($info, 'full'));
        $submit->hidden = boolval(getValue($info, 'hidden'));
        $submit->info = getValue($info, 'info');
        return $submit;
    }

    public static function get($id) {
        $brain = new Brain();
        try {
            $info = $brain->getSubmit($id);
            $source = $brain->getSource($id);
            if ($info == null || $source == null) {
                error_log('Could not get submit or source with id ' . $id . '!');
                return null;
            }
            return Submit::instanceFromArray($info, $source);
        } catch (Exception $ex) {
            error_log('Could not get submit ' . $id . '. Exception: ' . $ex->getMessage());
        }
        return null;
    }

    public static function getUserSubmits($userId, $problemId) {
        $brain = new Brain();
        $submitMaps = $brain->getUserSubmits($userId, $problemId);
        $sourcesMaps = $brain->getUserSources($userId, $problemId);
        $submits = array();
        // This can be done better than O(N^2) if need be.
        foreach ($submitMaps as $submitMap) {
            foreach ($sourcesMaps as $sourceMap) {
                if ($submitMap['id'] == $sourceMap['submitId']) {
                    array_push($submits, Submit::instanceFromArray($submitMap, $sourceMap));
                    break;
                }
            }
        }
        return $submits;
    }

    public function calcStatus() {
        // Handle the case where there are no results (i.e. no tests)
        // This is an exceptional scenario and shouldn't happen, so return INTERNAL_ERROR
        if (count($this->results) == 0)
            return $GLOBALS['STATUS_INTERNAL_ERROR'];

        $passedTests = array_filter($this->results, function($el) {return is_numeric($el);});
        // If all results are numeric, then the problem has been accepted
        if (count($passedTests) == count($this->results)) {
            // We may, however, consider it not okay, if some of the results are not full scores
            // On non-relative problems consider the solution to be okay only if it has more than 80% of the points
            // TODO: Remove the 80-percent logic altogether (make all relative problems games)
            $brain = new Brain();
            $problem = $brain->getProblem($this->problemId);
            if ($problem['type'] != 'relative') {
                if (array_sum($this->results) < count($this->results) * 8.0 / 10.0)
                    return $GLOBALS['STATUS_WRONG_ANSWER'];
            }
            return $GLOBALS['STATUS_ACCEPTED'];
        }

        $failedTests = array_filter($this->results, function($el) {return !is_numeric($el) && strlen($el) == 2;});
        // If all tests are processed (either numeric or two-letter), then the grading has been completed
        if (count($passedTests) + count($failedTests) == count($this->results))
            return array_values($failedTests)[0]; // Return the status code of the first error

        // If none of the tests are processed (either numeric or two-letter), return the status of the first test
        if (count($passedTests) + count($failedTests) == 0)
            return $this->results[0];

        // If none of the above, the solution is still being graded
        return $GLOBALS['STATUS_TESTING'];
    }

    public function calcScores() {
        $brain = new Brain();
        $tests = $brain->getProblemTests($this->problemId);

        if (count($this->results) != count($tests)) {
            error_log('Number of tests of problem ' . $this->problemId . ' differs from results in submission ' . $this->id . '!');
        }

        $scores = [];
        $maxScore = 0.0;
        for ($i = 0; $i < count($this->results); $i = $i + 1) {
            $maxScore += $tests[$i]['score'];
            // The grader assigns 0/1 value for each test of IOI- and ACM-style problems and [0, 1] real fraction of the score
            // for games and relative problems. In both cases, multiplying the score of the test by this value is correct.
            array_push($scores, (is_numeric($this->results[$i]) ? $this->results[$i] : 0.0) * $tests[$i]['score']);
        }
        return array_map(function($num) use($maxScore) {return 100.0 * $num / $maxScore;}, $scores);
    }

    public function calcScore() {
        return array_sum($this->calcScores());
    }
}

?>