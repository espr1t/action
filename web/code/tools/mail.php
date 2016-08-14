<?php
require_once('../user.php');
require_once('../common.php');

session_start();

$SPAM_LIMIT = 20; // Messages per 24 hours

$user = User::getUser($_SESSION['username']);
if ($user == null || $user->getAccess() < $GLOBALS['ACCESS_REPORT_PROBLEM']) {
    printAjaxResponse(array(
        'status' => 'UNAUTHORIZED'
    ));
}

if (!passSpamProtection('mail_log.txt', $user, $SPAM_LIMIT)) {
    printAjaxResponse(array(
        'status' => 'SPAM'
    ));
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
        printAjaxResponse(array(
            'status' => 'OK'
        ));
    } else {
        printAjaxResponse(array(
            'status' => 'ERROR'
        ));
    }
}

?>