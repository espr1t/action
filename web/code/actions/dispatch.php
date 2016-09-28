<?php
require_once(__DIR__ . '/../entities/user.php');
require_once(__DIR__ . '/../entities/widgets.php');

session_start();

$user = User::get($_SESSION['userId']);
if ($user == null) {
    printAjaxResponse(array(
        'status' => 'ERROR',
        'reason' => 'Не сте влезли в системата.'
    ));
}

switch ($_GET['action']) {

    case 'reportProblem':
        require_once('report_problem.php');
        break;

    case 'publishNews':
        require_once('publish_news.php');
        break;

    case 'submitSolution':
        require_once('submit_solution.php');
        break;

    case 'editProblem':
        require_once('edit_problem.php');
        break;

    case 'uploadTest':
        require_once('upload_test.php');
        break;

    case 'deleteTest':
        require_once('delete_test.php');
        break;

    default:
        printAjaxResponse(array(
            'status' => 'WARNING',
            'reason' => 'Невалидно действие!'
        ));
}

?>