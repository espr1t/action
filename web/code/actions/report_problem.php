<?php
require_once(__DIR__ . "/../config.php");
require_once(__DIR__ . "/../common.php");
require_once(__DIR__ . "/../db/brain.php");

global $user;

// User doesn't have access level needed for sending a mail
if ($user->getAccess() < $GLOBALS["ACCESS_REPORT_PROBLEM"]) {
    printAjaxResponse(array(
        "status" => "ERROR",
        "message" => "Нямате права да изпращате съобщения."
    ));
}

// User has sent too many messages in the last day
if (!passSpamProtection($user, $GLOBALS["SPAM_EMAIL_ID"], $GLOBALS["SPAM_EMAIL_LIMIT"])) {
    printAjaxResponse(array(
        "status" => "ERROR",
        "message" => "Надвишили сте максималния брой съобщения за деня."
    ));
}

Brain::addReport($user->getId(), $_POST["link"], $_POST["problem"]);

$address = $GLOBALS["ADMIN_EMAIL"];
$subject = "Problem report from {$user->getUsername()}";
$content = "
    <!DOCTYPE html>
    <html>
        <head>
          <title>$subject</title>
        </head>
        <body>
            <b>User:</b> {$user->getUsername()}<br>
            <b>Link:</b> {$_POST['link']}<br>
            <b>Problem Description:</b><br>
            ==================================================<br>
            " . nl2br($_POST['problem']) . "
        </body>
    </html>
";

if (sendEmail($address, $subject, $content, "html")) {
    error_log("INFO: User {$user->getUsername()} reported a problem.");
    printAjaxResponse(array(
        "status" => "OK",
        "message" => "Съобщението беше изпратено успешно."
    ));
} else {
    error_log("ERROR: User {$user->getUsername()} got error while trying to report a problem.");
    printAjaxResponse(array(
        "status" => "ERROR",
        "message" => "Възникна проблем при изпращането на съобщението."
    ));
}

?>