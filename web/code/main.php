<?php
require_once('entities/user.php');
require_once('config.php');
require_once('common.php');
require_once('admin/achievements.php');

session_start();

$user = getCurrentUser();
if ($user == null) {
    $user = new User();
}

// Update activity statistics
$user->updateStats();

// Log the opened page
$logMessage = sprintf('User %s opened page "%s".', $user->username, $_SERVER['REQUEST_URI']);
write_log($GLOBALS['LOG_PAGE_VIEWS'], $logMessage);

# Show notifications (set in $_SESSION from server or $_POST from client)
$showNotification = '';
if (isset($_SESSION['notificationType']) && isset($_SESSION['notificationText'])) {
    $showNotification .= '<script>showNotification("' . $_SESSION['notificationType'] . '", "' . $_SESSION['notificationText'] . '");</script>';
    unset($_SESSION['notificationType']);
    unset($_SESSION['notificationText']);
}
if (isset($_POST['notificationType']) && isset($_POST['notificationText'])) {
    $showNotification .= '<script>showNotification("' . $_POST['notificationType'] . '", "' . $_POST['notificationText'] . '");</script>';
    $showNotification .= '<script>setTimeout(function() {window.location=window.location;}, 3000);</script>';
}


// Decide which Page should generate the content
switch ($_GET['page']) {
    case 'home':
        require_once('home.php');
        $page = new HomePage($user);
        break;
    case 'problems':
        require_once('problems.php');
        $page = new ProblemsPage($user);
        break;
    case 'games':
        require_once('games.php');
        $page = new GamesPage($user);
        break;
    case 'training':
        require_once('training.php');
        $page = new TrainingPage($user);
        break;
    case 'queue':
        require_once('queue.php');
        $page = new QueuePage($user);
        break;
    case 'ranking':
        require_once('ranking.php');
        $page = new RankingPage($user);
        break;
    case 'messages':
        require_once('messages.php');
        $page = new MessagesPage($user);
        break;
    case 'profile':
        require_once('profile.php');
        $page = new ProfilePage($user);
        $page->init();
        break;
    case 'login':
        require_once('login.php');
        $page = new LoginPage($user);
        break;
    case 'logout':
        $user->logOut();
        break;
    case 'register':
        require_once('register.php');
        $page = new RegisterPage($user);
        break;
    case 'reset':
        require_once('reset.php');
        $page = new ResetPage($user);
        break;
    case 'about':
        require_once('about.php');
        $page = new AboutPage($user);
        break;
    case 'help':
        require_once('help.php');
        $page = new HelpPage($user);
        break;
    case 'stats':
        require_once('stats.php');
        $page = new StatsPage($user);
        $page->init();
        break;
    case 'error':
        require_once('error.php');
        $page = new ErrorPage($user);
        break;
    case 'forbidden':
        require_once('forbidden.php');
        $page = new ForbiddenPage($user);
        break;
    default:
        require_once('error.php');
        $page = new ErrorPage($user);
}
$content = $page->getContent();
$achievementsContent = getAchievementsContent();

if (!isset($_GET['print'])) {
    require('page.html');
} else {
    require('print.html');
}

?>
