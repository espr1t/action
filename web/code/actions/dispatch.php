<?php
require_once(__DIR__ . '/../common.php');
require_once(__DIR__ . '/../entities/user.php');

session_start();

$user = isset($_SESSION['userId']) ? User::get($_SESSION['userId']) : null;
if ($user == null) {
    printAjaxResponse(array(
        'status' => 'ERROR',
        'reason' => 'Не сте влезли в системата.'
    ));
}

switch ($_GET['action']) {

    case 'checkGrader':
        require_once('check_grader.php');
        break;

    case 'reportProblem':
        require_once('report_problem.php');
        break;

    case 'publishNews':
        require_once('publish_news.php');
        break;

    case 'sendSubmission':
        require_once('send_submission.php');
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

    case 'uploadSolution':
        require_once('upload_solution.php');
        break;

    case 'deleteSolution':
        require_once('delete_solution.php');
        break;

    case 'updateChecker':
        require_once('update_checker.php');
        break;

    case 'updateTester':
        require_once('update_tester.php');
        break;

    default:
        printAjaxResponse(array(
            'status' => 'WARNING',
            'reason' => 'Невалидно действие!'
        ));
}

?>