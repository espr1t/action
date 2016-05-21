<?php
require_once('common.php');

function createHead($title, $keywords, $extraStyles, $extraScripts) {
    $meta = '
    <meta charset="utf-8">
    <title>' . $title . '</title>
    <meta name="author" content="Alexander Georgiev">';
    if (count($keywords) > 0) {
        $meta = $meta . '
    <meta name="keywords" content="' . $keywords[0];
        for ($i = 1; $i < count($keywords); $i = $i + 1) {
            $meta = $meta . ', ' . $keywords[$i];
        }
        $meta = $meta . '">';
    }
    $meta = $meta . '
    <link rel="shortcut icon" href="/favicon.ico" type="image/x-icon">
    <link rel="icon" href="/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" type="text/css" href="/styles/style.css">
    <link href="https://fonts.googleapis.com/css?family=Nothing+You+Could+Do|Zeyada|Euphoria+Script|Kristi|Ruthie|Lovers+Quarrel" rel="stylesheet" type="text/css">
    <link href="https://fonts.googleapis.com/css?family=Raleway" rel="stylesheet" type="text/css">';
    foreach($extraStyles as $style) {
        $meta = $meta . '
    <link rel="stylesheet" type="text/css" href="' . $style . '">';
    }
    foreach($extraScripts as $script) {
        $meta = $meta . '
    <script type="text/javascript" src="' . $script .'"></script>';
    }
    return trim($meta) . newLine();
}

function getMenu() {
    return "";
}

function getContent() {
    return "";
}

$title = "Do(n)e Arena";
$keywords = array("Програмиране", "Информатика", "Алгоритми", "Структури Данни", "Задачи",
                  "Programming", "Informatics", "Algorithms", "Data Structures", "Problems");
$extraStyles = array();
$extraScripts = array();
$extraCode = '';
//$content = getContent();

?>
<!DOCTYPE html>
<html>
    <head>
        <?php echo createHead($title, $keywords, $extraStyles, $extraScripts); ?>
    </head>

    <body>
        <!-- Header with menu -->
        <div class="header" id="head">
            <div class="menu" id="menu">
                <table class="menu" id="menuTable">
                    <tr>
                        <td class="button">HOME</td>
                        <td class="button">PROBLEMS</td>
                        <td class="button">CONTESTS</td>
                        <td class="logo">
                            <div class="logo">
                                do<div style="font-size: 0.25em; display: inline; top: 0.25em;">(N)</div>e</td>
                            </div>
                        <td class="button">TRAINING</td>
                        <td class="button">USERS</td>
                        <td class="button">LOGIN</td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Main page with actual content -->
        <div class="main" id="main">
            <div class="container">
                <div class="loginInfo">
                    logged in as: <div class="user">anonymous</div>
                </div>
                Under development.
                <?php echo $content; ?>
            </div>
        </div>
    
        <!-- Footer with copyright info -->
        <div class="footer" id="footer">
            help | about | become an admin | sponsor a contest | report a problem
        </div>
        <?php echo $extraCode; ?>
    </body>
</html>