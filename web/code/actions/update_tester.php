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

$testerDir = dirname($problem->getTesterPath());

// Delete current tester if present
if ($problem->getTester() != "") {
    unlink($problem->getTesterPath());
    $problem->setTester("");
}
if (file_exists($testerDir)) {
    rmdir($testerDir);
}

if ($_POST["action"] == "upload") {
    $problem->setTester($_POST["testerName"]);

    // Create the Tester directory if it doesn't already exist
    if (!file_exists($testerDir)) {
        mkdir($testerDir, 0777, true);
    }

    // TODO: Maybe replace CRLF only if Linux is detected?
    $testerContent = base64_decode($_POST["testerContent"]);
    $testerContent = preg_replace("~\R~u", "\n", $testerContent);
    file_put_contents($problem->getTesterPath(), $testerContent);
}

$problem->updateTester();

// Everything seems okay
printAjaxResponse(array(
    "status" => "OK",
    "message" => "Тестерът беше обновен успешно."
));

?>