<?php
require_once(__DIR__ . '/../entities/user.php');
require_once(__DIR__ . '/../entities/widgets.php');

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

$submitId = $_POST['id'];
$status = $_POST['status'];
$message = $_POST['message'];

error_log(sprintf('Received update on submission %d with status %s and message: %s', $submitId, $status, $message));


?>