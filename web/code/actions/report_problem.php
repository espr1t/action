<?php
require_once(__DIR__ . '/../config.php');
require_once(__DIR__ . '/../common.php');
require_once(__DIR__ . '/../db/brain.php');

global $user;

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

Brain::addReport($user->id, $_POST['link'], $_POST['problem']);


$address = $GLOBALS['ADMIN_EMAIL'];
$subject = "Problem report from " . $user->username;
$content = "<html><body>" .
           "<b>User:</b> " . $user->username . "<br>" .
           "<b>Link:</b> " . $_POST['link'] . "<br>" .
           "<b>Problem Description:</b><br> " .
           "==================================================<br>" .
           nl2br($_POST['problem']) .
           "</body></html>";

if (sendEmail($address, $subject, $content)) {
    printAjaxResponse(array(
        'status' => 'OK',
        'message' => 'Съобщението беше изпратено успешно.'
    ));
} else {
    printAjaxResponse(array(
        'status' => 'ERROR',
        'message' => 'Възникна проблем при изпращането на съобщението.'
    ));
}

?>