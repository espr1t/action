<?php
require_once(__DIR__ . '/../config.php');
require_once(__DIR__ . '/../db/brain.php');
require_once(__DIR__ . '/../entities/problem.php');

// User doesn't have access level needed for adding testcases
if ($user->access < $GLOBALS['ACCESS_EDIT_PROBLEM']) {
    printAjaxResponse(array(
        'status' => 'ERROR',
        'message' => 'Нямате права да добавяте тестове.'
    ));
}

$problem = Problem::get($_POST['problemId']);
if ($problem == null) {
    printAjaxResponse(array(
        'status' => 'ERROR',
        'message' => 'Няма задача с ID "' . $_POST['problemId'] . '"!'
    ));
}

$testName = $_POST['testName'];
$testPath = sprintf("%s/%s/%s/%s", $GLOBALS['PATH_PROBLEMS'], $problem->folder,
                                   $GLOBALS['PROBLEM_TESTS_FOLDER'], $testName);

// TODO: Maybe replace CRLF only if Linux is detected?
$testContent = base64_decode($_POST['testContent']);

//$testContent = preg_replace('~\R~u', "\n", $testContent);
file_put_contents($testPath, $testContent);

$testHash = md5($testContent);

$brain = new Brain();
$brain->updateTestFile($_POST['problemId'], $_POST['testPosition'], $testName, $testHash);

// Return the relative path to the thest so it is displayed properly on the frontend
$testPath = explode($_SERVER['DOCUMENT_ROOT'], $testPath)[1];

// Everything seems okay
printAjaxResponse(array(
    'status' => 'OK',
    'message' => 'Тестът е добавен успешно.',
    'hash' => $testHash,
    'path' => $testPath
));

?>