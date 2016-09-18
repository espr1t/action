<?php
require_once('brain.php');
require_once('config.php');
require_once('problem.php');

// User doesn't have access level needed for adding a testcase
if ($user->access < $GLOBALS['ACCESS_MODIFY_PROBLEM']) {
    printAjaxResponse(array(
        'status' => 'ERROR',
        'message' => 'Нямате права да променяте задачи.'
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
$testContent = preg_replace('~\R~u', "\n", $testContent);
file_put_contents($testPath, $testContent);

$testHash = md5($testContent);

$brain = new Brain();
$brain->updateTestFile($_POST['problemId'], $_POST['testPosition'], $testName, $testHash);

// Everything seems okay
printAjaxResponse(array(
    'status' => 'OK',
    'message' => 'Тестът е добавен успешно.',
    'hash' => $testHash
));

?>