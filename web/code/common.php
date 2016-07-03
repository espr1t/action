<?php

$PATH_PROBLEMS = $_SERVER['DOCUMENT_ROOT'] . '/data/problems/';
$PATH_USERS = $_SERVER['DOCUMENT_ROOT'] . '/data/users/';

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

function userInfo($user) {
    if ($user->getUsername() != "anonymous") {
        return '<div class="userInfo">user: <div class="user">' . $user->getUsername() . '</div></div>';
    }
    return '';
}

function hashPassword($password) {
    return md5('informatika.bg' . $password);
}

?>