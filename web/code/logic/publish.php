<?php

// User doesn't have access level needed for publishing news
if ($user->access < $GLOBALS['ACCESS_PUBLISH_NEWS']) {
    printAjaxResponse(array(
        'status' => 'ERROR',
        'message' => 'Нямате права да публикувате новини.'
    ));
}

require_once('brain.php');
require_once('news.php');

function validateData($news) {
    if (!preg_match('/\d{4}-\d{2}-\d{2}$/', $news->date))
        return 'Въведената дата е невалидна!';
    return '';
}

$news = new News();
$news->date = $_POST['date'];
$news->title = $_POST['title'];
$news->content = $_POST['content'];

$errorMessage = validateData($news);
if ($errorMessage != '') {
    printAjaxResponse(array(
        'status' => 'ERROR',
        'message' => $errorMessage
    ));
}

// New problem
if ($_POST['id'] == 'new') {
    if (!$news->create()) {
        printAjaxResponse(array(
            'status' => 'ERROR',
            'message' => 'Възникна проблем при публикуването на новината.'
        ));
    }
}
// Updating existing problem
else {
    $news->id = intval($_POST['id']);
    if (!$news->update()) {
        printAjaxResponse(array(
            'status' => 'ERROR',
            'message' => 'Възникна проблем при записа на новината.'
        ));
    }
}

printAjaxResponse(array(
    'id' => $news->id,
    'status' => 'OK',
    'message' => 'Новината е записана успешно.'
));

?>