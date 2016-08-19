<?php

function showMessage($type, $message) {
    return '<script>showMessage("' . $type . '", "' . $message . '");</script>';
}

function getUserLink($userName) {
    return '<a href="/users/' . $userName . '"><div class="user">' . $userName . '</div></a>';
}

function getProblemLink($problemId, $problemName) {
    return '<a href="/problems/' . $problemId . '"><div class="problem">' . $problemName . '</div></a>';
}

function userInfo($user) {
    if ($user->username != "anonymous") {
        return '<div class="userInfo">logged in as: ' . getUserLink($user->username) . '</div>';
    }
    return '';
}

function inBox($content, $extra=array()) {
    $classes = array_merge(array("box"), $extra);
    return '
            <div class="' . implode(";", $classes) . '">
                ' . $content . '
            </div>
    ';
}

?>