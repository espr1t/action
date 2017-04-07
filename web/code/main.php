<?php
require_once('entities/user.php');
require_once('config.php');
require_once('common.php');

session_start();

$user = null;
if (isset($_SESSION['userId'])) {
    $user = User::get($_SESSION['userId']);
} else if (isset($_COOKIE[$GLOBALS['COOKIE_NAME']])) {
    // Scan all users for a one with a loginKey matching the one stored in the cookie
    list($loginKey, $hmac) = explode(':', $_COOKIE[$GLOBALS['COOKIE_NAME']], 2);
    // This, unfortunately, wouldn't work for non-static IPs =/
    if ($hmac == hash_hmac('md5', $loginKey, $_SERVER['REMOTE_ADDR'])) {
        $user = User::getByLoginKey($loginKey);
        if ($user != null) {
            $_SESSION['userId'] = $user->id;
        }
    }
}

if ($user == null) {
    $user = new User();
}

$showMessage = '';
if (isset($_SESSION['messageType']) && isset($_SESSION['messageText'])) {
    $showMessage .= '<script>showMessage("' . $_SESSION['messageType'] . '", "' . $_SESSION['messageText'] . '");</script>';
    unset($_SESSION['messageType']);
    unset($_SESSION['messageText']);
}

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

require('page.html');
?>
