<?php
require_once(__DIR__ . '/../config.php');
require_once(__DIR__ . '/../common.php');
require_once(__DIR__ . '/../entities/news.php');

global $user;

// User doesn't have access level needed for publishing news
if ($user->access < $GLOBALS['ACCESS_PUBLISH_NEWS']) {
    printAjaxResponse(array(
        'status' => 'ERROR',
        'message' => 'Нямате права да публикувате новини.'
    ));
}

$news = new News();
$news->date = $_POST['date'];
$news->title = $_POST['title'];
$news->content = $_POST['content'];
$news->icon = $_POST['icon'];
$news->type = $_POST['type'];

$errorMessage = validateNews($news);
if ($errorMessage != '') {
    printAjaxResponse(array(
        'status' => 'ERROR',
        'message' => $errorMessage
    ));
}

// Publish a new news entry
if ($_POST['id'] == 'new') {
    if (!$news->publish()) {
        printAjaxResponse(array(
            'status' => 'ERROR',
            'message' => 'Възникна проблем при публикуването на новината.'
        ));
    }
}
// Updating existing news entry
else {
    $news->id = intval($_POST['id']);
    if (!$news->update()) {
        printAjaxResponse(array(
            'status' => 'ERROR',
            'message' => 'Възникна проблем при записа на новината.'
        ));
    }
}

// Everything seems alright, return success and the news ID
printAjaxResponse(array(
    'status' => 'OK',
    'message' => 'Новината е записана успешно.'
));

function validateNews($news) {
    if (!preg_match('/\d{4}-\d{2}-\d{2}$/', $news->date))
        return 'Въведената дата е невалидна!';
    return '';
}

?>