<?php
require_once('config.php');
require_once('widgets.php');
require_once('user.php');

session_start();

$user = User::get($_SESSION['userId']);
if ($user == null) {
    printAjaxResponse(array(
        'status' => 'ERROR',
        'reason' => 'Не сте влезли в системата.'
    ));
    exit();
}

switch ($_GET['action']) {
    case 'mail':
        require_once('mail.php');
        break;
    case 'publish':
        require_once('publish.php');
        break;
    case 'submit':
        require_once('submit.php');
        break;
    case 'modify':
        require_once('modify.php');
        break;
    case 'upload':
        require_once('upload.php');
        break;
    default:
        printAjaxResponse(array(
            'status' => 'WARNING',
            'reason' => 'Невалидно действие!'
        ));
}

?>