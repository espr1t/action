<?php

// User doesn't have access level needed for publishing news
if ($user->access < $GLOBALS['ACCESS_PUBLISH_NEWS']) {
    printAjaxResponse(array(
        'status' => 'ERROR',
        'message' => 'Нямате права да публикувате новини.'
    ));
    exit();
}

require_once('brain.php');

$brain = new Brain();
$result = $brain->publishNews($_POST['date'], $_POST['title'], $_POST['content']);

if ($result) {
    printAjaxResponse(array(
        'status' => 'OK',
        'message' => 'Новината е публикувана успешно.'
    ));
} else {
    printAjaxResponse(array(
        'status' => 'ERROR',
        'message' => 'Възникна проблем при публикуването.'
    ));
}

?>