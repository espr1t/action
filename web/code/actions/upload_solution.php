<?php
require_once(__DIR__ . '/../config.php');
require_once(__DIR__ . '/../db/brain.php');
require_once(__DIR__ . '/../entities/problem.php');
require_once(__DIR__ . '/../entities/submit.php');
require_once(__DIR__ . '/../entities/user.php');

// User doesn't have access level needed for uploading solutions
if ($user->access < $GLOBALS['ACCESS_EDIT_PROBLEM']) {
    printAjaxResponse(array(
        'status' => 'ERROR',
        'message' => 'Нямате права да качвате решения.'
    ));
}

$problemId = $_POST['problemId'];
$problem = Problem::get($problemId);
if ($problem == null) {
    printAjaxResponse(array(
        'status' => 'ERROR',
        'message' => 'Няма задача с ID "' . $problemId . '"!'
    ));
}

$solutionName = $_POST['solutionName'];

// Determine language by the file extention
$language = end(explode('.', $solutionName));
if (!array_key_exists($language, $GLOBALS['SUPPORTED_LANGUAGES'])) {
    printAjaxResponse(array(
        'status' => 'ERROR',
        'message' => 'Неразпознат програмен език на файл с име: "' . $solutionName . '"!'
    ));
}
$language = $GLOBALS['SUPPORTED_LANGUAGES'][$language];

// Generate the path where to store the solution
$solutionPath = sprintf("%s/%s/%s/%s", $GLOBALS['PATH_PROBLEMS'], $problem->folder,
                                       $GLOBALS['PROBLEM_SOLUTIONS_FOLDER'], $solutionName);
// TODO: Maybe replace CRLF only if Linux is detected?
$solutionSource = base64_decode($_POST['solutionSource']);
$solutionSource = preg_replace('~\R~u', "\n", $solutionSource);
file_put_contents($solutionPath, $solutionSource);

// Solutions should be uploaded with System's user
$user = User::get(0);

// Create a hidden judge submit
$submit = Submit::newSubmit($user, $problemId, $language, $solutionSource, false /* full */, true /* hidden */);

// In case the solution cannot be written to the database, return an error
// so the user knows something is wrong
if (!$submit->write()) {
    printAjaxResponse(array(
        'status' => 'ERROR',
        'message' => 'Възникна проблем при записването на решението.'
    ));
}

// Record that this is an author's solution in the correct table
$brain = new Brain();
$brain->addSolution($problemId, $solutionName, $submit->id, $solutionSource, $language);

// Return the relative path to the solution so it is displayed properly on the frontend
$solutionPath = explode($_SERVER['DOCUMENT_ROOT'], $solutionPath)[1];

$status = 'OK';
$message = 'Решението беше качено успешно.';

// In case the submission cannot be sent to the grader, return a warning, but record
// it for later evaluation
if ($problem->type != 'game' && !$submit->send()) {
    $status = 'WARNING';
    $message = 'Решението ще бъде тествано по-късно.';
}

// Otherwise print success and return the submit ID
printAjaxResponse(array(
    'status' => $status,
    'message' => $message,
    'id' => $submit->id,
    'path' => $solutionPath
));

?>