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
output('Filling "last seen" info...');
$numUpdated = 0;
$usersArr = $brain->getAllUsers();
$usersInfoArr = $brain->getAllUsersInfo();
for ($i = 0; $i < count($usersArr); $i++) {
    $user = User::instanceFromArray($usersArr[$i], $usersInfoArr[$i]);
    output('&nbsp&nbsp>> User "' . $user->username . '"');
    output('&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp-- last seen: "' . $user->lastSeen . '"');
    if ($user->lastSeen != '0000-00-00 00:00:00') {
        output('&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp-- already set! skipping...');
    } else {
        $numUpdated++;
        $submits = $brain->getUserSubmits($user->id);
        if (count($submits) > 0) {
            $user->lastSeen = $submits[count($submits) - 1]['submitted'];
            output('&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp-- setting to last submit: "' . $user->lastSeen . '"');
        } else {
            $user->lastSeen = $user->registered . ' 00:00:00';
            output('&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp-- setting register time: "' . $user->lastSeen . '"');
        }
        $brain->updateUserInfo($user);
    }
}
output('Finished! Updated ' . $numUpdated . ' users.');
*/


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