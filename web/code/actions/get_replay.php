<?php
require_once(__DIR__ . '/../entities/grader.php');

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
