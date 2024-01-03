<?php
require_once("config.php");
require_once("db/brain.php");

function getCurrentUrl(): string {
    return !isset($_SERVER["HTTPS"]) ? "http://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]"
                                     : "https://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
}

function getUtcTime(): string {
    for ($i = 0; $i < 3; $i++) {
        try {
            $utcTime = new DateTime(null, new DateTimeZone("UTC"));
            return $utcTime->format("Y-m-d H:i:s");
        } catch (Exception $ex) {
            sleep(0.3);
        }
    }
    // In case we couldn't use DateTime class, fall-back to date().
    return date("Y-m-d H:i:s");
}

function getLocalTime(): ?string {
    $localTime = DateTime::createFromFormat("U.u", microtime(true));
    if ($localTime) {
        $localTime->setTimezone(new DateTimeZone("Europe/Sofia"));
        return $localTime->format("Y-m-d H:i:s.u");
    } else {
        return null;
    }
}

function write_log(string $logName, string $message): void {
    global $user;
    // User can be not set if the log is coming from an action (e.g., grader update)
    if (isset($user) && $user->getId() == -1) {
        // Logs for anonymous users go to separate files.
        $logName = explode(".", $logName)[0] . "_anonymous.log";
    }
    $logPath = str_replace("\\", "/", realpath(__DIR__ . "/../")) . "/logs/{$logName}";
    // TODO: It may be a good idea to leave this to the default (UTC)
    $logTime = getLocalTime();
    if ($logTime !== null) {
        $logLine = "[{$logTime}] {$message}\n";
        file_put_contents($logPath, $logLine, FILE_APPEND | LOCK_EX);
    } else {
        error_log("Couldn't log message '{$message}'. DateTime failed.");
    }
}

function isValidIP(string $ip): bool {
    if ($ip === null || $ip === "" || strtolower($ip) === "unknown")
        return false;
    // This will not work for IPv6
    $firstOctet = explode(".", $ip)[0];
    if ($firstOctet == "10" || $firstOctet == "172" || $firstOctet == "192")
        return false;
    return true;
}

function getUserIP(): string {
    if (isset($_SERVER["HTTP_CLIENT_IP"]) && isValidIP($_SERVER["HTTP_CLIENT_IP"]))
        return $_SERVER["HTTP_CLIENT_IP"];
    if (isset($_SERVER["HTTP_X_FORWARDED_FOR"]) && isValidIP($_SERVER["HTTP_X_FORWARDED_FOR"]))
        return $_SERVER["HTTP_X_FORWARDED_FOR"];
    return $_SERVER["REMOTE_ADDR"];
}

function popcount(int $num): int {
    $bits = 0;
    for (; $num > 0; $num &= ($num - 1))
        $bits += 1;
    return $bits;
}

function randomString(int $len, string $alpha): string {
    $randomString = "";
    $alphaSize = strlen($alpha);
    for ($i = 0; $i < $len; $i++) {
        $randomString .= $alpha[rand(0, $alphaSize - 1)];
    }
    return $randomString;
}

function showNotification(string $notificationType, string $notificationText): string {
    return "<script>showNotification(`{$notificationType}`, `{$notificationText}`);</script>";
}

function getNotification(): string {
    $showNotification = "";
    if (isset($_SESSION["notificationType"]) && isset($_SESSION["notificationText"])) {
        $showNotification .= "<script>showNotification(`{$_SESSION['notificationType']}`, `{$_SESSION['notificationText']}`);</script>";
        unset($_SESSION["notificationType"]);
        unset($_SESSION["notificationText"]);
    }
    if (isset($_POST["notificationType"]) && isset($_POST["notificationText"])) {
        $showNotification .= "<script>showNotification(`{$_POST['notificationType']}`, `{$_POST['notificationText']}`);</script>";
        $showNotification .= "<script>setTimeout(function() {{window.location=window.location;}}, {$GLOBALS['NOTIFICATION_DISPLAY_TIME']});</script>";
    }
    return $showNotification;
}

function redirect(string $url, ?string $notificationType=null, ?string $notificationText=null): void {
    // Redirect with arguments (pass them using session data).
    if ($notificationType !== null && $notificationText !== null) {
        $_SESSION["notificationType"] = $notificationType;
        $_SESSION["notificationText"] = $notificationText;
    }
    header("Location: {$url}");
    exit();
}

function printAjaxResponse(array $response): void {
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit(0);
}

function printArray(array $arr): void {
    echo json_encode($arr, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "<br>\n";
}

function saltHashPassword(string $password): string {
    return md5($password . $GLOBALS["PASSWORD_SALT"]);
}

function isProduction(): bool {
    return !in_array($_SERVER["REMOTE_ADDR"], array("127.0.0.1", "::1", "localhost", "192.168.1.123"));
}

function validateReCaptcha(): bool {
    if (!isset($_POST["g-recaptcha-response"]))
        return false;

    $url = "https://www.google.com/recaptcha/api/siteverify";
    $data = array(
        "secret" => isProduction() ? $GLOBALS["RE_CAPTCHA_PROD_SECRET_KEY"] : $GLOBALS["RE_CAPTCHA_TEST_SECRET_KEY"],
        "response" => $_POST["g-recaptcha-response"],
        "remoteip" => getUserIP()
    );
    $options = array(
        "http" => array(
            "header"  => "Content-type: application/x-www-form-urlencoded\r\n",
            "method"  => "POST",
            "content" => http_build_query($data)
        )
    );
    $context  = stream_context_create($options);
    $response = file_get_contents($url, false, $context);
    if ($response === false) {
        error_log("ERROR: Could not call re-CAPTCHA server.");
        return false;
    }
    $result = json_decode($response, true);
    return $result["success"];
}

function validateUsername(string $username): bool {
    return preg_match('/^\w[\w.]{1,15}$/', $username) == 1;
}

function validateName(string $name): bool {
    return preg_match('/(*UTF8)^([A-Za-zА-Яа-я]|-){1,32}$/', $name) == 1;
}

function validatePassword(string $password): bool {
    return preg_match('/^.{1,32}$/', $password) == 1;
}

function validateEmail(string $email): bool {
    return $email == "" || preg_match('/^[A-Za-z0-9_.+*=$^-]+@[a-zA-Z0-9-]+\.[a-zA-Z0-9-.]+$/', $email) == 1;
}

function validateDate(string $date): bool {
    return $date == "" || preg_match('/^\d\d\d\d-\d\d-\d\d$/', $date) == 1;
}

function validatePlace(string $town): bool {
    return $town == "" || preg_match('/(*UTF8)^[A-Za-zА-Яа-я ]{1,32}$/', $town) == 1;
}

function validateGender(string $gender): bool {
    return $gender == "" || preg_match('/^male|female$/', $gender) == 1;
}

function toInt($value): ?int {
    if ($value === null || is_int($value))
        return $value;
    // Not perfect, as 19 nines will overflow, but I want to treat 10^18 as valid.
    if (is_string($value) && preg_match('/^[+-]?\d{1,19}$/', $value) == 1)
        return intval($value);
    return null;
}

function toFloat($value): ?float {
    if ($value === null || is_float($value))
        return $value;
    if (is_numeric($value))
        return floatval($value);
    return null;
}

function toBool($value): ?bool {
    if ($value === null || is_bool($value))
        return $value;
    if (is_string($value)) {
        if (strtolower($value) === "true" || $value === "1")
            return true;
        if (strtolower($value) === "false" || $value === "0")
            return false;
    }
    return null;
}

function getValue(array $array, string $key) {
    if (!array_key_exists($key, $array)) {
        echo "ERROR: Array does not contain value for key \"{$key}\"!<br>\n";
        error_log("Array does not contain value for key \"{$key}\"!");
        return null;
    }
    return $array[$key];
}

function getStringValue(array $array, string $key): ?string {
    $value = getValue($array, $key);
    return $value === null ? null : strval($value);
}

function getIntValue(array $array, string $key): ?int {
    $value = getValue($array, $key);
    if ($value === null) {
        return null;
    }
    $result = toInt($value);
    if (!is_int($result)) {
        echo "Value '{$value}' does not seem to be an integer!<br>\n";
        error_log("Value '{$value}' does not seem to be an integer!");
    }
    return $result;
}

function getFloatValue(array $array, string $key): ?float {
    $value = getValue($array, $key);
    if ($value === null) {
        return null;
    }
    $result = toFloat($value);
    if (!is_float($result)) {
        echo "Value '{$value}' does not seem to be a float!<br>\n";
        error_log("Value '{$value}' does not seem to be a float!");
    }
    return $result;
}

function getBoolValue(array $array, string $key): ?bool {
    $value = getValue($array, $key);
    if ($value === null) {
        return null;
    }
    $result = toBool($value);
    if (!is_bool($result)) {
        echo "Value '{$value}' does not seem to be a bool!<br>\n";
        error_log("Value '{$value}' does not seem to be a bool!");
    }
    return $result;
}

/** @return int[] */
function getIntArray(array $array, string $key): ?array {
    $value = getValue($array, $key);
    if ($value === null || !is_string($value))
        return null;
    return parseIntArray($value);
}

/** @return float[] */
function getFloatArray(array $array, string $key): ?array {
    $value = getValue($array, $key);
    if ($value === null || !is_string($value))
        return null;
    return parseFloatArray($value);
}

/** @return string[] */
function getStringArray(array $array, string $key): ?array {
    $value = getValue($array, $key);
    if ($value === null || !is_string($value))
        return null;
    return parseStringArray($value);
}

function lastElement(array $array) {
    return empty($array) ? null : $array[array_key_last($array)];
}

/** @return int[] */
function parseIntArray(string $str): array {
    return array_map("toInt", parseStringArray($str));
}

/** @return float[] */
function parseFloatArray(string $str): array {
    return array_map("toFloat", parseStringArray($str));
}

/** @return string[] */
function parseStringArray(string $str): array {
    return $str == "" ? array() : explode(",", $str);
}

function passSpamProtection(User $user, string $type, int $limit): bool {
    Brain::refreshSpamCounters(time() - $GLOBALS["SPAM_INTERVAL"]);
    if (Brain::getSpamCounter($user, $type) < $limit) {
        Brain::incrementSpamCounter($user, $type, time());
        return true;
    }
    return false;
}

function canSeeProblem(User $user, bool $problemVisible): bool {
    if ($problemVisible)
        return true;
    return $user->getAccess() >= $GLOBALS["ACCESS_HIDDEN_PROBLEMS"];
}

function getGameUrlName(string $problemName): string {
    return str_replace(" ", "-", strtolower($problemName));
}

function getGameUrl(string $problemName): string {
    $gameUrlName = getGameUrlName($problemName);
    return "/games/{$gameUrlName}";
}

function getTaskUrl(int $problemId): string {
    return "/problems/{$problemId}";
}

function getGameLink(string $problemName): string {
    $url = getGameUrl($problemName);
    return "<a href='{$url}'><div class='problem'>{$problemName}</div></a>";
}

function getTaskLink(int $problemId, string $problemName): string {
    $url = getTaskUrl($problemId);
    return "<a href='{$url}'><div class='problem'>{$problemName}</div></a>";
}

function getUserLink(string $userName, array $unofficial=array()): string {
    $shownName = $userName . (in_array($userName, $unofficial) ? "*" : "");
    return "<a href='/users/{$userName}'><div class='user'>{$shownName}</div></a>";
}

function getUserBox(string $userName): string {
    return "<a href='/users/{$userName}'>
                <div class='achievement'>
                    <div class='user' style='color: white; margin-left: 0.25rem;'>
                        <i class='fa fa-user-circle'></i> {$userName}</div>
                </div>
            </a>
    ";
}

// Used in page.html
function userInfo(User $user): string {
    if ($user->getId() >= 1) {
        $numUnreadMessages = $user->numUnreadMessages();
        $notificationIcon = "<i class='fas fa-bell'></i>";
        $notificationText = "Нямате непрочетени съобщения.";
        if ($numUnreadMessages > 0) {
            $notificationIcon = "<i class='fas fa-bell' style='color: #D84A38;'></i>";
            $notificationText = $numUnreadMessages == 1 ? "Имате 1 непрочетено съобщение." :
                                                          "Имате {$numUnreadMessages} непрочетени съобщения.";
        }
        return "
            <div class='userInfo'>
                <i class='fa fa-user-circle'></i>
                <span style='position: relative; top: -0.0625rem;'>" . getUserLink($user->getUsername()) . " | </span>
                <div class='tooltip--top' style='cursor: pointer;' data-tooltip='{$notificationText}'>
                    <a class='decorated' href='/messages'>{$notificationIcon}</a>
                </div>
                &nbsp;
            </div>
        ";
    }
    return "";
}

function inBox(string $content, array $extra=array()): string {
    $extraClasses = implode(" ", $extra);
    return "
            <div class='box {$extraClasses}'>
                {$content}
            </div>
    ";
}

// Used in page.html
function createHead(Page $page): string {
    $keywords = implode(",", [
        "Програмиране", "Информатика", "Алгоритми", "Структури Данни", "Задачи",
        "Programming", "Informatics", "Algorithms", "Data Structures", "Problems"
    ]);

    $scripts = array_merge([
        "/scripts/common.js",
        "/scripts/achievements.js"
    ], $page->getExtraScripts());

    $styles = array_merge([
        "/styles/style.css",
        "/styles/achievements.css",
        "/styles/tooltips.css",
        "/styles/icons/css/fontawesome-all.min.css"
    ], $page->getExtraStyles());

    $meta = "
        <title>{$page->getTitle()}</title>
        <meta charset='utf-8'>
        <meta name='author' content='Alexander Georgiev'>
        <meta name='keywords' content='{$keywords}'>
        <link rel='shortcut icon' type='image/png' href='/images/favicon_blue_512.png'>
    ";
    foreach($styles as $style) {
        $meta .= "
        <link rel='stylesheet' type='text/css' href='{$style}'>";
    }
    foreach($scripts as $script) {
        $meta .= "
        <script src='{$script}'></script>";
    }
    return trim($meta) . "
    ";
}

// TODO: Handle properly Python errors once supported
function prettyPrintCompilationErrors(Submit $submit): string {
    $pathToSandbox = sprintf("sandbox/submit_%06d/", $submit->getId());
    $message = $submit->getMessage();
    $message = str_replace($pathToSandbox, "", $message);
    $message = str_replace("Compilation error: ", "", $message);
    $message = str_replace("source.cpp: ", "", $message);
    $message = str_replace("source.java:", "Line ", $message);

    $errorsList = "";
    foreach (explode("^", $message) as $errors) {
        foreach (explode("source.cpp", $errors) as $error) {
            $error = ltrim($error, ":");
            if (strlen($error) > 1) {
                $error = str_replace('"', "'", $error);
                $error = str_replace("\\", "\\\\", $error);
                $error = htmlspecialchars(trim($error));
                $errorsList .= "<li><code>{$error}</code></li>";
            }
        }
    }
    return "
        <div style='border: 1px dashed #333333; padding: 0.5rem;'>
            <ul>
            {$errorsList}
            </ul>
        </div>
    ";
}

function getWaitingTimes(User $user, Problem $problem, &$remainPart, &$remainFull): void {
    $submits = Submit::getAllSubmits($user->getId(), $problem->getId());
    $lastPart = 0; $lastFull = 0;
    foreach ($submits as $submit) {
        if ($submit->getStatus() != $GLOBALS["STATUS_COMPILATION_ERROR"]) {
            if ($submit->getFull()) {
                $lastFull = max(array($lastFull, strtotime($submit->getSubmitted())));
            } else {
                $lastPart = max(array($lastPart, strtotime($submit->getSubmitted())));
            }
        }
    }
    $remainPart = $problem->getWaitPartial() * 60 - (time() - $lastPart);
    $remainFull = $problem->getWaitFull() * 60 - (time() - $lastFull);
}

function getSubmitWithChecks(User $user, int $submitId, Problem $problem, string $redirectUrl): Submit {
    $submit = Submit::get($submitId);
    if ($submit === null) {
        redirect($redirectUrl, "ERROR", "Не съществува решение с този идентификатор!");
    }
    if ($user->getAccess() < $GLOBALS["ACCESS_SEE_SUBMITS"]) {
        if ($submit->getUserId() != $user->getId()) {
            redirect($redirectUrl, "ERROR", "Нямате достъп до това решение!");
        }
        if ($submit->getProblemId() != $problem->getId()) {
            redirect($redirectUrl, "ERROR", "Решението не е по поисканата задача!");
        }
    }
    return $submit;
}

function getStatusColor(string $status): string {
    switch ($status) {
        case $GLOBALS["STATUS_ACCEPTED"]:
            return "green";
        case $GLOBALS["STATUS_INTERNAL_ERROR"]:
            return "black";
        default:
            return strlen($status) == 1 ? "gray" : "red";
    }
}

function getSourceSection(Problem $problem, Submit $submit): string {
    $url = getTaskUrl($problem->getId());
    if ($problem->getType() == "game" || $problem->getType() == "relative") {
        $url = getGameUrl($problem->getName());
    }
    $url = "{$url}/submits/{$submit->getId()}/source";

    return "
        <div class='centered' id='sourceLink'>
            <a href='{$url}' target='_blank'>Виж кода</a>
        </div>
    ";
}

function getCurrentUser(): ?User {
    if (isset($_SESSION["userId"])) {
        return User::getById($_SESSION["userId"]);
    }
    if (isset($_COOKIE[$GLOBALS["COOKIE_NAME"]])) {
        // Scan all users for a one with a loginKey matching the one stored in the cookie
        list($loginKey, $hmac) = explode(":", $_COOKIE[$GLOBALS["COOKIE_NAME"]], 2);
        // This, unfortunately, wouldn't work for non-static IPs =/
        if ($hmac == hash_hmac("md5", $loginKey, getUserIP())) {
            $user = User::getByLoginKey($loginKey);
            if ($user != null) {
                $_SESSION["userId"] = $user->getId();
            }
            return $user;
        }
    }
    return null;
}

function sendEmail(string $address, string $subject, string $content, string $content_type="plain"): bool {
    $headers = array(
        "MIME-Version" => "1.0",
        "Content-Type" => "text/$content_type; charset=utf-8",
        "From" => "action@informatika.bg"
    );
    return mail($address, $subject, $content, $headers);
}

function getAchievementsContent(): string {
    $newAchievements = AdminAchievementsPage::getNewAchievements($GLOBALS["user"]);
    if (count($newAchievements) == 0) {
        return "";
    }
    $achievementsFile = file_get_contents("{$GLOBALS['PATH_DATA']}/achievements/achievements.json");
    $achievementsData = json_decode($achievementsFile, true);

    $achievementsContent = "";
    $shownAchievements = min(array(3, count($newAchievements)));
    for ($i = 0; $i < $shownAchievements; $i += 1) {
        $key = $newAchievements[$i];
        $title = null;
        $description = null;
        foreach ($achievementsData as $info) {
            if ($info["key"] == $key) {
                $title = $info["title"];
                $description = $info["description"];
                break;
            }
        }
        if ($title === null) {
            error_log("Could not find achievement with key '{$key}'!");
            continue;
        }
        $achievementsContent .= "
            <script>showAchievement(`{$title}`, `{$description}`, {$i}, {$shownAchievements});</script>
        ";
    }
    return $achievementsContent;
}

function getPrimaryStatsCircle(string $statName, string $statValue, string $statInfo): string {
    return "
        <div class='tooltip--top' data-tooltip='{$statInfo}'>
            <div class='primary-stats-circle'>
                <div class='primary-stats-circle-content'>
                    <div class='primary-stats-circle-line1'>
                        {$statName}
                    </div>
                    <div class='primary-stats-circle-line2'>
                        {$statValue}
                    </div>
                </div>
            </div>
        </div>
    ";
}

?>
