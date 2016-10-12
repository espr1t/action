<?php
require_once(__DIR__ . '/../entities/grader.php');

if (!isset($_SERVER['PHP_AUTH_USER']) || !isset($_SERVER['PHP_AUTH_PW'])) {
    error_log('Update command sent without authentication from IP: ' . $_SERVER['REMOTE_ADDR']);
    exit();
}
if (sha1($GLOBALS['GRADER_USERNAME']) != $_SERVER['PHP_AUTH_USER']) {
    error_log('Update command with wrong username ("' . $_SERVER['PHP_AUTH_USER'] . '") from IP: ' . $_SERVER['REMOTE_ADDR']);
    exit();
}
if (sha1($GLOBALS['GRADER_PASSWORD']) != $_SERVER['PHP_AUTH_PW']) {
    error_log('Update command with wrong password ("' . $_SERVER['PHP_AUTH_PW'] . '") from IP: ' . $_SERVER['REMOTE_ADDR']);
    exit();
}

$id = isset($_POST['id']) ? $_POST['id'] : -1;
$message = isset($_POST['message']) ? $_POST['message'] : '';
$results = isset($_POST['results']) ? $_POST['results'] : [];

$grader = new Grader();
$grader->update($id, $message, $results);

?>