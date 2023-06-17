<?php
require_once(__DIR__ . "/../config.php");
require_once(__DIR__ . "/../common.php");
require_once(__DIR__ . "/../entities/user.php");

session_start();

$user = getCurrentUser();
if ($user == null || $user->getAccess() < $GLOBALS["ACCESS_ADMIN_PAGES"]) {
    redirect("/forbidden", "ERROR", "Нямате права да достъпите тази страница.");
}

# Show notifications (set in $_SESSION from server or $_POST from client)
$showNotification = getNotification();

switch ($_GET["page"]) {

    case "news":
        require_once("news.php");
        $page = new AdminNewsPage($user);
        break;

    case "problems":
        require_once("problems.php");
        $page = new AdminProblemsPage($user);
        break;

    case "regrade":
        require_once("regrade.php");
        $page = new AdminRegradePage($user);
        break;

    case "achievements":
        require_once("achievements.php");
        $page = new AdminAchievementsPage($user);
        break;

    case "history":
        require_once("history.php");
        $page = new AdminHistoryPage($user);
        break;

    case "messages":
        require_once("messages.php");
        $page = new AdminMessagesPage($user);
        break;

    case "delete_user":
        require_once("delete_user.php");
        $page = new AdminDeleteUserPage($user);
        break;

        case "tests":
        require_once("../../tests/language_detector.php");
        $page = new LanguageDetectorPage($user);
        break;

    default:
        redirect("/error", "ERROR", "Невалидна страница.");
        exit();
}

$isAdminPage = true;
$achievementsContent = "";
$content = $page->getContent();

require("../page.html");

?>
