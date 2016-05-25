<?php
require_once('common.php');
require_once('user.php');

$user = new User();

switch ($_GET['page']) {
    case 'home':
        require_once('home.php');
        $page = new HomePage($user);
        break;
    case 'problems':
        require_once('problems.php');
        $page = new ProblemsPage($user);
        break;
    case 'contests':
        require_once('contests.php');
        $page = new ContestsPage($user);
        break;
    case 'training':
        require_once('training.php');
        $page = new TrainingPage($user);
        break;
    case 'ranking':
        require_once('ranking.php');
        $page = new RankingPage($user);
        break;
    case 'login':
        require_once('login.php');
        $page = new LoginPage($user);
        break;
    case 'logout':
        require_once('logout.php');
        $page = new LogoutPage($user);
        break;
    default:
        require_once('error.php');
        $page = new ErrorPage($user);
}

function createHead($page) {
    $meta = '
        <title>' . $page->getTitle() . '</title>
        <meta charset="utf-8">
        <meta name="author" content="Alexander Georgiev">
        <meta name="keywords" content="Програмиране,Информатика,Алгоритми,Структури Данни,Задачи,' .
                                      'Programming,Informatics,Algorithms,Data Structures,Problems">
        <link rel="shortcut icon" href="/favicon.ico" type="image/x-icon">
        <link rel="icon" href="/favicon.ico" type="image/x-icon">
        <link rel="stylesheet" type="text/css" href="/styles/style.css">';
    foreach($page->getExtraStyles() as $style) {
        $meta = $meta . '
        <link rel="stylesheet" type="text/css" href="' . $style . '">';
    }
    foreach($page->getExtraScripts() as $script) {
        $meta = $meta . '
        <script type="text/javascript" src="' . $script .'"></script>';
    }
    return trim($meta) . newLine();
}

?>
<!DOCTYPE html>
<html>
    <head>
        <?php echo createHead($page); ?>
    </head>

    <body>
        <!-- Header with menu -->
        <div class="header" id="head">
            <div class="menu" id="menu">
                <table class="menu" id="menuTable">
                    <tr>
                        <td class="button"><a href="/home"><div class="button">HOME</div></a></td>
                        <td class="button"><a href="/problems"><div class="button">PROBLEMS</div></a></td>
                        <td class="button"><a href="/contests"><div  class="button">CONTESTS</div></a></td>
                        <td class="logo">
                            <div class="logo">
                                act!O<span style="font-size: 0.8em;">(</span>n<span style="font-size: 0.8em;">)</span>
                            </div>
                        </td>
                        <td class="button"><a href="/training"><div class="button">TRAINING</div></a></td>
                        <td class="button"><a href="/ranking"><div class="button">RANKING</div></a></td>
                        <td class="button"><?php
                                if ($user->getId() == 0) {
                                    echo '<a href="/login"><div class="button">LOGIN</div></a>';
                                } else {
                                    echo '<a href="/logout"><div class="button">LOGOUT</div></a>';
                                }
                            ?></td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Actual content -->
        <div class="main" id="main">
            <?php echo $page->getContent(); ?>
        </div>
    
        <!-- Footer with copyright info -->
        <div class="footer" id="footer">
            help | about | create a contest | sponsor a contest | report a problem
        </div>
        <?php echo $page->getExtraCode(); ?>
    </body>
</html>