<?php
require_once(__DIR__ . '/../entities/grader.php');

// TODO: This may or may not be a security issue
// If a person knows the replayId of another person he/she may get it using this file directly.
$replayId = $_POST['replayId'];

// Invoke the Grader to get the replay
$grader = new Grader();
$data = $grader->get_replay($replayId);

if ($data != null) {
    echo $data;
    die();
} else {
    error_log('Error while trying to get a replay with id ' . $replayId . '!');
}
