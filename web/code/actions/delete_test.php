<?php
require_once(__DIR__ . '/../config.php');
require_once(__DIR__ . '/../db/brain.php');
require_once(__DIR__ . '/../entities/problem.php');

// User doesn't have access level needed for deleting testcases
if ($user->access < $GLOBALS['ACCESS_EDIT_PROBLEM']) {
    printAjaxResponse(array(
        'status' => 'ERROR',
        'message' => 'Нямате права да премахвате тестове.'
    ));
}

$problem = Problem::get($_POST['problemId']);
if ($problem == null) {
    printAjaxResponse(array(
        'status' => 'ERROR',
        'message' => 'Няма задача с ID "' . $_POST['problemId'] . '"!'
    ));
}

// Suppress warnings if file does not exist or cannot be erased.
set_error_handler(function() { /* ignore errors */ });
$inpFilePath = sprintf("%s/%s/%s/%s", $GLOBALS['PATH_PROBLEMS'], $problem->folder,
                                   $GLOBALS['PROBLEM_TESTS_FOLDER'], $_POST['inpFile']);
unlink($inpFilePath);

$solFilePath = sprintf("%s/%s/%s/%s", $GLOBALS['PATH_PROBLEMS'], $problem->folder,
                                   $GLOBALS['PROBLEM_TESTS_FOLDER'], $_POST['solFile']);
unlink($solFilePath);
restore_error_handler();

// Also delete from database
$brain = new Brain();
if ($brain->deleteTest($problem->id, $_POST['position']) == null) {
    printAjaxResponse(array(
        'status' => 'ERROR',
        'message' => 'Тестът не беше изтрит от базата.'
    ));
}

// Everything seems okay
printAjaxResponse(array(
    'status' => 'OK',
    'message' => 'Тестът беше изтрит успешно.'
));

?>