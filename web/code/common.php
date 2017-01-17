<?php

function showMessage($type, $message) {
    return '<script>showMessage("' . $type . '", "' . $message . '");</script>';
}

function redirect($url, $type = null, $message = null) {
    // Simple redirect
    if ($type == null) {
        header('Location: ' . $url);
    } else {

        // Redirect with arguments. Pass them using a POST form.
        echo '
        <!DOCTYPE html>
        <html>
            <head>
                <title>O(N)::Redirect</title>
                <meta charset="utf-8">
            </head>

            <body>
                <form id="redirectForm" action="' . $url . '" method="POST">
                    <input type="hidden" name="messageType" value="' . htmlentities($type) . '">
                    <input type="hidden" name="messageText" value="' . htmlentities($message) . '">
                </form>
                <script type="text/javascript">
                    document.getElementById("redirectForm").submit();
                </script>
            </body>
        </html>
        ';
    }
    exit();
}

function getUserLink($userName) {
    return '<a href="/users/' . $userName . '"><div class="user">' . $userName . '</div></a>';
}

function getProblemLink($problemId, $problemName) {
    return '<a href="/problems/' . $problemId . '"><div class="problem">' . $problemName . '</div></a>';
}

function userInfo($user) {
    if ($user->username != 'anonymous') {
        return '<div class="userInfo"><i class="fa fa-user-circle"></i> &nbsp;' . getUserLink($user->username) . '</div>';
    }
    return '';
}

function inBox($content, $extra=array()) {
    $classes = array_merge(array("box"), $extra);
    return '
            <div class="' . implode(";", $classes) . '">
                ' . $content . '
            </div>
    ';
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

?>