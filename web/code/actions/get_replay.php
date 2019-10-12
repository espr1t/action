<?php
require_once(__DIR__ . '/../entities/grader.php');

$replayId = $_POST['replayId'];

// Invoke the Grader to get the replay
$grader = new Grader();
$data = $grader->get_replay($replayId);

if ($data != null) {
    /*
    header($_SERVER["SERVER_PROTOCOL"] . " 200 OK");
    header("Cache-Control: public"); // needed for internet explorer
    header("Content-Type: text/html; charset=UTF-8");
    header("Content-Length:" . strlen($data));
    */
    echo $data;
    die();
} else {
    error_log('Error while trying to get a replay with id ' . $replayId . '!');
}
