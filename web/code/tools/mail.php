<?php
require_once('../user.php');
require_once('../common.php');

session_start();

$SPAM_LIMIT = 20; // Messages per 24 hours
$SPAM_INTERVAL = 86400; // Seconds in 24 hours

$user = User::getUser($_SESSION['username']);
if ($user == null || $user->getAccess() < $GLOBALS['ACCESS_REPORT_PROBLEM']) {
    printResponse('NOT AUTHORIZED');
}

$curTime = time();
$spamCount = 0;

$logs = preg_split('/\r\n|\r|\n/', file_get_contents('mail.txt'));
$length = count($logs);
$out = fopen('mail.txt', 'w');
for ($i = 0; $i < $length; $i = $i + 1) {
    $username = '';
    $timestamp = 0;
    // Use double-quotes as single quotes have problems with \r and \n
    if (sscanf($logs[$i], "%s %d", $username, $timestamp) == 2) {
        if ($curTime - $timestamp < $SPAM_INTERVAL) {
            fprintf($out, "%s %d\n", $username, $timestamp);
            if ($user->getUsername() == $username) {
                $spamCount = $spamCount + 1;
            }
        }
    }
}
if ($spamCount < $SPAM_LIMIT) {
    fprintf($out, "%s %d\n", $user->getUsername(), $curTime);
}
fclose($out);

if ($spamCount >= $SPAM_LIMIT) {
    printResponse('SPAM');
} else {
    // Use double-quotes as single quotes have problems with \r and \n
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: action@informatika.bg\r\n";

    $address = "thinkcreative@outlook.com";
    $subject = "Problem report from " . $user->getUsername();
    $text = "<html><body>" .
            "<b>User:</b> " . $user->getUsername() . "<br>" .
            "<b>Link:</b> " . $_POST['link'] . "<br>" .
            "<b>Problem Description:</b><br> " . 
            "==================================================<br>" .
            nl2br($_POST['problem']) .
            "</body></html>";

    if (mail($address, $subject, $text, $headers)) {
        printResponse('OKAY');
    } else {
        printResponse('ERROR');
    }
}

function printResponse($status) {
    $response = array(
        'status' => $status
    );
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
?>