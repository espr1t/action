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

$achievementsContent = '';
$newAchievements = AdminAchievementsPage::getNewAchievements($user);
if (count($newAchievements) > 0) {
    $achievementsFile = file_get_contents($GLOBALS['PATH_ACHIEVEMENTS'] . '/achievements.json');
    $achievementsData = json_decode($achievementsFile, true);

    foreach ($GLOBALS['newAchievements'] as $achievement) {
        $icon = '';
        $title = '';
        $description = '';
        foreach ($GLOBALS['achievementsData'] as $info) {
            if ($info['key'] == $achievement) {
                $icon = $info['icon'];
                $title = $info['title'];
                $description = $info['description'];
                break;
            }
        }
        if ($icon == '') {
            error_log('Could not find achievement with key "' . $achievement . '"!');
            continue;
        }
        $GLOBALS['achievementsContent'] .= '
            <div class="achievementWrapper" id="' . $achievement . '">
                <div class="achievementBoxLeft"></div>
                <div class="achievementBoxLeftIn"></div>
                <div class="achievementBox">
                    <div class="achievementTagLeft">
                        ACHIEVEMENT
                    </div>
                    <div class="achievementTagRight">
                        UNLOCKED
                    </div>

                    <div class="achievementBadge">
                        <i class="fas fa-trophy"></i>
                    </div>
                    <div class="achievementTitleDividerTop"></div>
                    <div class="achievementTitle">
                        ' . $title . '
                    </div>
                    <div class="achievementTitleDividerBottom"></div>
                    <div class="achievementDescription">
                        -- ' . $description . ' --
                    </div>
                </div>
                <div class="achievementBoxRightIn"></div>
                <div class="achievementBoxRight"></div>
                <div class="achievementBoxShadowTB"></div>
                <div class="achievementBoxShadowLR"></div>
                <div class="achievementOverlay"></div>

                <script>
                    registerAchievementHandlers(\'' . $achievement . '\');
                    setTimeout(hideAchievements.bind(this, \'' . $achievement . '\'), 10000);
                </script>
            </div>
        ';
    }
}

require('page.html');
?>
