<?php
require_once(__DIR__ . '/brain.php');
require_once(__DIR__ . '/../common.php');
require_once(__DIR__ . '/../entities/user.php');

session_start();

function output($message) {
    echo $message . '<br>';
}

$user = getCurrentUser();
if ($user == null || $user->access < $GLOBALS['ADMIN_USER_ACCESS']) {
    output('You don\'t have permissions to do this action.');
    exit(0);
}


$brain = new Brain();

/*
output('Migrating user info...');
$users = $brain->getAllUsers();
foreach ($users as $u) {
    $user = User::instanceFromArray($u);
    if ($brain->addUserInfo($user)) {
        output('  >> added user info for user "' . $user->username . '"');
    } else {
        output('ERROR: cannot add user info for user "' . $user->username . '"');
    }
}
*/

/*
output('Migrating user credentials...');
$users = $brain->getAllUsers();
foreach ($users as $user) {
    if ($brain->addCredentials($user['id'], $user['username'], $user['password'], $user['loginKey'])) {
        output('  >> added credentials for user "' . $user['username'] . '"');
    } else {
        output('ERROR: cannot add credentials for user "' . $user['username'] . '"');
    }
}
*/


?>