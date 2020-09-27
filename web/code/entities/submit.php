<?php
require_once(__DIR__ . '/../config.php');
require_once(__DIR__ . '/../common.php');
require_once(__DIR__ . '/../db/brain.php');
require_once(__DIR__ . '/problem.php');
require_once(__DIR__ . '/queue.php');

class Submit {
    public $id = -1;
    public $submitted = '';
    public $gradingStart = 0.0;
    public $gradingFinish = 0.0;
    public $userId = -1;
    public $userName = '';
    public $problemId = -1;
    public $problemName = '';
    public $source = '';
    public $language = '';
    public $results = array();
    public $execTime = array();
    public $execMemory = array();
    public $status = '';
    public $message = '';
    public $full = true;
    public $ip = '';
    public $info = '';
    public $replayId = '';

    private static function instanceFromArray($info) {
        $submit = new Submit();
        $submit->id = intval(getValue($info, 'id'));
        $submit->submitted = getValue($info, 'submitted');
        $submit->gradingStart = floatval(getValue($info, 'gradingStart'));
        $submit->gradingFinish = floatval(getValue($info, 'gradingFinish'));
        $submit->userId = intval(getValue($info, 'userId'));
        $submit->userName = getValue($info, 'userName');
        $submit->problemId = intval(getValue($info, 'problemId'));
        $submit->problemName = getValue($info, 'problemName');
        $submit->language = getValue($info, 'language');
        $submit->results = explode(',', getValue($info, 'results'));
        $submit->execTime = explode(',', getValue($info, 'execTime'));
        $submit->execMemory = explode(',', getValue($info, 'execMemory'));
        $submit->status = getValue($info, 'status');
        $submit->message = getValue($info, 'message');
        $submit->full = boolval(getValue($info, 'full'));
        $submit->info = getValue($info, 'info');
        $submit->replayId = getValue($info, 'replayId');
        return $submit;
    }

    public static function get($id) {
        $brain = new Brain();
        try {
            $info = $brain->getSubmit($id);
            if ($info == null) {
                error_log('Could not get submit or source with id ' . $id . '!');
                return null;
            }
            return Submit::instanceFromArray($info);
        } catch (Exception $ex) {
            error_log('Could not get submit ' . $id . '. Exception: ' . $ex->getMessage());
        }
        return null;
    }

    private static function init($submit) {
        $brain = new Brain();
        $numTests = count($brain->getProblemTests($submit->problemId));
        $submit->results = array_fill(0, $numTests, $GLOBALS['STATUS_WAITING']);
        $submit->execTime = array_fill(0, $numTests, 0);
        $submit->execMemory = array_fill(0, $numTests, 0);
        $submit->status = $GLOBALS['STATUS_WAITING'];
        $submit->gradingStart = microtime(true);
        $submit->gradingFinish = 0.0;
        $submit->message = '';
        $submit->info = '';
        $submit->replayId = '';
    }

    public static function create($user, $problemId, $language, $source, $full) {
        $submit = new Submit();
        $problem = Problem::get($problemId);

        // The submission doesn't have an ID until it is inserted in the database
        $submit->id = -1;

        // Populate the static submission info
        $submit->userId = $user->id;
        $submit->userName = $user->username;
        $submit->problemId = $problem->id;
        $submit->problemName = $problem->name;
        $submit->source = $source;
        $submit->language = $language;
        $submit->full = $full;

        // Initialize the volatile submission info
        Submit::init($submit);

        // Mark the time and IP of the submission
        $submit->ip = getUserIP();
        $submit->submitted = date('Y-m-d H:i:s');

        return $submit;
    }

    public function getSource() {
        if ($this->source == '') {
            $brain = new Brain();
            $source = $brain->getSource($this->id);
            $this->source = strval(getValue($source, 'source'));
        }
        return $this->source;
    }

    private function test() {
        // TODO: Run the update in a separate process so the user doesn't have to wait for the update
        // Finally force an update of the queue so the submit may be sent for grading immediately
        Queue::update();
    }

    public function update() {
        $brain = new Brain();
        $brain->updateSubmit($this);
    }

    public function regrade($forceUpdate=true) {
        Submit::init($this);
        $this->update();
        if ($forceUpdate) {
            $this->test();
        }
    }

    public function add($forceUpdate=true) {
        $brain = new Brain();
        $this->id = $brain->addSubmit($this);
        if ($this->id >= 0) {
            $brain->addSource($this);
            if ($forceUpdate) {
                $this->test();
            }
            return true;
        }
        return false;
    }

    public function getKey() {
        return sprintf("%d@%f", $this->id, $this->gradingStart);
    }

    public function getGradingData() {
        // Get problem, tests, and matches info
        $problem = Problem::get($this->problemId);

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

        $matches = null;
        if ($problem->type == 'game') {
            // Each game needs to have a tester
            assert($problem->tester != '');
            $matches = $this->getGameMatches($problem, $tests);
        }

        // Compile all the data, required by the grader to evaluate the solution
        $updateEndpoint = $GLOBALS['WEB_ENDPOINT_UPDATE'];
        $testsEndpoint = sprintf($GLOBALS['WEB_ENDPOINT_TESTS'], $problem->folder);

        $data = array(
            'id' => $this->id,
            'key' => $this->getKey(),
            'source' => $this->getSource(),
            'language' => $this->language,
            'floats' => $problem->floats,
            'problemType' => $problem->type,
            'timeLimit' => $problem->timeLimit,
            'memoryLimit' => $problem->memoryLimit,
            'tests' => $tests,
            'testsEndpoint' => $testsEndpoint,
            'updateEndpoint' => $updateEndpoint
        );

        if ($matches != null) {
            $data['matches'] = $matches;
        }

        // Also add the checker or tester if they are present
        if ($problem->checker != '') {
            $checkerPath = sprintf('%s/%s/%s/%s', $GLOBALS['PATH_PROBLEMS'], $problem->folder,
                $GLOBALS['PROBLEM_CHECKER_FOLDER'], $problem->checker);
            $data['checker'] = md5(file_get_contents($checkerPath));
            $data['checkerEndpoint'] = sprintf($GLOBALS['WEB_ENDPOINT_CHECKER'], $problem->folder) . $problem->checker;
        }
        if ($problem->tester != '') {
            $testerPath = sprintf('%s/%s/%s/%s', $GLOBALS['PATH_PROBLEMS'], $problem->folder,
                $GLOBALS['PROBLEM_TESTER_FOLDER'], $problem->tester);
            $data['tester'] = md5(file_get_contents($testerPath));
            $data['testerEndpoint'] = sprintf($GLOBALS['WEB_ENDPOINT_TESTER'], $problem->folder) . $problem->tester;
        }
        return $data;
    }

    private function getGameMatches($problem, $tests) {
        $brain = new Brain();

        // Create a set of matches to be played (depending on whether the submit is full or not)
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
                    'source' => $submit->getSource()
                ));
            }
        }
        return $matches;
    }

    private static function getObjects($objectsAsArrays) {
        return array_map(
            function ($entry) {
                return Submit::instanceFromArray($entry, array('source' => ''));
            }, $objectsAsArrays
        );
    }

    public static function getProblemSubmits($problemId, $status = 'all') {
        $brain = new Brain();
        return self::getObjects($brain->getProblemSubmits($problemId, $status));
    }

    public static function getUserSubmits($userId, $problemId = -1, $status = 'all') {
        $brain = new Brain();
        return self::getObjects($brain->getUserSubmits($userId, $problemId, $status));
    }

    public static function getPendingSubmits() {
        $brain = new Brain();
        return self::getObjects($brain->getPendingSubmits());
    }

    public static function getLatestSubmits() {
        $brain = new Brain();
        return self::getObjects($brain->getLatestSubmits());
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
            // TODO: This may be a problem later on. Interactive problems have their scores not normalized,
            // thus may pass this easily, however if their scores are in [0, 1] they will fall here.
            // Fix this by introducing 'scoring' flag in the problems (absolute/relative).
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
            error_log(sprintf('Number of tests of problem %d differs from results in submission %d!', $this->problemId, $this->id));
            return [];
        }

        $scores = [];
        $maxScore = 0.0;
        for ($i = 0; $i < count($tests); $i++) {
            $maxScore += $tests[$i]['score'];
            if ($tests[$i]['position'] < count($this->results)) {
                $result = $this->results[$tests[$i]['position']];
                // The grader assigns 0/1 value for each test of IOI- and ACM-style problems and [0, 1] real fraction of the score
                // for games and relative problems. In both cases, multiplying the score of the test by this value is correct.
                array_push($scores, (is_numeric($result) ? $result : 0.0) * $tests[$i]['score']);
            }
        }
        if ($maxScore > 0.0) {
            $scores = array_map(function($num) use($maxScore) {return 100.0 * $num / $maxScore;}, $scores);
        }
        return $scores;
    }

    public function calcScore() {
        return array_sum($this->calcScores());
    }

    public function calcProgress() {
        if (count($this->results) == 0)
            return 0.0;
        $executed = 0;
        foreach ($this->results as $result) {
            if (is_numeric($result) || strlen($result) == 2) {
                $executed++;
            }
        }
        return 1.0 * $executed / count($this->results);
    }
}

?>