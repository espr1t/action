<?php
require_once('brain.php');
require_once('config.php');
require_once('problem.php');

// User doesn't have access level needed for modifying a problem
if ($user->access < $GLOBALS['ACCESS_MODIFY_PROBLEM']) {
    printAjaxResponse(array(
        'status' => 'ERROR',
        'message' => 'Нямате права да променяте задачи.'
    ));
    exit();
}

$problem = new Problem();
$problem->name = $_POST['name'];
$problem->author = $_POST['author'];
$problem->folder = $_POST['folder'];
$problem->origin = $_POST['origin'];
$problem->timeLimit = $_POST['timeLimit'];
$problem->memoryLimit = $_POST['memoryLimit'];
$problem->type = $_POST['type'];
$problem->difficulty = $_POST['difficulty'];
$problem->checker = $_POST['checker'];
$problem->tester = $_POST['tester'];
$problem->tags = $_POST['tags'];
$problem->addedBy = $_POST['addedBy'];

$brain = new Brain();


// Updating existing problem
if ($_POST['id'] != 'new') {
    $problem->id = $_POST['id'];
}
// New problem
else {
    // Add the problem to the database.
    $result = $brain->addProblem($problem);
    if (!$result) {
        printAjaxResponse(array(
            'status' => 'ERROR',
            'message' => 'Възникна проблем при създаването на задачата.'
        ));
    }
    $problem->id = $result;

    // Create a folder and meta information.
    $problemPath = sprintf("%s/%s",  $GLOBALS['PATH_PROBLEMS'], $problem->folder);
    if (!mkdir($problemPath)) {
        printAjaxResponse(array(
            'status' => 'ERROR',
            'message' => 'Не може да бъде създадена директория за задачата.'
        ));
    }
}

// Update the problem info in the database
$result = $brain->updateProblem($problem);
if (!$result) {
    printAjaxResponse(array(
        'status' => 'ERROR',
        'message' => 'Възникна проблем при записа на задачата.'
    ));
}

// Update the problem meta information (JSON file in the problem folder)
$infoPath = sprintf("%s/%s/%s",  $GLOBALS['PATH_PROBLEMS'], $problem->folder, $GLOBALS['PROBLEM_INFO_FILENAME']);
$infoFile = fopen($infoPath, 'w');
if (!$infoFile) {
    printAjaxResponse(array(
        'status' => 'ERROR',
        'message' => 'Възникна проблем при записа на мета информацията.'
    ));
}
fwrite($infoFile, json_encode($problem->arrayFromInstance(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

// Update the problem statement (HTML file in the problem folder)
$statementPath = sprintf("%s/%s/%s",  $GLOBALS['PATH_PROBLEMS'], $problem->folder, $GLOBALS['PROBLEM_STATEMENT_FILENAME']);
file_put_contents($statementPath, $_POST['statement']);

// Update the tests
// TODO

// Everything seems okay
printAjaxResponse(array(
    'status' => 'OK',
    'message' => 'Задачата е записана успешно.'
));

?>