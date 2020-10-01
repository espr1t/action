<?php
require_once(__DIR__ . '/../config.php');
require_once(__DIR__ . '/db.php');

$globalDB = new DB();

class Brain {
    private DB $db;

    function __construct() {
        global $globalDB;
        $this->db = $globalDB;
    }

    /*
     * Query wrappers
     */
    function query(string $query) {
//        $startTime = microtime(true);
        $result = $this->db->query($query);
//        printf("Query:<br><pre>%s</pre><br>  >> execution time: %.3fs<br>", $query, microtime(true) - $startTime);
        if (!$result) {
            error_log("Error while trying to execute query:\n{$query}");
        }
        return $result;
    }

    function select(string $table, string $where="", string $order="", int $limit=-1): ?mysqli_result {
        $result = $this->query("
            SELECT * FROM `{$table}`" .
            ($where == "" ? "" : "\n            WHERE {$where}") .
            ($order == "" ? "" : "\n            ORDER BY {$order}") .
            ($limit == -1 ? "" : "\n            LIMIT {$limit}")
        );
        return !$result ? null : $result;
    }

    private function convertKey(string $key) {
        return "`$key`";
    }

    private function convertVal(string $val) {
            if (is_string($val)) {
                $val = $this->db->escape($val);
            }
            return "'$val'";
    }

    function insert(string $table, array $keyValues): ?int {
        $columns = implode(", ", array_map(function ($k) {return $this->convertKey($k);}, array_keys($keyValues)));
        $values  = implode(", ", array_map(function ($v) {return $this->convertVal($v);}, array_values($keyValues)));
        $result = $this->query("
            INSERT INTO `{$table}`({$columns})
            VALUES ({$values})
        ");
        return !$result ? null : $this->db->lastId();
    }

    function insertOrUpdate(string $table, array $keyValues, array $updateKeyValues): ?int {
        $columns = implode(", ", array_map(function ($k) {return $this->convertKey($k);}, array_keys($keyValues)));
        $values  = implode(", ", array_map(function ($v) {return $this->convertVal($v);}, array_values($keyValues)));
        $updatePairs = array_map(function ($k, $v) {return $this->convertKey($k) . " = " . $this->convertVal($v);}, array_keys($updateKeyValues), $updateKeyValues);
        $result = $this->query("
            INSERT INTO `{$table}`({$columns})
            VALUES ({$values})
            ON DUPLICATE KEY UPDATE
                " . implode(",\n                ", $updatePairs) . "
        ");
        return !$result ? null : $this->db->lastId();
    }

    function update(string $table, array $keyValues, string $where): bool {
        $pairs = array_map(function ($k, $v) {return $this->convertKey($k) . " = " . $this->convertVal($v);}, array_keys($keyValues), $keyValues);
        $result = $this->query("
            UPDATE `{$table}` SET
                " . implode(",\n                ", $pairs) . "
            WHERE {$where}
        ");
        return !!$result;
    }

    function delete(string $table, string $where): bool {
        $result = $this->query("
            DELETE FROM `{$table}`
            WHERE {$where}
        ");
        return !!$result;
    }

    /*
     * Miscellaneous
     */
    function getResult(mysqli_result $sqlResponse): array {
        $result = $sqlResponse->fetch_assoc();
        $sqlResponse->close();
        return $result;
    }

    function getResults(mysqli_result $sqlResponse): array {
        $results = array();
        while ($row = $sqlResponse->fetch_assoc()) {
            array_push($results, $row);
        }
        $sqlResponse->close();
        return $results;
    }

    function getIntResults(mysqli_result $sqlResponse): array {
        $results = array();
        while ($row = $sqlResponse->fetch_row()) {
            array_push($results, intval($row[0]));
        }
        $sqlResponse->close();
        return $results;
    }


    // TODO: Make functions have typed arguments and return values

    /*
     * News
     */
    function addNews(): ?int {
        return $this->insert(
            "News", [
                "date" => "",
                "title" => "",
                "content" => "",
                "icon" => "",
                "type" => ""
            ]
        );
    }

    function updateNews(News $news): bool {
        return $this->update(
            "News", [
                "date" => $news->date,
                "title" => $news->title,
                "content" => $news->content,
                "icon" => $news->icon,
                "type" => $news->type
            ], "id = {$news->id}"
        );
    }

    function getAllNews(): ?array {
        $response = $this->select(
            "News",
            "",
            "`date` DESC",
            20
        );
        return !$response ? null : $this->getResults($response);
    }

    function getNews(int $id): ?array {
        $response = $this->select(
            "News",
            "`id` = {$id}",
            "`date` DESC",
            1
        );
        return !$response ? null : $this->getResult($response);
    }

    /*
     * Reports
     */
    function addReport(int $userId, string $page, string $content): ?int {
        return $this->insert(
            "Reports", [
                "user" => $userId,
                "date" => date('Y-m-d'),
                "page" => $page,
                "content" => $content
            ]
        );
    }

    function getReports(int $userId = -1): ?array {
        $response = $this->select(
            "Reports",
            $userId == -1 ? "" : "`user` = {$userId}"
        );
        return !$response ? null : $this->getResults($response);
    }

    /*
     * Problems
     */
    function addProblem(): ?int {
        return $this->insert(
            "Problems", [
                "type" => "ioi",
                "difficulty" => "trivial"
            ]
        );
    }

    function updateProblem(Problem $problem): bool {
        return $this->update(
            "Problems", [
                "name" => $problem->name,
                "author" => $problem->author,
                "folder" => $problem->folder,
                "timeLimit" => $problem->timeLimit,
                "memoryLimit" => $problem->memoryLimit,
                "type" => $problem->type,
                "difficulty" => $problem->difficulty,
                "statement" => $problem->statement,
                "origin" => $problem->origin,
                "checker" => $problem->checker,
                "tester" => $problem->tester,
                "floats" => $problem->floats ? 1 : 0,
                "tags" => implode(',', $problem->tags),
                "addedBy" => $problem->addedBy,
                "visible" => $problem->visible ? 1 : 0
            ], "`id` = {$problem->id}"
        );
    }

    function updateChecker(Problem $problem): bool {
        return $this->update(
            "Problems", [
                "checker" => $problem->checker
            ], "`id` = {$problem->id}"
        );
    }

    function updateTester(Problem $problem): bool {
        return $this->update(
            "Problems", [
                "tester" => $problem->tester
            ], "`id` = {$problem->id}"
        );
    }

    function getProblem(int $problemId): ?array {
        $response = $this->select(
            "Problems",
            "`id` = {$problemId}",
        );
        return !$response ? null : $this->getResult($response);
    }

    function getAllProblems(): ?array {
        $response = $this->select(
            "Problems",
            "`type` NOT IN ('game', 'relative', 'interactive')",
            "`id`"
        );
        return !$response ? null : $this->getResults($response);
    }

    function getAllGames(): ?array {
        $response = $this->select(
            "Problems",
            "`type` IN ('game', 'relative', 'interactive')",
            "`id`"
        );
        return !$response ? null : $this->getResults($response);
    }

    /*
     * Solutions
     */
    function getProblemSolutions(int $problemId): ?array {
        $response = $this->select(
            "Solutions",
            "`problemId` = {$problemId}"
        );
        return !$response ? null : $this->getResults($response);
    }

    function addSolution(int $problemId, string $name, int $submitId, string $source, string $language): ?int {
        return $this->insert(
            "Solutions", [
                "problemId" => $problemId,
                "name" => $name,
                "submitId" => $submitId,
                "source" => $source,
                "language" => $language
            ]
        );
    }

    function deleteSolution(int $problemId, string $name): bool {
        return $this->delete(
            "Solutions",
            "`problemId` = {$problemId} AND `name` = '" . $this->db->escape($name) . "'"
        );
    }

    /*
     * Tests
     */
    function getProblemTests(int $problemId): ?array {
        $response = $this->select(
            "Tests",
            "`problem` = {$problemId}",
            "`position`"
        );
        return !$response ? null : $this->getResults($response);
    }

    // Find the ID of a testcase (or create a new one and get its ID if not present)
    function getTestId(int $problemId, int $position, bool $insertIfNotFound): ?int {
        $response = $this->select(
            "Tests",
            "`problem` = {$problemId} AND `position` = {$position}"
        );
        $result = $this->getResult($response);

        if ($result) {
            return $result['id'];
        }

        // Insert in the database if a new test
        if ($insertIfNotFound) {
            return $this->insert(
                "Tests", [
                    "problem" => $problemId,
                    "position" => $position,
                    "score" => 10
                ]
            );
        }
        return null;
    }

    function updateTestScore(int $problemId, int $position, int $score): bool {
        $id = $this->getTestId($problemId, $position, false);
        if ($id === null) {
            return false;
        }
        return $this->update(
            "Tests", [
                "score" => $score
            ], "`id` = {$id}"
        );
    }

    function updateTestFile(int $problemId, int $position, string $name, string $hash): bool {
        $id = $this->getTestId($problemId, $position, true);
        if ($id === null) {
            return false;
        }
        $extension = lastElement(explode('.', $name));
        return $this->update(
            "Tests", [
                (($extension == 'in' || $extension == 'inp') ? 'inpFile' : 'solFile') => $name,
                (($extension == 'in' || $extension == 'inp') ? 'inpHash' : 'solHash') => $hash
            ], "`id` = {$id}"
        );
    }

    function deleteTest(int $problemId, int $position): bool {
        return $this->delete(
            "Tests",
            "`problem` = {$problemId} AND `position` = {$position}"
        );
    }

    /*
     * Submits
     */
    function addSubmit(Submit $submit): ?int {
        return $this->insert(
            "Submits", [
                "submitted" => $submit->submitted,
                "gradingStart" => $submit->gradingStart,
                "gradingFinish" => $submit->gradingFinish,
                "userId" => $submit->userId,
                "userName" => $submit->userName,
                "problemId" => $submit->problemId,
                "problemName" => $submit->problemName,
                "language" => $submit->language,
                "results" => implode(",", $submit->results),
                "execTime" => implode(",", $submit->execTime),
                "execMemory" => implode(",", $submit->execMemory),
                "status" => $submit->status,
                "message" => $submit->message,
                "full" => $submit->full ? 1 : 0,
                "ip" => $submit->ip,
                "info" => $submit->info,
                "replayId" => $submit->replayId
            ]
        );
    }

    function updateSubmit(Submit $submit): bool {
        return $this->update(
            "Submits", [
            "gradingStart" => $submit->gradingStart,
            "gradingFinish" => $submit->gradingFinish,
            "results" => implode(',', $submit->results),
            "execTime" => implode(',', $submit->execTime),
            "execMemory" => implode(',', $submit->execMemory),
            "status" => $submit->status,
            "message" => $submit->message,
            "info" => $submit->info,
            "replayId" => $submit->replayId

        ], "`id` = {$submit->id}"
        );
    }

    function getSubmit(int $submitId): ?array {
        $response = $this->select(
            "Submits",
            "`id` = {$submitId}"
        );
        return !$response ? null : $this->getResult($response);
    }

    function getAllSubmits(string $status="all"): ?array {
        $response = $this->select(
            "Submits",
            $status == "all" ? "" : "`status` = '{$status}'"
        );
        return !$response ? null : $this->getResults($response);
    }

    function getPendingSubmits(): ?array {
        $response = $this->select(
            "Submits",
            "`status` IN (
                '{$GLOBALS['STATUS_WAITING']}',
                '{$GLOBALS['STATUS_PREPARING']}',
                '{$GLOBALS['STATUS_COMPILING']}',
                '{$GLOBALS['STATUS_TESTING']}'
            )",
            "`id`"
        );
        return !$response ? null : $this->getResults($response);
    }

    function getLatestSubmits(): ?array {
        $response = $this->select(
            "Submits",
            "`status` IN (
                '{$GLOBALS['STATUS_INTERNAL_ERROR']}',
                '{$GLOBALS['STATUS_COMPILATION_ERROR']}',
                '{$GLOBALS['STATUS_WRONG_ANSWER']}',
                '{$GLOBALS['STATUS_TIME_LIMIT']}',
                '{$GLOBALS['STATUS_MEMORY_LIMIT']}',
                '{$GLOBALS['STATUS_RUNTIME_ERROR']}',
                '{$GLOBALS['STATUS_ACCEPTED']}'
            )",
            "`id` DESC",
            100
        );
        return !$response ? null : $this->getResults($response);
    }

    function getProblemSubmits(int $problemId, string $status="all"): ?array {
        $response = $this->select(
            "Submits",
            "`problemId` = {$problemId}" . ($status == "all" ? "" : " AND `status` = '{$status}'"),
            "`submitted`"
        );
        return !$response ? null : $this->getResults($response);
    }

    function getUserSubmits(int $userId, int $problemId=-1, string $status="all"): ?array {
        $response = $this->select(
            "Submits",
            "`userId` = {$userId}" .
                ($problemId == -1 ? "" : " AND `problemId` = {$problemId}") .
                ($status == "all" ? "" : " AND `status` = '{$status}'"),
            "`submitted`"
        );
        return !$response ? null : $this->getResults($response);
    }

    function getSolved(int $userId): ?array {
        $response = $this->db->query("
            SELECT DISTINCT `problemId` FROM `Submits`
            WHERE `userId` = {$userId} AND `status` = '{$GLOBALS['STATUS_ACCEPTED']}'
        ");
        if (!$response) {
            error_log("Could not execute getSolved() query for user {$userId}!");
            return null;
        }
        return $this->getIntResults($response);
    }

    function getAllSolved(): ?array {
        $response = $this->db->query("
            SELECT DISTINCT `problemId`, `userId` FROM `Submits`
            WHERE `status` = '{$GLOBALS['STATUS_ACCEPTED']}'
        ");
        if (!$response) {
            error_log("Could not execute getAllSolved() query!");
            return null;
        }
        return $this->getResults($response);
    }

    /*
     * Sources
     */
    function addSource(Submit $submit): ?int {
        return $this->insert(
            "Sources", [
                "submitId" => $submit->id,
                "userId" => $submit->userId,
                "problemId" => $submit->problemId,
                "language" => $submit->language,
                "source" => $submit->getSource()
            ]
        );
    }

    function getSource(int $submitId): ?array {
        $response = $this->select(
            "Sources",
            "`submitId` = {$submitId}"
        );
        return !$response ? null : $this->getResult($response);
    }

    function getAllSources(): ?array {
        $response = $this->select(
            "Sources"
        );
        return !$response ? null : $this->getResults($response);
    }

    function getUserSources(int $userId, int $problemId=-1): ?array {
        $response = $this->select(
            "Sources",
            "`userId` = {$userId}" .
                ($problemId == -1 ? "" : " AND `problemId` = {$problemId}"),
            "`submitted`"
        );
        return !$response ? null : $this->getResults($response);
    }

    /*
     * Users
     */
    function addUser(User $user): ?int {
        return $this->insert(
            "Users", [
                "access" => $user->access,
                "registered" => $user->registered,
                "username" => $user->username,
                "name" => $user->name,
                "email" => $user->email,
                "town" => $user->town,
                "country" => $user->country,
                "gender" => $user->gender,
                "birthdate" => $user->birthdate,
                "avatar" => $user->avatar,
            ]
        );
    }

    function updateUser(User $user): bool {
        return $this->update(
            "Users", [
                "access" => $user->access,
                "registered" => $user->registered,
                "username" => $user->username,
                "name" => $user->name,
                "email" => $user->email,
                "town" => $user->town,
                "country" => $user->country,
                "gender" => $user->gender,
                "birthdate" => $user->birthdate,
                "avatar" => $user->avatar
            ], "id = {$user->id}"
        );
    }

    function getUser(int $userId): ?array {
        $response = $this->select(
            "Users",
            "`id` = {$userId}"
        );
        return !$response ? null : $this->getResult($response);
    }

    function getUserByUsername(string $username): ?array {
        $response = $this->select(
            "Users",
            "`username` = '{$username}'"
        );
        return !$response ? null : $this->getResult($response);
    }

    function getAllUsers(): ?array {
        $response = $this->select(
            "Users",
            "",
            "`id`"
        );
        return !$response ? null : $this->getResults($response);
    }

    /*
     * User info
     */
    function addUserInfo(User $user): bool {
        return $this->insert(
            "UsersInfo", [
                "id" => $user->id,
                "username" => $user->username,
                "actions" => $user->actions,
                "totalTime" => $user->totalTime,
                "lastSeen" => $user->lastSeen,
                "profileViews" => $user->profileViews,
                "lastViewers" => $user->lastViewers,
                "loginCount" => $user->loginCount,
                "lastIP" => $user->lastIP
            ]
        );
    }

    function updateUserInfo(User $user): bool {
        return $this->update(
            "UsersInfo", [
                "actions" => $user->actions,
                "totalTime" => $user->totalTime,
                "lastSeen" => $user->lastSeen,
                "profileViews" => $user->profileViews,
                "lastViewers" => $user->lastViewers,
                "loginCount" => $user->loginCount,
                "lastIP" => $user->lastIP
            ], "`id` = {$user->id}"
        );
    }

    function getUserInfo(int $userId): ?array {
        $response = $this->select(
            "UsersInfo",
            "`id` = {$userId}"
        );
        return !$response ? null : $this->getResult($response);
    }

    function getAllUsersInfo(): ?array {
        $response = $this->select(
            "UsersInfo",
            "",
            "`id`"
        );
        return !$response ? null : $this->getResults($response);
    }

    function getActiveUsersInfo(): ?array {
        $response = $this->select(
            "UsersInfo",
            "`lastSeen` >= '" . date('Y-m-d H:i:s', time() - 15 * 60) . "'",
        );
        return !$response ? null : $this->getResults($response);
    }

    /*
     * Credentials
     */
    function addCreds(int $userId, string $userName, string $password, string $loginKey): bool {
        return $this->insert(
            "Credentials", [
                "userId" => $userId,
                "username" => $userName,
                "password" => $password,
                "loginKey" => $loginKey
            ]
        );
    }

    // TODO: The argument may be object? Either User or Credentials.
    function updateCreds(array $creds): bool {
        return $this->update(
            "Credentials", [
                "password" => $creds['password'],
                "loginKey" => $creds['loginKey'],
                "resetKey" => $creds['resetKey'],
                "resetTime" => $creds['resetTime'],
                "lastReset" => $creds['lastReset']
            ], "`userId` = {$creds['userId']}"
        );
    }

    function getCreds(int $userId): ?array {
        $response = $this->select(
            "Credentials",
            "`userId` = {$userId}"
        );
        return !$response ? null : $this->getResult($response);
    }

    function getCredsByLoginKey(string $loginKey): ?array {
        $response = $this->select(
            "Credentials",
            "`loginKey` = '{$loginKey}'"
        );
        return !$response ? null : $this->getResult($response);
    }

    function getCredsByResetKey(string $resetKey): ?array {
        $response = $this->select(
            "Credentials",
            "`resetKey` = '{$resetKey}'"
        );
        return !$response ? null : $this->getResult($response);
    }

    /*
     * Achievements
     */
    function getAchievements(int $userId = -1): ?array {
        $response = $this->select(
            "Achievements",
            $userId == -1 ? "" : "`user` = {$userId}",
            "`date`, `id` ASC"
        );
        return !$response ? null : $this->getResults($response);
    }

    function addAchievement(int $userId, string $achievement, string $date): bool {
        return $this->insertOrUpdate(
            "Achievements", [
                "user" => $userId,
                "achievement" => $achievement,
                "date" => $date
            ], [
                "user" => $userId
            ]
        );
    }

    function markAsSeenAchievement(int $achievementId): bool {
        return $this->update(
            "Achievements", [
                "seen" => "TRUE"
            ], "`id` = {$achievementId}"
        );
    }

    /*
     * SPAM counters
     */
    function refreshSpamCounters(int $minTime): bool {
        return $this->delete(
            "Spam",
            "`time` < {$minTime}"
        );
    }

    function getSpamCounter(User $user, int $type): ?int {
        $response = $this->select(
            "Spam",
            "`user` = {$user->id} AND `type` = {$type}"
        );
        return !$response ? null : count($this->getResults($response));
    }

    function incrementSpamCounter(User $user, int $type, int $time): bool {
        return $this->insert(
            "Spam", [
                "type" => $type,
                "user" => $user->id,
                "time" => $time
            ]
        );
    }

    /*
     * Games
     */
    function getMatch(int $problemId, int $test, int $userOne, int $userTwo): ?array {
        $response = $this->select(
            "Matches",
            "`problemId` = {$problemId} AND `test` = {$test} AND `userOne` = {$userOne} AND `userTwo` = {$userTwo}"
        );
        return !$response ? null : $this->getResult($response);
    }

    function getMatchById(int $matchId): ?array {
        $response = $this->select(
            "Matches",
            "`id` = {$matchId}"
        );
        return !$response ? null : $this->getResult($response);
    }

    function getGameMatches(int $problemId, int $userId = -1): ?array {
        $response = $this->select(
            "Matches",
            "`problemId` = {$problemId}" . ($userId == -1 ? "" : "AND (`userOne` = {$userId} OR `userTwo` = {$userId})")
        );
        return !$response ? null : $this->getResults($response);
    }

    function updateMatch(Match $match): bool {
        return $this->insertOrUpdate(
            "Matches", [
                "problemId" => $match->problemId,
                "test" => $match->test,
                "userOne" => $match->userOne,
                "userTwo" => $match->userTwo,
                "submitOne" => $match->submitOne,
                "submitTwo" => $match->submitTwo,
                "scoreOne" => $match->scoreOne,
                "scoreTwo" => $match->scoreTwo,
                "message" => $match->message,
                "replayId" => $match->replayId,
            ], [
                "submitOne" => $match->submitOne,
                "submitTwo" => $match->submitTwo,
                "scoreOne" => $match->scoreOne,
                "scoreTwo" => $match->scoreTwo,
                "message" => $match->message,
                "replayId" => $match->replayId
            ]
        );
    }

    /*
     * Training
     */
    function addTopic(int $id, string $key, string $link, string $title, string $summary, string $expanded, string $problems) {
        return $this->insertOrUpdate(
            "Training", [
                "id" => $id,
                "key" => $key,
                "link" => $link,
                "title" => $title,
                "summary" => $summary,
                "expanded" => $expanded,
                "problems" => $problems
            ], [
                "link" => $link,
                "title" => $title,
                "summary" => $summary,
                "expanded" => $expanded,
                "problems" => $problems
            ]
        );
    }

    function getTopic(string $key): ?array {
        $response = $this->select(
            "Training",
            "`key` = '{$key}'"
        );
        return !$response ? null : $this->getResult($response);
    }

    function getTrainingTopics(): ?array {
        $response = $this->select(
            "Training"
        );
        return !$response ? null : $this->getResults($response);
    }

    /*
     * Regrading
     */
    // TODO: Change the `id` column to `key`, as it is confusing.
    function getRegradeList(string $id): ?array {
        $response = $this->select(
            "Regrades",
            "`id` = '{$id}'"
        );
        return !$response ? null : $this->getResults($response);
    }

    function getRegradeSubmit(string $id, Submit $submit): ?array {
        $response = $this->select(
            "Regrades",
            "`id` = '{$id}' AND `submitId` = {$submit->id}"
        );
        return !$response ? null : $this->getResults($response);
    }

    function addRegradeSubmit(string $id, Submit $submit): bool {
        return $this->insert(
            "Regrades", [
                "id" => $id,
                "submitId" => $submit->id,
                "userName" => $submit->userName,
                "problemName" => $submit->problemName,
                "submitted" => $submit->submitted,
                "regraded" => date('Y-m-d H:i:s'),
                "oldTime" => max($submit->execTime),
                "newTime" => -1.0,
                "oldMemory" => max($submit->execMemory),
                "newMemory" => -1.0,
                "oldStatus" => $submit->status,
                "newStatus" => $GLOBALS['STATUS_WAITING']
            ]
        );
    }

    function updateRegradeSubmit(string $id, Submit $submit): bool {
        return $this->update(
            "Regrades", [
                "newTime" => max($submit->execTime),
                "newMemory" => max($submit->execMemory),
                "newStatus" => $submit->status
            ], "`id` = '{$id}' AND `submitId` = {$submit->id}"
        );
    }

    /*
     * Notifications
     */
    // TODO: Pass arrays and implode the arrays here.
    function addNotifications(int $userId, string $userName, string $messages, string $seen): ?int {
        return $this->insert(
            "Notifications", [
                "userId" => $userId,
                "username" => $userName,
                "messages" => $messages,
                "seen" => $seen
            ]
        );
    }

    // TODO: Convert argument to an object.
    function updateNotifications(array $notifications): bool {
        return $this->update(
            "Notifications", [
                "messages" => $notifications["messages"],
                "seen" => $notifications["seen"]
            ], "`userId` = {$notifications['userId']}"
        );
    }

    function getNotifications(int $userId): ?array {
        $response = $this->select(
            "Notifications",
            "`userId` = {$userId}"
        );
        return !$response ? null : $this->getResult($response);
    }

    /*
     * Messages
     */
    function getMessage(int $id): ?array {
        $response = $this->select(
            "Messages",
            "`id` = {$id}"
        );
        return !$response ? null : $this->getResult($response);
    }

    function getMessageByKey(string $key): ?array {
        $response = $this->select(
            "Messages",
            "`key` = '{$key}'"
        );
        return !$response ? null : $this->getResult($response);
    }

    function getAllMessages(): ?array {
        $response = $this->select(
            "Messages"
        );
        return !$response ? null : $this->getResults($response);
    }

    function getMessagesToEveryone(): ?array {
        $response = $this->select(
            "Messages",
            "`userIds` = '-1'"
        );
        return !$response ? null : $this->getResults($response);
    }

    function addMessage(): bool {
        return $this->insert(
            "Messages", [
                "authorId" => -1
            ]
        );
   }

    function updateMessage(Message $message): bool {
        return $this->update(
            "Messages", [
                "key" => $message->key,
                "sent" => $message->sent,
                "authorId" => $message->authorId,
                "authorName" => $message->authorName,
                "title" => $message->title,
                "content" => $message->content,
                "userIds" => implode(",", $message->userIds),
                "userNames" => implode(",", $message->userNames)
            ], "`id` = {$message->id}"
        );
    }

    /*
     * History
     */
    function getHistory(int $submitId): ?array {
        $response = $this->select(
            "History",
            "`submitId` = {$submitId}"
        );
        return !$response ? null : $this->getResult($response);
    }

    function addHistory(int $submitId): bool {
        return $this->insert(
            "History", [
                "submitId" => $submitId
            ]
        );
    }

    function updateHistory(int $submitId, array $info): bool {
        return $this->update(
            "History", [
                "time01" => $info['time01'],
                "time02" => $info['time02'],
                "time03" => $info['time03'],
                "time04" => $info['time04'],
                "time05" => $info['time05']
            ], "`submitId` = {$submitId}"
        );
    }

}

?>
