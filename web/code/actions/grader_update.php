<?php
require_once(__DIR__ . '/../entities/grader.php');
require_once(__DIR__ . '/../common.php');

$address = getUserIP();

// Hack to work-around CGI handler for PHP
list($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']) = explode(':', base64_decode(substr($_SERVER['HTTP_AUTHORIZATION'], 6)));

if (!isset($_SERVER['PHP_AUTH_USER']) || !isset($_SERVER['PHP_AUTH_PW'])) {
    error_log('Update command sent without authentication from IP: ' . $address);
    exit();
}

if ($GLOBALS['GRADER_USERNAME'] != $_SERVER['PHP_AUTH_USER']) {
    error_log('Update command with wrong username ("' . $_SERVER['PHP_AUTH_USER'] . '") from IP: ' . $address);
    exit();
}

if ($GLOBALS['GRADER_PASSWORD'] != $_SERVER['PHP_AUTH_PW']) {
    error_log('Update command with wrong password ("' . $_SERVER['PHP_AUTH_PW'] . '") from IP: ' . $address);
    exit();
}

$id = isset($_POST['id']) ? intval($_POST['id']) : -1;
$message = isset($_POST['message']) ? $_POST['message'] : '';
$results = isset($_POST['results']) ? json_decode($_POST['results'], true) : [];
$timestamp = isset($_POST['timestamp']) ? floatval($_POST['timestamp']) : -1.0;

$grader = new Grader();
$grader->update($id, $message, $results, $timestamp);

?>