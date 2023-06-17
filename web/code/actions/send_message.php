<?php
require_once(__DIR__ . "/../config.php");
require_once(__DIR__ . "/../common.php");
require_once(__DIR__ . "/../entities/message.php");

global $user;

// User doesn't have access level needed for publishing news
if ($user->getAccess() < $GLOBALS["ACCESS_SEND_MESSAGE"]) {
    printAjaxResponse(array(
        "status" => "ERROR",
        "message" => "Нямате права да изпращате съобщения."
    ));
}

$message = new Message();
$message->setId(getIntValue($_POST, "id"));
$message->setKey(getStringValue($_POST, "key"));
$message->setSent(getStringValue($_POST, "sent"));
$message->setAuthorId(getIntValue($_POST, "authorId"));
$message->setAuthorName(getStringValue($_POST, "authorName"));
$message->setTitle(getStringValue($_POST, "title"));
$message->setContent(getStringValue($_POST, "content"));
$message->setUserIds(parseIntArray(getStringValue($_POST, "userIds")));
$message->setUserNames(parseStringArray(getStringValue($_POST, "userNames")));

// Send a new message
if ($message->getId() == -1) {
    if (!$message->send()) {
        printAjaxResponse(array(
            "status" => "ERROR",
            "message" => "Възникна проблем при изпращането на съобщението."
        ));
    }
}
// Updating an existing message
else {
    if (!$message->update()) {
        printAjaxResponse(array(
            "status" => "ERROR",
            "message" => "Възникна проблем при oбновяването на съобщението."
        ));
    }
}

// Everything seems alright, return success and the message ID
printAjaxResponse(array(
    "status" => "OK",
    "message" => "Съобщението беше изпратено успешно."
));

?>