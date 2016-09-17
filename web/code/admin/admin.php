<?php
session_start();

require_once('../logic/config.php');
require_once('../logic/user.php');
require_once('../common.php');

$user = !isset($_SESSION['userId']) ? new User() : User::get($_SESSION['userId']);
if ($user == null || $user->access < $GLOBALS['ACCESS_ADMIN_PAGES']) {
    header('Location: /forbidden');
    exit();
}

$actions = '';
if (isset($_GET['action'])) {
    if ($_GET['action'] == 'success') {
        $actions .= '<script>showMessage("INFO", "Successful operation!");</script>';
    } else if ($_GET['action'] == 'unsuccess') {
        $actions .= '<script>showMessage("ERROR", "Unsuccessful operation!");</script>';
    }
}

switch ($_GET['page']) {
    case 'news':
        require_once('news.php');
        $page = new AdminNewsPage($user);
        break;
    case 'problems':
        require_once('problems.php');
        $page = new AdminProblemsPage($user);
        break;
    default:
        require_once('../error.php');
        $page = new ErrorPage($user);
}
$content = $page->getContent();

require('../page.html');
?>