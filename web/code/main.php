<?php
session_start();

require_once('logic/config.php');
require_once('logic/user.php');
require_once('common.php');

$user = !isset($_SESSION['username']) ? new User() : User::get($_SESSION['username']);
if ($user == null) {
    $user = new User();
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
    case 'home':
        require_once('home.php');
        $page = new HomePage($user);
        break;
    case 'problems':
        require_once('problems.php');
        $page = new ProblemsPage($user);
        break;
    case 'queue':
        require_once('queue.php');
        $page = new QueuePage($user);
        break;
    case 'training':
        require_once('training.php');
        $page = new TrainingPage($user);
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
        break;
    default:
        require_once('error.php');
        $page = new ErrorPage($user);
}
$content = $page->getContent();

function createHead($page) {
    $meta = '
        <title>' . $page->getTitle() . '</title>
        <meta charset="utf-8">
        <meta name="author" content="Alexander Georgiev">
        <meta name="keywords" content="Програмиране,Информатика,Алгоритми,Структури Данни,Задачи,' .
                                      'Programming,Informatics,Algorithms,Data Structures,Problems">
        <link rel="shortcut icon" href="/favicon.ico" type="image/x-icon">
        <link rel="icon" href="/favicon.ico" type="image/x-icon">
        <link rel="stylesheet" type="text/css" href="/styles/style.css">
        <link rel="stylesheet" type="text/css" href="/styles/icons/css/font-awesome.css">
        <script src="/scripts/common.js"></script>
    ';
    foreach($page->getExtraStyles() as $style) {
        $meta = $meta . '
        <link rel="stylesheet" type="text/css" href="' . $style . '">';
    }
    foreach($page->getExtraScripts() as $script) {
        $meta = $meta . '
        <script src="' . $script .'"></script>';
    }
    return trim($meta) . '
    ';
}

require_once('page.html');
?>
