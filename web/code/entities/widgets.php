<?php
require_once(__DIR__ . '/../config.php');
require_once(__DIR__ . '/../db/brain.php');

function saltHashPassword($password) {
    return md5($password . $GLOBALS['PASSWORD_SALT']);
}

function getValue($array, $key) {
    if (!array_key_exists($key, $array)) {
        error_log('Array does not contain value for key "'. $key . '"!');
        return null;
    }
    return $array[$key];
}

function passSpamProtection($user, $type, $limit) {
    $brain = new Brain();
    $brain->refreshSpamCounters(time() - $GLOBALS['SPAM_INTERVAL']);
    if ($brain->getSpamCounter($user, $type) < $limit) {
        $brain->incrementSpamCounter($user, $type, time());
        return true;
    }
    return false;
}

function printAjaxResponse($response) {
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit(0);
}

?>
