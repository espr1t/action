<?php
require_once(__DIR__ . '/../config.php');
require_once(__DIR__ . '/../common.php');
require_once(__DIR__ . '/../entities/message.php');

// User doesn't have access level needed for publishing news
if ($user->access < $GLOBALS['ACCESS_SEND_MESSAGE']) {
    printAjaxResponse(array(
        'status' => 'ERROR',
        'message' => 'Нямате права да изпращате съобщения.'
    ));
}

$message = new Message();
$message->id = intval($_POST['id']);
$message->key = $_POST['key'];
$message->sent = $_POST['sent'];
$message->authorId = intval($_POST['authorId']);
$message->authorName = $_POST['authorName'];
$message->title = $_POST['title'];
$message->content = $_POST['content'];
$message->userIds = parseIntArray($_POST['userIds']);
$message->userNames = parseStringArray($_POST['userNames']);

// Send a new message
if ($message->id == -1) {
    if (!$message->send()) {
        printAjaxResponse(array(
            'status' => 'ERROR',
            'message' => 'Възникна проблем при изпращането на съобщението.'
        ));
    }
}
// Updating an existing message
else {
    if (!$message->update()) {
        printAjaxResponse(array(
            'status' => 'ERROR',
            'message' => 'Възникна проблем при oбновяването на съобщението.'
        ));
    }
}

// Everything seems alright, return success and the message ID
printAjaxResponse(array(
    'status' => 'OK',
    'message' => 'Съобщението беше изпратено успешно.'
));

?>