<?php
date_default_timezone_set('Europe/Sofia');

$COOKIE_NAME = 'action.informatika.bg';

// User access
$DEFAULT_USER_ACCESS = 10;
$ACCESS_REPORT_PROBLEM = 2;
$ACCESS_SUBMIT_SOLUTION = 1;

// System paths
$PATH_PROBLEMS = $_SERVER['DOCUMENT_ROOT'] . '/data/problems';
$PATH_USERS = $_SERVER['DOCUMENT_ROOT'] . '/data/users';
$PATH_NEWS = $_SERVER['DOCUMENT_ROOT'] . '/data/news';
$PATH_SUBMISSIONS = $_SERVER['DOCUMENT_ROOT'] . '/data/submissions';

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
        die('User info does not contain value for key "'. $key . '"!');
    }
    return $array[$key];
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
                if ($user->getUsername() == $username) {
                    $spamCount = $spamCount + 1;
                }
            }
        }
    }
    if ($spamCount < $limit) {
        fprintf($out, "%s %d\n", $user->getUsername(), $curTime);
    }
    fclose($out);
    return $spamCount < $limit;
}

function printAjaxResponse($response) {
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}

?>