<?php

$COOKIE_NAME = 'action.informatika.bg';

$PATH_PROBLEMS = $_SERVER['DOCUMENT_ROOT'] . '/data/problems/';
$PATH_USERS = $_SERVER['DOCUMENT_ROOT'] . '/data/users/';
$PATH_NEWS = $_SERVER['DOCUMENT_ROOT'] . '/data/news/';


function newLine() {
    return '
';
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