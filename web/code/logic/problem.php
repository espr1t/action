<?php
require_once('config.php');
require_once('widgets.php');

class Problem {
    public $id = -1;
    public $name = '';
    public $author = '';
    public $folder = '';
    public $timeLimit = -1;
    public $memoryLimit = -1;
    public $type = '';
    public $difficulty = '';
    public $tags = array();
    public $origin = '';
    public $checker = '';
    public $executor = '';
    public $addedBy = '';

    public function __construct() {
        $addedBy = $GLOBALS['user']->username;
    }

    private function arrayFromInstance() {
        return array(
            'id' => $this->id,
            'name' => $this->name,
            'author' => $this->author,
            'folder' => $this->folder,
            'timeLimit' => $this->timeLimit,
            'memoryLimit' => $this->memoryLimit,
            'type' => $this->type,
            'difficulty' => $this->difficulty,
            'tags' => $this->tags,
            'origin' => $this->origin,
            'checker' => $this->checker,
            'executor' => $this->executor,
            'addedBy' => $this->addedBy
        );
    }

    private static function instanceFromArray($info) {
        $problem = new Problem;
        $problem->id = getValue($info, 'id');
        $problem->name = getValue($info, 'name');
        $problem->author = getValue($info, 'author');
        $problem->folder = getValue($info, 'folder');
        $problem->timeLimit = getValue($info, 'timeLimit');
        $problem->memoryLimit = getValue($info, 'memoryLimit');
        $problem->type = getValue($info, 'type');
        $problem->difficulty = getValue($info, 'difficulty');
        $problem->tags = getValue($info, 'tags');
        $problem->origin = getValue($info, 'origin');
        $problem->checker = getValue($info, 'checker');
        $problem->executor = getValue($info, 'executor');
        $problem->addedBy = getValue($info, 'addedBy');
        return $problem;
    }

    public static function get($id) {
        $brain = new Brain();
        try {
            return Problem::instanceFromArray($brain->getProblem($id));
        } catch(Exception $ex) {
            error_log('Could not get problem ' . $id . '. Exception: ' . $ex->getMessage());
        }
        return null;
    }

    private function update() {
        $fileName = sprintf('%s/%s/%s', $GLOBALS['PATH_PROBLEMS'], $this->folder, $GLOBALS['PROBLEM_INFO_FILENAME']);
        $file = fopen($fileName, 'w');
        if (!$file) {
            error_log('Unable to open file ' . $fileName . ' for writing!');
            return false;
        }
        if (!fwrite($file, json_encode($this->arrayFromInstance(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
            error_log('Unable to write to file ' . $fileName . '!');
            return false;
        }
        $brain = new Brain();
        $brain->updateProblem($this);
        return true;
    }

    public function scoreSubmit($submit) {
        $brain = new Brain();
        $tests = $brain->getProblemTests($submit->problemId);

        if (count($submit->results) != count($tests)) {
            error_log('Number of tests of problem ' . $submit->problemId . ' differs from results in submission ' . $submit->id . '!');
            echo 'Number of tests of problem ' . $submit->problemId . ' differs from results in submission ' . $submit->id . '!';
        }

        $scoredSubmit = array();

        $scoredSubmit['score'] = 0;
        $scoredSubmit['status'] = $GLOBALS['STATUS_ACCEPTED'];

        $scoredSubmit['results'] = array();
        for ($i = 0; $i < count($submit->results); $i = $i + 1) {
            $result = $submit->results[$i];

            // Non-negative results indicate actually graded tests
            if ($result >= 0) {
                // The grader assigns 0/1 value for each test of IOI- and ACM-style problems and [0, 1] real fraction of the score
                // for games and relative problems. In both cases, multiplying the score of the test by this value is correct.
                $scoredSubmit['results'][$i] = $result * $this->tests[$i]['score'];
                $scoredSubmit['score'] += $scoredSubmit['results'][$i];
            }
            // Negative results indicate exceptional cases
            else {
                // Exceptional cases are considered a zero for the test
                $scoredSubmit['results'][$i] = 0;
                // The possible statuses are ordered by priority - assign the status of the problem to be the highest one
                $scoredSubmit['status'] = Math.max([$scoredSubmit['status'], $result]);
            }
        }

        return $scoredSubmit;
    }

}

?>