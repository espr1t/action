<?php
require_once(__DIR__ . "/../config.php");
require_once(__DIR__ . "/../db/brain.php");
require_once(__DIR__ . "/../entities/problem.php");

global $user;

// User doesn't have access level needed for deleting testcases
if ($user->getAccess() < $GLOBALS["ACCESS_EDIT_PROBLEM"]) {
    printAjaxResponse(array(
        "status" => "ERROR",
        "message" => "Нямате права да премахвате тестове."
    ));
}

$problem = Problem::get($_POST["problemId"]);
if ($problem == null) {
    printAjaxResponse(array(
        "status" => "ERROR",
        "message" => "Няма задача с ID \"{$_POST['problemId']}\"!"
    ));
}

// Suppress warnings if file does not exist or cannot be erased.
set_error_handler(function() { /* ignore errors */ });
unlink("{$problem->getTestsPath()}/{$_POST['inpFile']}");
unlink("{$problem->getTestsPath()}/{$_POST['solFile']}");
restore_error_handler();

// Also delete from database
if (!Brain::deleteTest($problem->getId(), $_POST["position"])) {
    printAjaxResponse(array(
        "status" => "ERROR",
        "message" => "Възникна проблем при изтриването на теста!"
    ));
}

// Everything seems okay
printAjaxResponse(array(
    "status" => "OK",
    "message" => "Тестът беше изтрит успешно."
));

?>