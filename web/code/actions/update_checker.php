<?php
require_once(__DIR__ . '/../config.php');
require_once(__DIR__ . '/../db/brain.php');
require_once(__DIR__ . '/../entities/problem.php');

global $user;

// User doesn't have access level needed for adding testcases
if ($user->access < $GLOBALS['ACCESS_EDIT_PROBLEM']) {
    printAjaxResponse(array(
        'status' => 'ERROR',
        'message' => 'Нямате права да променяте задачата.'
    ));
}

$problem = Problem::get($_POST['problemId']);
if ($problem == null) {
    printAjaxResponse(array(
        'status' => 'ERROR',
        'message' => 'Няма задача с ID "' . $_POST['problemId'] . '"!'
    ));
}

$checkerDir = sprintf("%s/%s/%s", $GLOBALS['PATH_PROBLEMS'], $problem->folder,
                                  $GLOBALS['PROBLEM_CHECKER_FOLDER']);

// Delete current checker if present
if ($problem->checker != '') {
    $oldChecker = sprintf("%s/%s", $checkerDir, $problem->checker);
    unlink($oldChecker);
    $problem->checker = '';
}
if (file_exists($checkerDir)) {
    rmdir($checkerDir);
}

if ($_POST['action'] == 'upload') {
    $problem->checker = $_POST['checkerName'];

    // Create the Checker directory if it doesn't already exist
    if (!file_exists($checkerDir)) {
        mkdir($checkerDir, 0777, true);
    }

    // TODO: Maybe replace CRLF only if Linux is detected?
    $checkerContent = base64_decode($_POST['checkerContent']);
    $checkerContent = preg_replace('~\R~u', "\n", $checkerContent);
    file_put_contents($checkerDir . '/' . $problem->checker, $checkerContent);
}

Brain::updateChecker($problem);

// Everything seems okay
printAjaxResponse(array(
    'status' => 'OK',
    'message' => 'Чекерът беше обновен успешно.'
));

?>