<?php
require_once(__DIR__ . '/../config.php');
require_once(__DIR__ . '/../db/brain.php');
require_once(__DIR__ . '/../entities/problem.php');

// User doesn't have access level needed for deleting solutions
if ($user->access < $GLOBALS['ACCESS_EDIT_PROBLEM']) {
    printAjaxResponse(array(
        'status' => 'ERROR',
        'message' => 'Нямате права да изтривате решения.'
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
// Generate the path where to store the solution
$solutionPath = sprintf("%s/%s/%s/%s", $GLOBALS['PATH_PROBLEMS'], $problem->folder,
                                       $GLOBALS['PROBLEM_SOLUTIONS_FOLDER'], $solutionName);
unlink($solutionPath);
restore_error_handler();

// Also delete from database
$brain = new Brain();
if ($brain->deleteSolution($problem->id, $_POST['name']) == null) {
    printAjaxResponse(array(
        'status' => 'ERROR',
        'message' => 'Решението не беше изтрит от базата.'
    ));
}

// Everything seems okay
printAjaxResponse(array(
    'status' => 'OK',
    'message' => 'Решението беше изтрито успешно.'
));

?>