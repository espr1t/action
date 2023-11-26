<?php
require_once(__DIR__ . "/../config.php");
require_once(__DIR__ . "/../entities/problem.php");

global $user;

// User doesn't have access level needed for adding testcases
if ($user->getAccess() < $GLOBALS["ACCESS_EDIT_PROBLEM"]) {
    printAjaxResponse(array(
        "status" => "ERROR",
        "message" => "Нямате права да променяте задачата."
    ));
}

$problem = Problem::get(getIntValue($_POST, "problemId"));
if ($problem == null) {
    printAjaxResponse(array(
        "status" => "ERROR",
        "message" => "Няма задача с ID \"{$_POST['problemId']}\"!"
    ));
}

// Delete current checker if present
if ($problem->getChecker() != "") {
    if (file_exists($problem->getCheckerPath())) {
        unlink($problem->getCheckerPath());
    }
    if (file_exists($problem->getCheckerDir())) {
        rmdir($problem->getCheckerDir());
    }
    $problem->setChecker("");
}

if ($_POST["action"] == "upload") {
    $problem->setChecker($_POST["checkerName"]);

    // Create the Checker directory if it doesn't already exist
    if (!file_exists($problem->getCheckerDir())) {
        mkdir($problem->getCheckerDir(), 0777, true);
    }

    // TODO: Maybe replace CRLF only if Linux is detected?
    $checkerContent = base64_decode($_POST["checkerContent"]);
    $checkerContent = preg_replace("~\R~u", "\n", $checkerContent);
    file_put_contents($problem->getCheckerPath(), $checkerContent);
}

// Everything seems okay
printAjaxResponse(array(
    "status" => "OK",
    "message" => "Чекерът беше обновен успешно."
));

?>