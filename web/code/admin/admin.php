<?php
require_once(__DIR__ . '/../config.php');
require_once(__DIR__ . '/../common.php');
require_once(__DIR__ . '/../entities/user.php');

session_start();

$user = getCurrentUser();
if ($user == null || $user->access < $GLOBALS['ACCESS_ADMIN_PAGES']) {
    header('Location: /forbidden');
    exit();
}

# Show notifications (set in $_SESSION from server or $_POST from client)
$showNotification = getNotification();

switch ($_GET['page']) {

    case 'news':
        require_once('news.php');
        $page = new AdminNewsPage($user);
        break;

    case 'problems':
        require_once('problems.php');
        $page = new AdminProblemsPage($user);
        break;

    case 'regrade':
        require_once('regrade.php');
        $page = new AdminRegradePage($user);
        break;

    case 'achievements':
        require_once('achievements.php');
        $page = new AdminAchievementsPage($user);
        break;

    case 'history':
        require_once('history.php');
        $page = new AdminHistoryPage($user);
        break;

    case 'messages':
        require_once('messages.php');
        $page = new AdminMessagesPage($user);
        break;

    default:
        header('Location: /error');
        exit();
}
$content = $page->getContent();
$achievementsContent = '';

$isAdminPage = true;
require('../page.html');

?>
