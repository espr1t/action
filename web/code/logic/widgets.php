<?php

function saltHashPassword($password) {
    return md5($password . $GLOBALS['PASSWORD_SALT']);
}

function getValue($array, $key) {
    if (!array_key_exists($key, $array)) {
        error_log('User info does not contain value for key "'. $key . '"!');
        return null;
    }
    return $array[$key];
}

function passSpamProtection($fileName, $user, $limit) {
    $curTime = time();
    $logs = preg_split('/\r\n|\r|\n/', file_get_contents($fileName));
    $length = count($logs);
    $spamCount = 0;
    $out = fopen($fileName, 'w');
    for ($i = 0; $i < $length; $i = $i + 1) {
        $username = '';
        $timestamp = 0;
        // Use double-quotes as single quotes have problems with \r and \n
        if (sscanf($logs[$i], "%s %d", $username, $timestamp) == 2) {
            if ($curTime - $timestamp < $GLOBALS['SPAM_INTERVAL']) {
                fprintf($out, "%s %d\n", $username, $timestamp);
                if ($user->username == $username) {
                    $spamCount = $spamCount + 1;
                }
            }
        }
    }
    if ($spamCount < $limit) {
        fprintf($out, "%s %d\n", $user->username, $curTime);
    }
    fclose($out);
    return $spamCount < $limit;
}

function printAjaxResponse($response) {
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}

?>