<?php
require_once(__DIR__ . "/../config.php");
require_once(__DIR__ . "/../common.php");
require_once(__DIR__ . "/../db/brain.php");
require_once(__DIR__ . "/../entities/user.php");

session_start();

// We need to allow this for not logged-in users in order
// to be available for password recovery

function getSource(int $id): array {
    $source = Brain::getSource($id);
    if ($source === null) {
        return array(
            "status" => "ERROR",
            "reason" => "Няма събмит с този идентификатор."
        );
    }
    return array(
        "status" => "OK",
        "source" => $source["source"],
        "language" => $source["language"]
    );
}

function obscureEmail(string $email): string {
    if (strpos($email, "@") == false)
        return "";
    $obscured = explode("@", $email)[0];
    for ($i = 1; $i < strlen($obscured) - 1; $i++)
        $obscured[$i] = "*";
    return $obscured . "@" . explode("@", $email)[1];
}

function getUsersBasicInfo(): array {
    $users = Brain::getAllUsers();
    $usersBasicInfo = array();
    foreach ($users as $user) {
        if ($user["id"] >= 1) {
            array_push($usersBasicInfo, array(
                "id" => $user["id"],
                "username" => $user["username"],
                "name" => $user["name"],
                "email" => obscureEmail($user["email"])
            ));
        }
    }
    return $usersBasicInfo;
}

switch ($_GET["type"]) {
    case "users":
        printAjaxResponse(array(
            "status" => "OK",
            "users" => getUsersBasicInfo()
        ));
        break;

    case "source":
        $user = getCurrentUser();
        if ($user == null || $user->getAccess() < $GLOBALS["ACCESS_ADMIN_PAGES"]) {
            printAjaxResponse(array(
                "status" => "ERROR",
                "reason" => "Не сте влезли в системата."
            ));
        }
        printAjaxResponse(getSource($_GET['id']));
        break;

    default:
        printAjaxResponse(array(
            "status" => "WARNING",
            "reason" => "Невалидно действие!"
        ));
}

?>