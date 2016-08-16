<?php
require_once('../user.php');
require_once('../common.php');
require_once('logic.php');

session_start();

$SPAM_LIMIT = 100; // Submissions per 24 hours

$user = User::getUser($_SESSION['username']);
if ($user == null || $user->getAccess() < $GLOBALS['ACCESS_SUBMIT_SOLUTION']) {
    printAjaxResponse(array(
        'status' => 'UNAUTHORIZED'
    ));
}

if (!passSpamProtection('submit_log.txt', $user, $SPAM_LIMIT)) {
    printAjaxResponse(array(
        'status' => 'SPAM'
    ));
} else {
    // Create a unique ID for the submission.
    while (true) {
        $id = rand(0, 999999999);
        $bucket = $id / 10000000;
        $infoFile = sprintf('%s/%02d/%09d.json', $GLOBALS['PATH_SUBMISSIONS'], $bucket, $id);
        if (!file_exists($infoFile)) {
            break;
        }
    }

    // If the bucket directory doesn't already exist, create it
    if (!file_exists(sprintf('%s/%02d', $GLOBALS['PATH_SUBMISSIONS'], $bucket))) {
        mkdir(sprintf('%s/%02d', $GLOBALS['PATH_SUBMISSIONS'], $bucket));
    }

    // Populate submission info
    $problemInfo = Logic::getProblemInfo(intval($_POST['problemId']));
    $results = array();
    for ($i = 0; $i < count($problemInfo['tests']); $i = $i + 1) {
        $results[$i] = $GLOBALS['STATUS_WAITING'];
    }

    $info = array(
        'id' => $id,
        'timestamp' => time(),
        'userId' => $user->getId(),
        'userName' => $user->getUsername(),
        'problemId' => $problemInfo['id'],
        'problemName' => $problemInfo['name'],
        'language' => $_POST['language'],
        'results' => $results,
        'message' => ''
    );

    // Create the info file for this submission
    $file = fopen($infoFile, 'w') or die('Unable to create file ' . $infoFile . '!');
    fwrite($file, json_encode($info, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    fclose($file);

    // Write also the actual source of the submission
    $extension = $GLOBALS['LANGUAGE_EXTENSIONS'][$_POST['language']];
    $sourceFile = sprintf('%s/%02d/%09d.%s', $GLOBALS['PATH_SUBMISSIONS'], $bucket, $id, $extension);

    $file = fopen($sourceFile, 'w') or die('Unable to create file ' . $sourceFile . '!');
    fwrite($file, $_POST['source']);
    fclose($file);

    // Record the request in the submission queue.
    $file = fopen($GLOBALS['SUBMIT_QUEUE_FILENAME'], 'a')
            or die('Unable to append to file ' . $GLOBALS['SUBMIT_QUEUE_FILENAME'] . '!');
    fprintf($file, "%d\n", $id);
    fclose($file);

    // Record the submission in the user history
    array_push($user->getSubmissions(), $id);
    if (!in_array($problemInfo['id'], $user->getTried())) {
        array_push($user->getTried(), $problemInfo['id']);
    }
    $user->updateInfo();

    // TODO: Send a request to the grader

    printAjaxResponse(array(
        'status' => 'OK',
        'id' => $id
    ));
}

?>