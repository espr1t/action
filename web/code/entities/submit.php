<?php
require_once(__DIR__ . '/../config.php');
require_once(__DIR__ . '/../db/brain.php');
require_once(__DIR__ . '/problem.php');
require_once(__DIR__ . '/widgets.php');

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
    public $progress = 0;
    public $status = -1;
    public $message = '';

    private $brain = null;

    function __construct() {
        $this->brain = new Brain();
    }

    public static function newSubmit($user, $problemId, $language, $source) {
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
        $numTests = count($submit->brain->getProblemTests($problem->id));
        for ($i = 0; $i < $numTests; $i = $i + 1) {
            $submit->results[$i] = $GLOBALS['STATUS_WAITING'];
        }
        $submit->progress = 0;
        $submit->status = $GLOBALS['STATUS_WAITING'];
        $submit->message = '';
        return $submit;
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

        $url = $GLOBALS['GRADER_URL'] . '/grade';
        $data = array(
            'id' => $this->id,
            'source' => $this->source,
            'language' => $this->language,
            'checker' => 'none',
            'path' => 'localhost/data/problems/a_b_problem',
            'timeLimit' => 0.5,
            'memoryLimit' => 64,
            'tests' => array(
                array(
                    'test_id' => 1,
                    'test_name' => 'a_b_problem.01.in',
                    'test_md5' => 'awdawd',
                    'solution_name' => 'a_b_problem.01.sol',
                    'solution_md5' => 'foobarbaz'
                ),
                array(
                    'test_id' => 2,
                    'test_name' => 'a_b_problem.02.in',
                    'test_md5' => 'awdawd',
                    'solution_name' => 'a_b_problem.02.sol',
                    'solution_md5' => 'foobarbaz'
                ),
                array(
                    'test_id' => 3,
                    'test_name' => 'a_b_problem.03.in',
                    'test_md5' => 'awdawd',
                    'solution_name' => 'a_b_problem.03.sol',
                    'solution_md5' => 'foobarbaz'
                )
            )
        );

        return true;
    }

    private static function instanceFromArray($info) {
        $submit = new Submit();
        $submit->id = getValue($info, 'id');
        $submit->time = getValue($info, 'time');
        $submit->userId = getValue($info, 'userId');
        $submit->userName = getValue($info, 'userName');
        $submit->problemId = getValue($info, 'problemId');
        $submit->problemName = getValue($info, 'problemName');
        $submit->source = getValue($info, 'source');
        $submit->language = getValue($info, 'language');
        $submit->results = array_map('intval', explode(',', getValue($info, 'results')));
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

}

?>