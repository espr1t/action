<?php
require_once(__DIR__ . '/../config.php');
require_once(__DIR__ . '/../common.php');
require_once(__DIR__ . '/../entities/user.php');

session_start();

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
        header('Location: /error');
        exit();
}
$content = $page->getContent();

require('../page.html');
?>
