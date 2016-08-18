<?php
require_once('common.php');
require_once('user.php');
require_once('problem.php');


// In case an actual submit, not only using the class
if (session_status() == PHP_SESSION_NONE) {
    session_start();

    $SPAM_LIMIT = 100; // Submissions per 24 hours

    $user = User::get($_SESSION['username']);
    if ($user == null || $user->access < $GLOBALS['ACCESS_SUBMIT_SOLUTION']) {
        printAjaxResponse(array(
            'status' => 'UNAUTHORIZED'
        ));
    }

    if (!passSpamProtection('submit_log.txt', $user, $SPAM_LIMIT)) {
        printAjaxResponse(array(
            'status' => 'SPAM'
        ));
    } else {
        $submit = new Submit($user->username, $_POST['problemId'], $_POST['language'], $_POST['source']);

        // If cannot write the submit info file or update the user/problem or the solution cannot be sent
        // to the grader for judging (the grader machine is down or not accessible)
        if (!$submit->write() || !$submit->send()) {
            printAjaxResponse(array(
                'id' => -1,
                'status' => 'ERROR'
            ));
        }
        // Otherwise print success and return the submit ID
        else {
            printAjaxResponse(array(
                'id' => $submit->id,
                'status' => 'OK'
            ));
        }
    }
    exit();
}

class Submit {
    public $id = -1;
    public $timestamp = -1;
    public $source = '';
    public $language = '';
    public $userId = -1;
    public $userName = '';
    public $problemId = -1;
    public $problemName = '';
    public $results = array();
    public $message = '';

    private $user = null;
    private $problem = null;

    function __construct($userName, $problemId, $language, $source) {
        $this->user = User::get($userName);
        $this->problem = Problem::get($problemId);

        // Create a unique ID for the submission
        $this->id = $this->getUniqueId();

        // Mark the time of the submission
        $this->timestamp = time();

        // Populate the remaining submission info
        $this->language = $language;
        $this->source = $source;
        $this->userId = $this->user->id;
        $this->userName = $this->user->username;
        $this->problemId = $this->problem->id;
        $this->problemName = $this->problem->name;
        $this->results = array();
        for ($i = 0; $i < count($this->problem->tests); $i = $i + 1) {
            $this->results[$i] = $GLOBALS['STATUS_WAITING'];
        }
        $this->message = '';
    }

    private function arrayFromInstance() {
        return array(
            'id' => $this->id,
            'timestamp' => $this->timestamp,
            'source' => $this->source,
            'language' => $this->language,
            'userId' => $this->userId,
            'userName' => $this->userName,
            'problemId' => $this->problemId,
            'problemName' => $this->problemName,
            'results' => $this->results,
            'message' => $this->message
        );
    }

    private function getUniqueId() {
        $id = -1;
        while (true) {
            $id = rand(100000000, 999999999);
            if (!file_exists($this->getPath($id, 'json'))) {
                break;
            }
        }
        return $id;
    }

    private static function getPath($id, $extension) {
        return sprintf('%s/%02d/%09d.%s', $GLOBALS['PATH_SUBMISSIONS'], $id / 10000000, $id, $extension);
    }

    public function write() {
        $infoFilePath = $this->getPath($this->id, 'json');
        // If the bucket directory doesn't already exist, create it
        if (!file_exists(dirname($infoFilePath))) {
            if (!mkdir(dirname($infoFilePath), 0777, true)) {
                error_log('Unable to create directory for file ' . $infoFilePath . '!');
                return false;
            }
        }

        // Create the info file for this submission
        $file = fopen($infoFilePath, 'w');
        if (!$file) {
            error_log('Unable to create file ' . $infoFilePath . '!');
            return false;
        }
        fwrite($file, json_encode($this->arrayFromInstance(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        fclose($file);

        // Write also the actual source of the submission
        $sourcePath = $this->getPath($this->id, $GLOBALS['LANGUAGE_EXTENSIONS'][$this->language]);
        $file = fopen($sourcePath, 'w');
        if (!$file) {
            error_log('Unable to create file ' . $sourcePath . '!');
            return false;
        }
        fwrite($file, $this->source);
        fclose($file);

        // Record the request in the submission queue
        $file = fopen($GLOBALS['SUBMIT_QUEUE_FILENAME'], 'a');
        if (!$file) {
            error_log('Unable to append to file ' . $GLOBALS['SUBMIT_QUEUE_FILENAME'] . '!');
            return false;
        }
        fprintf($file, "%d\n", $this->id);
        fclose($file);

        // Record the submission in the user history
        if (!$this->user->addSubmission($this->id)) {
            return false;
        }

        // Record the submission in the problem history
        if (!$this->problem->addSubmission($this->id)) {
            return false;
        }

        return true;
    }

    public function send() {
        $url = $GLOBALS['GRADER_URL'] . '/grade';
        $data = array(
            'id' => $this->id,
            'source' => $this->source,
            'language' => $this->language,
            'checker' => 'none',
            'path' => 'localhost/data/problems/a_b_problem',
            'time_limit' => 0.5,
            'memory_limit' => 64,
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

    public static function getSubmitInfo($id) {
        $infoFilePath = Submit::getPath($id, 'json');
        if (!file_exists($infoFilePath)) {
            return null;
        }
        $info = json_decode(file_get_contents($infoFilePath), true);

        $problem = Problem::get($info['problemId']);
        if ($problem == null) {
            return null;
        }

        $score = 0;
        $status = $GLOBALS['STATUS_ACCEPTED'];

        for ($i = 0; $i < count($info['results']); $i = $i + 1) {
            $result = $info['results'][$i];

            // The grader assigns 0/1 value for each test of IOI- and ACM-style problems and [0, 1] real fraction of the score
            // for games and relative problems. In both cases, multiplying the score of the test by this value is correct.
            if ($result >= 0) {
                $info['results'][$i] = $result * $problem->tests[$i]['score'];
                $score += $info['results'][$i];
            }
            // Negative scores indicate exceptional cases and are considered a zero for the test
            else {
                // The possible statuses are ordered by priority - assign the status of the problem to be the highest one
                $status = $result > $status ? $result : $status;
            }
        }

        $info['date'] = date('d. F, Y', $info['timestamp']);
        $info['time'] = date('H:i:s', $info['timestamp']);
        $info['score'] = $score;
        $info['status'] = $status;

        return $info;
    }

}

?>