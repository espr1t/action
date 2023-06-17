<?php
require_once("entities/user.php");
require_once("config.php");
require_once("common.php");
require_once("error.php");
require_once("admin/achievements.php");

$startTime = microtime(true);

session_start();

$user = getCurrentUser();
if ($user == null) {
    $user = new User();
}

// Show notifications (set in $_SESSION from server or $_POST from client)
$showNotification = getNotification();

if ($GLOBALS["MAINTENANCE_MODE"] && $user->getAccess() < $GLOBALS["ADMIN_USER_ACCESS"]) {
    $page = new MaintenancePage($user);
    $content = $page->getContent();
    $achievementsContent = "";
} else {
    // Update activity statistics
    $user->updateStats();

    // Log the opened page
    write_log($GLOBALS["LOG_PAGE_VIEWS"], "User {$user->getUsername()} opened page {$_SERVER["REQUEST_URI"]}.");


    // Decide which Page should generate the content
    $page = new ErrorPage($user);

    switch ($_GET["page"]) {
        case "home":
            require_once("home.php");
            $page = new HomePage($user);
            break;
        case "problems":
            require_once("problems.php");
            $page = new ProblemsPage($user);
            break;
        case "games":
            require_once("games.php");
            $page = new GamesPage($user);
            break;
        case "training":
            require_once("training.php");
            $page = new TrainingPage($user);
            break;
        case "status":
            require_once("status.php");
            $page = new StatusPage($user);
            break;
        case "ranking":
            require_once("ranking.php");
            $page = new RankingPage($user);
            break;
        case "messages":
            require_once("messages.php");
            $page = new MessagesPage($user);
            break;
        case "profile":
            require_once("profile.php");
            $page = new ProfilePage($user);
            $page->init();
            break;
        case "login":
            require_once("login.php");
            $page = new LoginPage($user);
            break;
        case "logout":
            $user->logOut();
            break;
        case "register":
            require_once("register.php");
            $page = new RegisterPage($user);
            break;
        case "reset":
            require_once("reset.php");
            $page = new ResetPage($user);
            break;
        case "about":
            require_once("about.php");
            $page = new AboutPage($user);
            break;
        case "help":
            require_once("help.php");
            $page = new HelpPage($user);
            break;
        case "stats":
            require_once("stats.php");
            $page = new StatsPage($user);
            $page->init();
            break;
        case "error":
            require_once("error.php");
            $page = new ErrorPage($user);
            break;
        case "forbidden":
            require_once("error.php");
            $page = new ForbiddenPage($user);
            break;
    }
    $content = $page->getContent();
    $achievementsContent = getAchievementsContent();
}


// printf("Total execution time: %.3fs\n<br>", microtime(true) - $startTime);

if (!isset($_GET["print"])) {
    require("page.html");
} else {
    require("print.html");
}

?>
