<?php
require_once(__DIR__ . '/../config.php');
require_once(__DIR__ . '/../db/brain.php');
require_once(__DIR__ . '/widgets.php');

class Problem {
    public $id = -1;
    public $name = '';
    public $author = '';
    public $folder = '';
    public $timeLimit = -1;
    public $memoryLimit = -1;
    public $type = '';
    public $difficulty = '';
    public $statement = '';
    public $tags = array();
    public $origin = '';
    public $checker = '';
    public $tester = '';
    public $addedBy = '';

    public function __construct() {
        $this->name = 'ProblemName';
        $this->author = 'Problem Author';
        $this->folder = 'ProblemFolder';
        $this->timeLimit = 0.1;
        $this->memoryLimit = 64;
        $this->type = 'ioi';
        $this->difficulty = 'medium';
        $this->origin = 'Problem Origin';
        $this->addedBy = $GLOBALS['user']->username;

        $emptyStatementPath = sprintf("%s/%s", $GLOBALS['PATH_PROBLEMS'], $GLOBALS['PROBLEM_STATEMENT_FILENAME']);
        $this->statement = file_get_contents($emptyStatementPath);
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
            'tester' => $this->tester,
            'addedBy' => $this->addedBy
        );
    }

    private static function instanceFromArray($info) {
        $problem = new Problem;
        $problem->id = intval(getValue($info, 'id'));
        $problem->name = getValue($info, 'name');
        $problem->author = getValue($info, 'author');
        $problem->folder = getValue($info, 'folder');
        $problem->timeLimit = floatval(getValue($info, 'timeLimit'));
        $problem->memoryLimit = floatval(getValue($info, 'memoryLimit'));
        $problem->type = getValue($info, 'type');
        $problem->difficulty = getValue($info, 'difficulty');
        $problem->statement = getValue($info, 'statement');
        $problem->tags = explode(',', getValue($info, 'tags'));
        $problem->origin = getValue($info, 'origin');
        $problem->checker = getValue($info, 'checker');
        $problem->tester = getValue($info, 'tester');
        $problem->addedBy = getValue($info, 'addedBy');
        return $problem;
    }

    public static function get($id) {
        $brain = new Brain();
        try {
            $info = $brain->getProblem($id);
            if ($info == null) {
                error_log('Could not get problem ' . $id . '!');
                return null;
            }
            return Problem::instanceFromArray($info);
        } catch (Exception $ex) {
            error_log('Could not get problem ' . $id . '. Exception: ' . $ex->getMessage());
        }
        return null;
    }

    public function update() {
        // Update the problem meta information (JSON file in the problem folder)
        $infoPath = sprintf("%s/%s/%s", $GLOBALS['PATH_PROBLEMS'], $this->folder, $GLOBALS['PROBLEM_INFO_FILENAME']);
        if (!file_put_contents($infoPath, json_encode($this->arrayFromInstance(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
            error_log('Unable to record data in file "' . $infoPath . '"!');
            return false;
        }

        // Update the problem statement (HTML file in the problem folder)
        $statementPath = sprintf("%s/%s/%s",  $GLOBALS['PATH_PROBLEMS'], $this->folder, $GLOBALS['PROBLEM_STATEMENT_FILENAME']);
        if (!file_put_contents($statementPath, $this->statement)) {
            error_log('Unable to write statement to file "' . $statementPath . '"!');
            return false;
        }

        $brain = new Brain();
        return $brain->updateProblem($this);
    }

    public function create() {
        // Add the problem to the database
        $brain = new Brain();
        $result = $brain->addProblem();
        if (!$result) {
            return false;
        }
        $this->id = $result;

        // Create main folder
        $problemPath = sprintf("%s/%s", $GLOBALS['PATH_PROBLEMS'], $this->folder);
        if (!mkdir($problemPath)) {
            error_log('Unable to create problem folder "' . $problemPath . '"!');
            return false;
        }

        // Create tests folder
        $testsPath = sprintf("%s/%s/%s", $GLOBALS['PATH_PROBLEMS'], $this->folder, $GLOBALS['PROBLEM_TESTS_FOLDER']);
        if (!mkdir($testsPath)) {
            error_log('Unable to create problem tests folder "' . $testsPath . '"!');
            return false;
        }

        // Create solutions folder
        $solutionsPath = sprintf("%s/%s/%s", $GLOBALS['PATH_PROBLEMS'], $this->folder, $GLOBALS['PROBLEM_SOLUTIONS_FOLDER']);
        if (!mkdir($solutionsPath)) {
            error_log('Unable to create problem solutions folder "' . $solutionsPath . '"!');
            return false;
        }

        // Update the meta information and the statement
        return $this->update();
    }
}

?>