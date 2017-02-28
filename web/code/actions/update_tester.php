<?php
require_once(__DIR__ . '/../config.php');
require_once(__DIR__ . '/../db/brain.php');
require_once(__DIR__ . '/../entities/problem.php');

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

$testerDir = sprintf("%s/%s/%s", $GLOBALS['PATH_PROBLEMS'], $problem->folder,
                                 $GLOBALS['PROBLEM_TESTER_FOLDER']);

// Delete current tester if present
if ($problem->tester != '') {
    $oldTester = sprintf("%s/%s", $testerDir, $problem->tester);
    unlink($oldTester);
    $problem->tester = '';
}
if (file_exists($testerDir)) {
    rmdir($testerDir);
}

if ($_POST['action'] == 'upload') {
    $problem->tester = $_POST['testerName'];

    // Create the Tester directory if it doesn't already exist
    if (!file_exists($testerDir)) {
        mkdir($testerDir, 0777, true);
    }

    // TODO: Maybe replace CRLF only if Linux is detected?
    $testerContent = base64_decode($_POST['testerContent']);
    $testerContent = preg_replace('~\R~u', "\n", $testerContent);
    file_put_contents($testerDir . '/' . $problem->tester, $testerContent);
}

$brain = new Brain();
$brain->updateTester($problem);

// Everything seems okay
printAjaxResponse(array(
    'status' => 'OK',
    'message' => 'Чекерът беше обновен успешно.'
));

?>