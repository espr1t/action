<?php
require_once(__DIR__ . "/../config.php");
require_once(__DIR__ . "/../db/brain.php");
require_once(__DIR__ . "/../entities/problem.php");

global $user;

// User doesn't have access level needed for editing a problem
if ($user->getAccess() < $GLOBALS["ACCESS_EDIT_PROBLEM"]) {
    printAjaxResponse(array(
        "status" => "ERROR",
        "message" => "Нямате права да променяте задачи."
    ));
}

# Deduce the author by the user who is doing the POST
$_POST["addedBy"] = $user->getUsername();

# Some of the fields are not populated by the Admin page (they cannot be set there and should be set in the DB directly)
# These are for the games specifically - I haven't gotten to creating a different flow for creating/editing games yet as
# they are few enough to do by updating the DB directly.
$problem = Problem::instanceFromArray($_POST, ["description", "logo", "waitPartial", "waitFull"]);

$errorMessage = $problem->validate();
if ($errorMessage != "") {
    printAjaxResponse(array(
        "status" => "ERROR",
        "message" => $errorMessage
    ));
}

if ($_POST["id"] == "new") { // New problem
    if (!$problem->create()) {
        printAjaxResponse(array(
            "status" => "ERROR",
            "message" => "Възникна проблем при създаването на задачата."
        ));
    }
} else { // Updating existing problem
    if (!$problem->update()) {
        printAjaxResponse(array(
            "status" => "ERROR",
            "message" => "Възникна проблем при промяната на задачата."
        ));
    }
}

// Update the tests
for ($i = 0; $i <= 1000; $i++) {
    $key = sprintf("test_%d", $i);
    if (isset($_POST[$key])) {
        Brain::updateTestScore($problem->getId(), $i, $_POST[$key]);
    }
}

// Everything seems okay
printAjaxResponse(array(
    "id" => $problem->getId(),
    "status" => "OK",
    "message" => "Задачата беше запазена успешно."
));


?>