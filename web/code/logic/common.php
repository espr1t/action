<?php
// Set timezone
date_default_timezone_set('Europe/Sofia');

// Session and cookie
$COOKIE_NAME = 'action.informatika.bg';

// Grader
$GRADER_URL = 'localhost:5000';

// User access
$DEFAULT_USER_ACCESS = 10;
$ACCESS_REPORT_PROBLEM = 2;
$ACCESS_SUBMIT_SOLUTION = 1;

// System paths
$PATH_PROBLEMS = $_SERVER['DOCUMENT_ROOT'] . '/data/problems';
$PATH_USERS = $_SERVER['DOCUMENT_ROOT'] . '/data/users';
$PATH_NEWS = $_SERVER['DOCUMENT_ROOT'] . '/data/news';
$PATH_SUBMISSIONS = $_SERVER['DOCUMENT_ROOT'] . '/data/submissions';
$PATH_LOGIC = $_SERVER['DOCUMENT_ROOT'] . '/code/logic';
$PATH_ACHIEVEMENTS = $_SERVER['DOCUMENT_ROOT'] . '/data/achievements';

// Submission status
$STATUS_WAITING = -1;
$STATUS_RUNNING = -2;
$STATUS_INTERNAL_ERROR = -3;
$STATUS_COMPILATION_ERROR = -4;
$STATUS_MEMORY_LIMIT = -5;
$STATUS_TIME_LIMIT = -6;
$STATUS_RUNTIME_ERROR = -7;
$STATUS_WRONG_ANSWER = -8;
$STATUS_ACCEPTED = -9; // Individual tests are marked with a non-negative number indicating the score for the test

$STATUS_DISPLAY_NAME = array(
    $STATUS_WAITING => 'Waiting',
    $STATUS_RUNNING => 'Running',
    $STATUS_INTERNAL_ERROR => 'Internal Error',
    $STATUS_COMPILATION_ERROR => 'Compilation Error',
    $STATUS_MEMORY_LIMIT => 'Memory Limit',
    $STATUS_TIME_LIMIT => 'Time Limit',
    $STATUS_RUNTIME_ERROR => 'Runtime Error',
    $STATUS_WRONG_ANSWER => 'Wrong Answer',
    $STATUS_ACCEPTED => 'Accepted'
);

// Submission queue
$SUBMIT_QUEUE_FILENAME = 'submit_queue.txt';
$SUBMIT_DONE_FILENAME = 'submit_done.txt';

// Problems
$PROBLEM_INFO_FILENAME = '/problem_info.json';
$PROBLEM_STATEMENT_FILENAME = '/problem_statement.html';


$SPAM_INTERVAL = 86400; // Seconds in 24 hours

$LANGUAGE_EXTENSIONS = array(
    'C++' => 'cpp',
    'Java' => 'java',
    'Python' => 'py'
);

function showMessage($type, $message) {
    return '<script>showMessage("' . $type . '", "' . $message . '");</script>';
}

function inBox($content, $extra=array()) {
    $classes = array_merge(array("box"), $extra);
    return '
            <div class="' . implode(";", $classes) . '">
                ' . $content . '
            </div>
    ';
}

function saltHashPassword($password) {
    return md5($password . 'informatika.bg');
}

function getValue($array, $key) {
    if (!array_key_exists($key, $array)) {
        error_log('User info does not contain value for key "'. $key . '"!');
        return null;
    }
    return $array[$key];
}

function getUserLink($userName) {
    return '<a href="/users/' . $userName . '"><div class="user">' . $userName . '</div></a>';
}

function getProblemLink($problemId, $problemName) {
    return '<a href="/problems/' . $problemId . '"><div class="problem">' . $problemName . '</div></a>';
}

function passSpamProtection($fileName, $user, $limit) {
    $curTime = time();
    $logs = preg_split('/\r\n|\r|\n/', file_get_contents($fileName));
    $length = count($logs);
    $spamCount = 0;
    $out = fopen($fileName, 'w');
    for ($i = 0; $i < $length; $i = $i + 1) {
        $username = '';
        $timestamp = 0;
        // Use double-quotes as single quotes have problems with \r and \n
        if (sscanf($logs[$i], "%s %d", $username, $timestamp) == 2) {
            if ($curTime - $timestamp < $GLOBALS['SPAM_INTERVAL']) {
                fprintf($out, "%s %d\n", $username, $timestamp);
                if ($user->username == $username) {
                    $spamCount = $spamCount + 1;
                }
            }
        }
    }
    if ($spamCount < $limit) {
        fprintf($out, "%s %d\n", $user->username, $curTime);
    }
    fclose($out);
    return $spamCount < $limit;
}

function printAjaxResponse($response) {
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}

?>