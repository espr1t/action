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

// New problem
if ($_POST['id'] == 'new') {
    if (!$problem->create()) {
        printAjaxResponse(array(
            'status' => 'ERROR',
            'message' => 'Възникна проблем при създаването на задачата.'
        ));
    }
}
// Updating existing problem
else {
    $problem->id = $_POST['id'];
    if (!$problem->update()) {
        printAjaxResponse(array(
            'status' => 'ERROR',
            'message' => 'Възникна проблем при записа на задачата.'
        ));
    }
}


// Update the tests
// TODO

// Everything seems okay
printAjaxResponse(array(
    'status' => 'OK',
    'message' => 'Задачата е записана успешно.'
));

?>