<?php
require_once(__DIR__ . "/../config.php");
require_once(__DIR__ . "/../common.php");
require_once(__DIR__ . "/../entities/news.php");

global $user;

// User doesn't have access level needed for publishing news
if ($user->getAccess() < $GLOBALS["ACCESS_PUBLISH_NEWS"]) {
    printAjaxResponse(array(
        "status" => "ERROR",
        "message" => "Нямате права да публикувате новини."
    ));
}

$news = new News();
$news->setDate(getStringValue($_POST, "date"));
$news->setTitle(getStringValue($_POST, "title"));
$news->setContent(getStringValue($_POST, "content"));
$news->setIcon(getStringValue($_POST, "icon"));
$news->setType(getStringValue($_POST, "type"));

function validateNews(News $news): bool {
    if (!preg_match('/\d{4}-\d{2}-\d{2}$/', $news->getDate()))
        return "Въведената дата е невалидна!";
    return "";
}

$errorMessage = validateNews($news);
if ($errorMessage != "") {
    printAjaxResponse(array(
        "status" => "ERROR",
        "message" => $errorMessage
    ));
}

// Publish a new news entry
if ($_POST["id"] == "new") {
    if (!$news->publish()) {
        printAjaxResponse(array(
            "status" => "ERROR",
            "message" => "Възникна проблем при публикуването на новината."
        ));
    }
}
// Updating existing news entry
else {
    $news->setId(getIntValue($_POST, "id"));
    if (!$news->update()) {
        printAjaxResponse(array(
            "status" => "ERROR",
            "message" => "Възникна проблем при записа на новината."
        ));
    }
}

// Everything seems alright, return success and the news ID
printAjaxResponse(array(
    "status" => "OK",
    "message" => "Новината е записана успешно."
));

?>