<?php
require_once('brain.php');
require_once('config.php');
require_once('widgets.php');
require_once('user.php');
require_once('problem.php');

// In case an actual submit, not only using the class
if (isset($_GET['action']) && $_GET['action'] == 'submit') {
    if ($user->access < $GLOBALS['ACCESS_SUBMIT_SOLUTION']) {
        printAjaxResponse(array(
            'status' => 'UNAUTHORIZED'
        ));
    }

    if (!passSpamProtection($user, $GLOBALS['SPAM_SUBMIT_ID'], $GLOBALS['SPAM_SUBMIT_LIMIT'])) {
        printAjaxResponse(array(
            'status' => 'SPAM'
        ));
        exit();
    }

    // User has rights to submit and has not exceeded the limit for the day
    $submit = Submit::newSubmit($user, $_POST['problemId'], $_POST['language'], $_POST['source']);

    // If cannot write the submit info file or update the user/problem or the solution cannot be sent
    // to the grader for judging (the grader machine is down or not accessible)
    if (!$submit->write() || !$submit->send()) {
        printAjaxResponse(array(
            'status' => 'ERROR'
        ));
    }
    // Otherwise print success and return the submit ID
    else {
        printAjaxResponse(array(
            'status' => 'OK',
            'id' => $submit->id
        ));
    }
    exit();
}

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
        $submit->id = $info['id'];
        $submit->time = $info['time'];
        $submit->userId = $info['userId'];
        $submit->userName = $info['userName'];
        $submit->problemId = $info['problemId'];
        $submit->problemName = $info['problemName'];
        $submit->source = $info['source'];
        $submit->language = $info['language'];
        $submit->results = array_map('intval', explode(',', $info['results']));
        $submit->status = $info['status'];
        $submit->message = $info['message'];
        return $submit;
    }

    public static function getSubmit($submitId) {
        $brain = new Brain();
        $submitMap = $brain->getSubmit($submitId);
        return Submit::instanceFromArray($submitMap);
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