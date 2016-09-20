<?php
require_once('brain.php');
require_once('config.php');
require_once('problem.php');

// User doesn't have access level needed for modifying a problem
if ($user->access < $GLOBALS['ACCESS_MODIFY_PROBLEM']) {
    printAjaxResponse(array(
        'status' => 'ERROR',
        'message' => 'Нямате права да триете тестове.'
    ));
}

$problem = Problem::get($_POST['problemId']);

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
$brain->deleteTest($problem->id, $_POST['position']);

// Everything seems okay
printAjaxResponse(array(
    'status' => 'OK',
    'message' => 'Тестът беше изтрит успешно.'
));

?>