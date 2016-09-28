<?php
require_once(__DIR__ . '/../config.php');
require_once(__DIR__ . '/../entities/widgets.php');

// User doesn't have access level needed for sending a mail
if ($user->access < $GLOBALS['ACCESS_REPORT_PROBLEM']) {
    printAjaxResponse(array(
        'status' => 'ERROR',
        'message' => 'Нямате права да изпращате съобщения.'
    ));
}

// User has sent too many messages in the last day
if (!passSpamProtection($user, $GLOBALS['SPAM_EMAIL_ID'], $GLOBALS['SPAM_EMAIL_LIMIT'])) {
    printAjaxResponse(array(
        'status' => 'ERROR',
        'message' => 'Надвишили сте максималния брой съобщения за деня.'
    ));
}

// Use double-quotes as single quotes have problems with \r and \n
$headers = "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: text/html; charset=UTF-8\r\n";
$headers .= "From: action@informatika.bg\r\n";

$address = "thinkcreative@outlook.com";
$subject = "Problem report from " . $user->username;
$text = "<html><body>" .
        "<b>User:</b> " . $user->username . "<br>" .
        "<b>Link:</b> " . $_POST['link'] . "<br>" .
        "<b>Problem Description:</b><br> " .
        "==================================================<br>" .
        nl2br($_POST['problem']) .
        "</body></html>";

if (mail($address, $subject, $text, $headers)) {
    printAjaxResponse(array(
        'status' => 'OK',
        'message' => 'Докладваният проблем беше изпратен успешно.'
    ));
} else {
    printAjaxResponse(array(
        'status' => 'ERROR',
        'message' => 'Възникна проблем при изпращането на съобщението.'
    ));
}

?>