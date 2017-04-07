<?php

function swap(&$var1, &$var2) {
    $temp = $var1;
    $var1 = $var2;
    $var2 = $temp;
}

function showMessage($type, $message) {
    return '<script>showMessage("' . $type . '", "' . $message . '");</script>';
}

function redirect($url, $type = null, $message = null) {
    // Redirect with arguments (pass them using session data).
    if ($type != null && $message != null) {
        $_SESSION['messageType'] = $type;
        $_SESSION['messageText'] = $message;
    }
    header('Location: ' . $url);
    exit();
}

function getGameUrlName($problemName) {
    $urlName = strtolower($problemName);
    $urlName = str_replace(' ', '-', $urlName);
    return $urlName;
}

function getGameLink($problemName) {
    return '/games/' . getGameUrlName($problemName);
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
        <link rel="shortcut icon" type="image/x-icon" href="/images/favicon_128.png">
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

// TODO: Handle properly Python errors once supported
function prettyPrintCompilationErrors($submit) {
    $pathToSandbox = sprintf('sandbox/submit_%06d/', $submit->id);
    $submit->message = str_replace($pathToSandbox, '', $submit->message);
    $submit->message = str_replace('Compilation error: ', '', $submit->message);
    $submit->message = str_replace('source.cpp: ', '', $submit->message);
    $submit->message = str_replace('source.java:', 'Line ', $submit->message);

    $errorsList = '';
    foreach (explode('^', $submit->message) as $errors) {
        $first = true;
        foreach (explode('source.cpp', $errors) as $error) {
            $error = ltrim($error, ':');
            if (strlen($error) > 1) {
                $errorsList .= '<li><code>' . str_replace('\\', '\\\\', htmlspecialchars(trim($error))) . '</code></li>';
            }
            if ($first) {
                $errorsList .= '<ul>';
                $first = false;
            }
        }
        $errorsList .= '</ul>';
    }
    return '
        <div style="border: 1px dashed #333333; padding: 0.5rem;">
            <ul>
            ' . $errorsList . '
            </ul>
        </div>
    ';
}

?>