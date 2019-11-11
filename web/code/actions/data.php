<?php
require_once(__DIR__ . '/../config.php');
require_once(__DIR__ . '/../common.php');
require_once(__DIR__ . '/../db/brain.php');
require_once(__DIR__ . '/../entities/user.php');

session_start();

$user = isset($_SESSION['userId']) ? User::get($_SESSION['userId']) : null;
if ($user == null) {
    printAjaxResponse(array(
        'status' => 'ERROR',
        'reason' => 'Не сте влезли в системата.'
    ));
}

function getUsersBasicInfo() {
    $brain = new Brain();
    $users = $brain->getAllUsers();
    $usersBasicInfo = array();
    foreach ($users as $user) {
        if ($user['id'] >= 1) {
            array_push($usersBasicInfo, array(
                'id' => $user['id'],
                'username' => $user['username'],
                'name' => $user['name']
            ));
        }
    }
    return $usersBasicInfo;
}

switch ($_GET['type']) {

    case 'users':
        printAjaxResponse(array(
            'status' => 'OK',
            'users' => getUsersBasicInfo()
        ));

    default:
        printAjaxResponse(array(
            'status' => 'WARNING',
            'reason' => 'Невалидно действие!'
        ));
}

?>