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
}

function validateData($problem) {
    if (!preg_match('/(*UTF8)^([0-9A-Za-zА-Яа-я.,!*\/ -]){1,32}$/', $problem->name))
        return 'Въведеното име на задача е невалидно!';

    if (!preg_match('/(*UTF8)^([A-Za-zА-Яа-я -]){1,32}$/', $problem->author))
        return 'Въведеното име на автор е невалидно!';

    if (!preg_match('/([0-9A-Za-z_-]){1,32}$/', $problem->folder))
        return 'Въведената папка е невалидна!';

    if (!preg_match('/(*UTF8)^([0-9A-Za-zА-Яа-я.,! -]){1,32}$/', $problem->origin))
        return 'Въведеният източник е невалиден!';

    if (!floatval($problem->timeLimit))
        return 'Въведеното ограничение по време е невалидно!';
    $problem->timeLimit = floatval($problem->timeLimit);

    if (!floatval($problem->memoryLimit))
        return 'Въведеното ограничение по памет е невалидно!';
    $problem->memoryLimit = floatval($problem->memoryLimit);

    if (!in_array($problem->type, $GLOBALS['PROBLEM_TYPES']))
        return 'Въведеният тип е невалиден!';

    if (!in_array($problem->difficulty, $GLOBALS['PROBLEM_DIFFICULTIES']))
        return 'Въведената сложност ' . $problem->difficulty . ' е невалидна!';

    foreach ($problem->tags as $tag) {
        if (!in_array($tag, $GLOBALS['PROBLEM_TAGS'])) {
            return 'Въведеният таг ' . $tag . ' е невалиден!';
        }
    }
    return '';
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
$problem->statement = $_POST['statement'];
$problem->checker = $_POST['checker'];
$problem->tester = $_POST['tester'];
$problem->tags = ($_POST['tags'] == '' ? array() : explode(',', $_POST['tags']));
$problem->addedBy = $user->username;

$errorMessage = validateData($problem);
if ($errorMessage != '') {
    printAjaxResponse(array(
        'status' => 'ERROR',
        'message' => $errorMessage
    ));
}

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
    $problem->id = intval($_POST['id']);
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
    'id' => $problem->id,
    'status' => 'OK',
    'message' => 'Задачата е записана успешно.'
));

?>