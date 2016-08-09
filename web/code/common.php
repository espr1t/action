<?php
$COOKIE_NAME = 'action.informatika.bg';

$PATH_PROBLEMS = $_SERVER['DOCUMENT_ROOT'] . '/data/problems/';
$PATH_USERS = $_SERVER['DOCUMENT_ROOT'] . '/data/users/';
$PATH_NEWS = $_SERVER['DOCUMENT_ROOT'] . '/data/news/';

$ACCESS_REPORT_PROBLEM = 1;

date_default_timezone_set('Europe/Sofia');


function newLine() {
    return '
';
}

function showMessage($type, $message) {
    return '<script>showMessage("' . $type . '", "' . $message . '");</script>';
}

function inBox($content, $extra=array()) {
    $classes = array_merge(array("box"), $extra);
    return '
            <div class="' . implode(";", $classes) . '">' . newLine() .
                $content . '
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

?>