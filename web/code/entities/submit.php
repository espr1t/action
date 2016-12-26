<?php
require_once(__DIR__ . '/../config.php');
require_once(__DIR__ . '/../db/brain.php');
require_once(__DIR__ . '/problem.php');
require_once(__DIR__ . '/widgets.php');
require_once(__DIR__ . '/grader.php');

class Submit {
    public $id = -1;
    public $time = '';
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

    public static function newSubmit($user, $problemId, $language, $source) {
        $brain = new Brain();
        $submit = new Submit();

        $problem = Problem::get($problemId);

        // The submission doesn't have an ID until it is inserted in the database
        $submit->id = -1;

        // Mark the time of the submission
        $submit->time = date('Y-m-d H:i:s');

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
        $brain->updateSubmit($this);
        $brain->erasePending($this);
        $brain->eraseLatest($this);
    }

    public function write() {
        $brain = new Brain();
        $this->id = $brain->addSubmit($this);
        return $this->id >= 0;
    }

    public function send() {
        // Record the request in the submission queue
        $brain = new Brain();
        $brain->addPending($this);
        $problem = Problem::get($this->problemId);

        $updateEndpoint = $GLOBALS['WEB_ENDPOINT_UPDATE'];
        $testsEndpoint = sprintf($GLOBALS['WEB_ENDPOINT_TESTS'], $problem->folder);
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

        // Compile all the data, required by the grader to evaluate the solution
        $data = array(
            'id' => $this->id,
            'source' => $this->source,
            'language' => $this->language,
            'checker' => $problem->checker,
            'tester' => $problem->tester,
            'timeLimit' => $problem->timeLimit,
            'memoryLimit' => $problem->memoryLimit,
            'tests' => $tests,
            'testsEndpoint' => $testsEndpoint,
            'updateEndpoint' => $updateEndpoint
        );

        $grader = new Grader();
        return $grader->evaluate($data);
    }

    private static function instanceFromArray($info) {
        $submit = new Submit();
        $submit->id = intval(getValue($info, 'id'));
        $submit->time = getValue($info, 'time');
        $submit->userId = intval(getValue($info, 'userId'));
        $submit->userName = getValue($info, 'userName');
        $submit->problemId = intval(getValue($info, 'problemId'));
        $submit->problemName = getValue($info, 'problemName');
        $submit->source = getValue($info, 'source');
        $submit->language = getValue($info, 'language');
        $submit->results = explode(',', getValue($info, 'results'));
        $submit->exec_time = explode(',', getValue($info, 'exec_time'));
        $submit->exec_memory = explode(',', getValue($info, 'exec_memory'));
        $submit->status = getValue($info, 'status');
        $submit->message = getValue($info, 'message');
        return $submit;
    }

    public static function get($id) {
        $brain = new Brain();
        try {
            $info = $brain->getSubmit($id);
            if ($info == null) {
                error_log('Could not get submit ' . $id . '!');
                return null;
            }
            return Submit::instanceFromArray($info);
        } catch (Exception $ex) {
            error_log('Could not get submit ' . $id . '. Exception: ' . $ex->getMessage());
        }
        return null;
    }

    public static function getUserSubmits($userId, $problemId) {
        $brain = new Brain();
        $submitMaps = $brain->getUserSubmits($userId, $problemId);
        $submits = array();
        foreach ($submitMaps as $submitMap) {
            array_push($submits, Submit::instanceFromArray($submitMap));
        }
        return $submits;
    }

    public function calcStatus() {
        $statii = $GLOBALS['STATUS_DISPLAY_NAME'];
        while ($status = current($statii)) {
            foreach ($this->results as $result) {
                if ($result == key($statii)) {
                    return $result;
                }
            }
            next($statii);
        }
        // If all tests are numeric, thus tested and scored successfully, declare the submit ACCEPTED
        return $GLOBALS['STATUS_ACCEPTED'];
    }

    public function calcScores() {
        $brain = new Brain();
        $tests = $brain->getProblemTests($this->problemId);

        if (count($this->results) != count($tests)) {
            error_log('Number of tests of problem ' . $submit->problemId . ' differs from results in submission ' . $submit->id . '!');
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