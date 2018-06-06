<?php
require_once('config.php');
require_once('db/brain.php');

function swap(&$var1, &$var2) {
    $temp = $var1;
    $var1 = $var2;
    $var2 = $temp;
}

function popcount($num) {
    $bits = 0;
    for (; $num > 0; $num &= ($num - 1))
        $bits += 1;
    return $bits;
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

function printAjaxResponse($response) {
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit(0);
}

function saltHashPassword($password) {
    return md5($password . $GLOBALS['PASSWORD_SALT']);
}

function validateReCaptcha() {
    if (!isset($_POST['g-recaptcha-response']))
        return false;

    $url = 'https://www.google.com/recaptcha/api/siteverify';
    $data = array(
        'secret' => $GLOBALS['RE_CAPTCHA_KEY'],
        'response' => $_POST['g-recaptcha-response'],
        'remoteip' => $_SERVER['REMOTE_ADDR']
    );
    $options = array(
        'http' => array(
            'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
            'method'  => 'POST',
            'content' => http_build_query($data)
        )
    );
    $context  = stream_context_create($options);
    $response = file_get_contents($url, false, $context);
    if ($response === false) {
        error_log('ERROR: Could not call re-CAPTCHA server.');
        return false;
    }
    $result = json_decode($response, true);
    return $result['success'];
}

function validateUsername($username) {
    return preg_match('/^\w[\w.]{1,15}$/', $username);
}

function validateName($name) {
    return preg_match('/(*UTF8)^([A-Za-zА-Яа-я]|-){1,32}$/', $name);
}

function validatePassword($password) {
    return preg_match('/^.{1,32}$/', $password);
}

function validateEmail($email) {
    return $email == '' || preg_match('/^[A-Za-z0-9_.+*=$^-]+@[a-zA-Z0-9-]+\.[a-zA-Z0-9-.]+$/', $email);
}

function validateDate($date) {
    return $date == '' || preg_match('/^\d\d\d\d-\d\d-\d\d$/', $date);
}

function validatePlace($town) {
    return $town == '' || preg_match('/(*UTF8)^[A-Za-zА-Яа-я ]{1,32}$/', $town);
}

function validateGender($gender) {
    return $gender == '' || preg_match('/^male|female$/', $gender);
}

function getValue($array, $key) {
    if (!array_key_exists($key, $array)) {
        error_log('Array does not contain value for key "'. $key . '"!');
        return null;
    }
    return $array[$key];
}

function passSpamProtection($user, $type, $limit) {
    $brain = new Brain();
    $brain->refreshSpamCounters(time() - $GLOBALS['SPAM_INTERVAL']);
    if ($brain->getSpamCounter($user, $type) < $limit) {
        $brain->incrementSpamCounter($user, $type, time());
        return true;
    }
    return false;
}

function canSeeProblem($user, $problemVisible, $problemId) {
    if ($problemVisible)
        return true;
    return $user->access >= $GLOBALS['ACCESS_HIDDEN_PROBLEMS'];
}

function getGameUrlName($problemName) {
    $urlName = strtolower($problemName);
    $urlName = str_replace(' ', '-', $urlName);
    return $urlName;
}

function getGameLink($problemName) {
    return '/games/' . getGameUrlName($problemName);
}

function getUserLink($userName, $unofficial=array()) {
    $suffix = '';
    if (in_array($userName, $unofficial))
        $suffix = '*';
    return '<a href="/users/' . $userName . '"><div class="user">' . $userName . $suffix . '</div></a>';
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
        <link rel="shortcut icon" type="image/x-icon" href="/images/favicon_blue_128.png">
        <link rel="icon" href="/favicon.ico" type="image/x-icon">
        <link rel="stylesheet" type="text/css" href="/styles/style.css">
        <link rel="stylesheet" type="text/css" href="/styles/icons/css/fontawesome-all.min.css">
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

function getWaitingTimes($user, $problem, &$remainPartial, &$remainFull) {
    $brain = new Brain();
    $submits = $brain->getUserSubmits($user->id, $problem->id);
    $lastPartial = 0;
    $lastFull = 0;
    foreach ($submits as $submit) {
        if ($submit['status'] != $GLOBALS['STATUS_COMPILATION_ERROR']) {
            if ($submit['full'] == '1') {
                $lastFull = max(array($lastFull, strtotime($submit['submitted'])));
            } else {
                $lastPartial = max(array($lastPartial, strtotime($submit['submitted'])));
            }
        }
    }
    $remainPartial = $problem->waitPartial * 60 - (time() - $lastPartial);
    $remainFull = $problem->waitFull * 60 - (time() - $lastFull);
}

function getSubmitWithChecks($user, $submitId, $problem, $redirectUrl) {
    if (!is_numeric($submitId)) {
        redirect($redirectUrl, 'ERROR', 'Не съществува решение с този идентификатор!');
    }

    $submit = Submit::get($submitId);
    if ($submit == null) {
        redirect($redirectUrl, 'ERROR', 'Не съществува решение с този идентификатор!');
    }

    if ($user->access < $GLOBALS['ACCESS_SEE_SUBMITS']) {
        if ($submit->userId != $user->id) {
            redirect($redirectUrl, 'ERROR', 'Нямате достъп до това решение!');
        }

        if ($submit->problemId != $problem->id) {
            redirect($redirectUrl, 'ERROR', 'Решението не е по поисканата задача!');
        }
    }
    return $submit;
}

function getStatusColor($status) {
    $color = 'black';
    switch ($status) {
        case $GLOBALS['STATUS_ACCEPTED']:
            $color = 'green';
            break;
        case $GLOBALS['STATUS_INTERNAL_ERROR']:
            $color = 'black';
            break;
        default:
            $color = strlen($status) == 1 ? 'gray' : 'red';
    }
    return $color;
}

function getSourceSection($submit, $addslashes=true) {
    $source = $submit->source;
    $source = htmlspecialchars($source, ENT_NOQUOTES | ENT_HTML5);
    if ($addslashes) {
        $source = addslashes($source);
    }
    $source = str_replace('`', '&#96;', $source);

    return '
        <div class="centered" id="sourceLink">
            <a onclick="displaySource();">Виж кода</a>
        </div>
        <div style="display: none;" id="sourceField">
            <div class="right smaller"><a onclick="copyToClipboard();">копирай</a></div>
            <div class="show-source-box">
                <code id="source">' . $source . '</code>
            </div>
        </div>
    ';
}

?>
