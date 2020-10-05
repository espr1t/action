<?php
require_once(__DIR__ . '/../config.php');
require_once(__DIR__ . '/db.php');

class Brain {
    /*
     * Query wrappers
     */
    private static function query(string $query) {
//        $startTime = microtime(true);
        $result = DB::query($query);
//        if (isset($GLOBALS['user'])) {
//            // The check above fixes generating invalid JSON responses.
//            printf("Query:<br><pre>%s</pre><br>  >> execution time: %.3fs<br>", $query, microtime(true) - $startTime);
//        }
        if (!$result) {
            error_log("Error while trying to execute query:\n{$query}");
        }
        return $result;
    }

    private static function select(string $table, string $where="", string $order="", int $limit=-1): ?mysqli_result {
        $result = self::query("
            SELECT * FROM `{$table}`" .
            ($where == "" ? "" : "\n            WHERE {$where}") .
            ($order == "" ? "" : "\n            ORDER BY {$order}") .
            ($limit == -1 ? "" : "\n            LIMIT {$limit}")
        );
        return !$result ? null : $result;
    }

    private static function count(string $table, string $where=""): ?mysqli_result {
        $result = self::query("
            SELECT COUNT(*) FROM `{$table}`" .
            ($where == "" ? "" : "\n            WHERE {$where}")
        );
        return !$result ? null : $result;
    }

    private static function convertKey(string $key) {
        return "`$key`";
    }

    private static function convertVal(string $val) {
            if (is_string($val)) {
                $val = DB::escape($val);
            }
            return "'$val'";
    }

    private static function insert(string $table, array $keyValues): ?int {
        $columns = implode(", ", array_map(function ($k) {return self::convertKey($k);}, array_keys($keyValues)));
        $values  = implode(", ", array_map(function ($v) {return self::convertVal($v);}, array_values($keyValues)));
        $result = self::query("
            INSERT INTO `{$table}`({$columns})
            VALUES ({$values})
        ");
        return !$result ? null : DB::lastId();
    }

    private static function insertOrUpdate(string $table, array $keyValues, array $updateKeyValues): ?int {
        $columns = implode(", ", array_map(function ($k) {return self::convertKey($k);}, array_keys($keyValues)));
        $values  = implode(", ", array_map(function ($v) {return self::convertVal($v);}, array_values($keyValues)));
        $updatePairs = array_map(function ($k, $v) {return self::convertKey($k) . " = " . self::convertVal($v);}, array_keys($updateKeyValues), $updateKeyValues);
        $result = self::query("
            INSERT INTO `{$table}`({$columns})
            VALUES ({$values})
            ON DUPLICATE KEY UPDATE
                " . implode(",\n                ", $updatePairs) . "
        ");
        return !$result ? null : DB::lastId();
    }

    private static function update(string $table, array $keyValues, string $where): bool {
        $pairs = array_map(function ($k, $v) {return self::convertKey($k) . " = " . self::convertVal($v);}, array_keys($keyValues), $keyValues);
        $result = self::query("
            UPDATE `{$table}` SET
                " . implode(",\n                ", $pairs) . "
            WHERE {$where}
        ");
        return !!$result;
    }

    private static function delete(string $table, string $where): bool {
        $result = self::query("
            DELETE FROM `{$table}`
            WHERE {$where}
        ");
        return !!$result;
    }

    /*
     * Miscellaneous
     */
    private static function getResult(mysqli_result $sqlResponse): ?array {
        $result = $sqlResponse->fetch_assoc();
        $sqlResponse->close();
        return $result;
    }

    private static function getResults(mysqli_result $sqlResponse): array {
        $results = array();
        while ($row = $sqlResponse->fetch_assoc()) {
            array_push($results, $row);
        }
        $sqlResponse->close();
        return $results;
    }

    private static function getIntResult(mysqli_result $sqlResponse): int {
        $result = intval($sqlResponse->fetch_row()[0]);
        $sqlResponse->close();
        return $result;
    }

    private static function getIntResults(mysqli_result $sqlResponse): array {
        $results = array();
        while ($row = $sqlResponse->fetch_row()) {
            array_push($results, intval($row[0]));
        }
        $sqlResponse->close();
        return $results;
    }


    /*
     * News
     */
    public static function addNews(): ?int {
        return self::insert(
            "News", [
                "date" => "",
                "title" => "",
                "content" => "",
                "icon" => "",
                "type" => ""
            ]
        );
    }

    public static function updateNews(News $news): bool {
        return self::update(
            "News", [
                "date" => $news->date,
                "title" => $news->title,
                "content" => $news->content,
                "icon" => $news->icon,
                "type" => $news->type
            ], "id = {$news->id}"
        );
    }

    public static function getAllNews(): ?array {
        $response = self::select(
            "News",
            "",
            "`date` DESC",
            20
        );
        return !$response ? null : self::getResults($response);
    }

    public static function getNews(int $id): ?array {
        $response = self::select(
            "News",
            "`id` = {$id}",
            "`date` DESC",
            1
        );
        return !$response ? null : self::getResult($response);
    }

    /*
     * Reports
     */
    public static function addReport(int $userId, string $page, string $content): ?int {
        return self::insert(
            "Reports", [
                "user" => $userId,
                "date" => date('Y-m-d'),
                "page" => $page,
                "content" => $content
            ]
        );
    }

    public static function getReports(int $userId = -1): ?array {
        $response = self::select(
            "Reports",
            $userId == -1 ? "" : "`user` = {$userId}"
        );
        return !$response ? null : self::getResults($response);
    }

    /*
     * Problems
     */
    public static function addProblem(): ?int {
        return self::insert(
            "Problems", [
                "type" => "ioi",
                "difficulty" => "trivial"
            ]
        );
    }

    public static function updateProblem(Problem $problem): bool {
        return self::update(
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

    public static function updateChecker(Problem $problem): bool {
        return self::update(
            "Problems", [
                "checker" => $problem->checker
            ], "`id` = {$problem->id}"
        );
    }

    public static function updateTester(Problem $problem): bool {
        return self::update(
            "Problems", [
                "tester" => $problem->tester
            ], "`id` = {$problem->id}"
        );
    }

    public static function getProblem(int $problemId): ?array {
        $response = self::select(
            "Problems",
            "`id` = {$problemId}",
        );
        return !$response ? null : self::getResult($response);
    }

    public static function getAllProblems(): ?array {
        $response = self::select(
            "Problems",
            "`type` NOT IN ('game', 'relative', 'interactive')",
            "`id`"
        );
        return !$response ? null : self::getResults($response);
    }

    public static function getAllProblemsCount(): ?int {
        $response = self::count(
            "Problems",
            "`type` NOT IN ('game', 'relative', 'interactive')"
        );
        return !$response ? null : self::getIntResult($response);
    }

    public static function getAllGames(): ?array {
        $response = self::select(
            "Problems",
            "`type` IN ('game', 'relative', 'interactive')",
            "`id`"
        );
        return !$response ? null : self::getResults($response);
    }

    public static function getAllGamesCount(): ?int {
        $response = self::count(
            "Problems",
            "`type` IN ('game', 'relative', 'interactive')"
        );
        return !$response ? null : self::getIntResult($response);
    }

    /*
     * Solutions
     */
    public static function getProblemSolutions(int $problemId): ?array {
        $response = self::select(
            "Solutions",
            "`problemId` = {$problemId}"
        );
        return !$response ? null : self::getResults($response);
    }

    public static function addSolution(int $problemId, string $name, int $submitId, string $source, string $language): ?int {
        return self::insert(
            "Solutions", [
                "problemId" => $problemId,
                "name" => $name,
                "submitId" => $submitId,
                "source" => $source,
                "language" => $language
            ]
        );
    }

    public static function deleteSolution(int $problemId, string $name): bool {
        return self::delete(
            "Solutions",
            "`problemId` = {$problemId} AND `name` = '" . DB::escape($name) . "'"
        );
    }

    /*
     * Tests
     */
    public static function getProblemTests(int $problemId): ?array {
        $response = self::select(
            "Tests",
            "`problem` = {$problemId}",
            "`position`"
        );
        return !$response ? null : self::getResults($response);
    }

    // Find the ID of a testcase (or create a new one and get its ID if not present)
    public static function getTestId(int $problemId, int $position, bool $insertIfNotFound): ?int {
        $response = self::select(
            "Tests",
            "`problem` = {$problemId} AND `position` = {$position}"
        );
        $result = self::getResult($response);

        if ($result) {
            return $result['id'];
        }

        // Insert in the database if a new test
        if ($insertIfNotFound) {
            return self::insert(
                "Tests", [
                    "problem" => $problemId,
                    "position" => $position,
                    "score" => 10
                ]
            );
        }
        return null;
    }

    public static function updateTestScore(int $problemId, int $position, int $score): bool {
        $id = self::getTestId($problemId, $position, false);
        if ($id === null) {
            return false;
        }
        return self::update(
            "Tests", [
                "score" => $score
            ], "`id` = {$id}"
        );
    }

    public static function updateTestFile(int $problemId, int $position, string $name, string $hash): bool {
        $id = self::getTestId($problemId, $position, true);
        if ($id === null) {
            return false;
        }
        $extension = lastElement(explode('.', $name));
        return self::update(
            "Tests", [
                (($extension == 'in' || $extension == 'inp') ? 'inpFile' : 'solFile') => $name,
                (($extension == 'in' || $extension == 'inp') ? 'inpHash' : 'solHash') => $hash
            ], "`id` = {$id}"
        );
    }

    public static function deleteTest(int $problemId, int $position): bool {
        return self::delete(
            "Tests",
            "`problem` = {$problemId} AND `position` = {$position}"
        );
    }

    /*
     * Submits
     */
    public static function addSubmit(Submit $submit): ?int {
        return self::insert(
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

    public static function updateSubmit(Submit $submit): bool {
        return self::update(
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

    public static function getSubmit(int $submitId): ?array {
        $response = self::select(
            "Submits",
            "`id` = {$submitId}"
        );
        return !$response ? null : self::getResult($response);
    }

    public static function getAllSubmits(string $status="all"): ?array {
        $response = self::select(
            "Submits",
            $status == "all" ? "" : "`status` = '{$status}'"
        );
        return !$response ? null : self::getResults($response);
    }

    public static function getAllSubmitsCount(string $status="all"): ?int {
        $response = self::count(
            "Submits",
            $status == "all" ? "" : "`status` = '{$status}'"
        );
        return !$response ? null : self::getIntResult($response);
    }

    public static function getPendingSubmits(): ?array {
        $response = self::select(
            "Submits",
            "`status` IN (
                '{$GLOBALS['STATUS_WAITING']}',
                '{$GLOBALS['STATUS_PREPARING']}',
                '{$GLOBALS['STATUS_COMPILING']}',
                '{$GLOBALS['STATUS_TESTING']}'
            )",
            "`id`"
        );
        return !$response ? null : self::getResults($response);
    }

    public static function getLatestSubmits(): ?array {
        $response = self::select(
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
        return !$response ? null : self::getResults($response);
    }

    public static function getProblemSubmits(int $problemId, string $status="all"): ?array {
        $response = self::select(
            "Submits",
            "`problemId` = {$problemId}" . ($status == "all" ? "" : " AND `status` = '{$status}'"),
            "`submitted`"
        );
        return !$response ? null : self::getResults($response);
    }

    public static function getProblemStatusCounts(): ?array {
        $response = self::query("
            SELECT `problemId`, `status`, COUNT(*) AS `count` FROM `Submits`
            WHERE `userId` > 1
            GROUP BY `problemId`, `status`
        ");
        return !$response ? null : self::getResults($response);
    }

    public static function getUserSubmits(int $userId, int $problemId=-1, string $status="all"): ?array {
        $response = self::select(
            "Submits",
            "`userId` = {$userId}" .
                ($problemId == -1 ? "" : " AND `problemId` = {$problemId}") .
                ($status == "all" ? "" : " AND `status` = '{$status}'"),
            "`submitted`"
        );
        return !$response ? null : self::getResults($response);
    }

    public static function getSolved(int $userId): ?array {
        $response = self::query("
            SELECT DISTINCT `problemId` FROM `Submits`
            WHERE `userId` = {$userId} AND `status` = '{$GLOBALS['STATUS_ACCEPTED']}'
        ");
        return !$response ? null : self::getIntResults($response);
    }

    public static function getSolvedPerUser(): ?array {
        $response = self::query("
            SELECT `userId`, COUNT(*) AS `count` FROM (
                SELECT `userId`, `problemId` FROM `Submits`
                WHERE `status` = '{$GLOBALS['STATUS_ACCEPTED']}'
                GROUP BY `userId`, `problemId`
            ) AS tmp GROUP BY `userId`
        ");
        return !$response ? null : self::getResults($response);
    }

    /*
     * Sources
     */
    public static function addSource(Submit $submit): ?int {
        return self::insert(
            "Sources", [
                "submitId" => $submit->id,
                "userId" => $submit->userId,
                "problemId" => $submit->problemId,
                "language" => $submit->language,
                "source" => $submit->getSource()
            ]
        );
    }

    public static function getSource(int $submitId): ?array {
        $response = self::select(
            "Sources",
            "`submitId` = {$submitId}"
        );
        return !$response ? null : self::getResult($response);
    }

    public static function getAllSources(): ?array {
        $response = self::select(
            "Sources"
        );
        return !$response ? null : self::getResults($response);
    }

    public static function getUserSources(int $userId, int $problemId=-1): ?array {
        $response = self::select(
            "Sources",
            "`userId` = {$userId}" .
                ($problemId == -1 ? "" : " AND `problemId` = {$problemId}"),
            "`submitted`"
        );
        return !$response ? null : self::getResults($response);
    }

    /*
     * Users
     */
    public static function addUser(User $user): ?int {
        return self::insert(
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

    public static function updateUser(User $user): bool {
        return self::update(
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

    public static function getUser(int $userId): ?array {
        $response = self::select(
            "Users",
            "`id` = {$userId}"
        );
        return !$response ? null : self::getResult($response);
    }

    public static function getUserByUsername(string $username): ?array {
        $response = self::select(
            "Users",
            "`username` = '{$username}'"
        );
        return !$response ? null : self::getResult($response);
    }

    public static function getAllUsers(): ?array {
        $response = self::select(
            "Users",
            "",
            "`id`"
        );
        return !$response ? null : self::getResults($response);
    }

    public static function getAllUsersCount(): ?int {
        $response = self::count("Users");
        return !$response ? null : self::getIntResult($response);
    }

    /*
     * User info
     */
    public static function addUserInfo(User $user): bool {
        return self::insert(
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

    public static function updateUserInfo(User $user): bool {
        return self::update(
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

    public static function getUserInfo(int $userId): ?array {
        $response = self::select(
            "UsersInfo",
            "`id` = {$userId}"
        );
        return !$response ? null : self::getResult($response);
    }

    public static function getAllUsersInfo(): ?array {
        $response = self::select(
            "UsersInfo",
            "",
            "`id`"
        );
        return !$response ? null : self::getResults($response);
    }

    public static function getActiveUsersInfo(): ?array {
        $response = self::select(
            "UsersInfo",
            "`lastSeen` >= '" . date('Y-m-d H:i:s', time() - 15 * 60) . "'",
        );
        return !$response ? null : self::getResults($response);
    }

    /*
     * Credentials
     */
    public static function addCreds(int $userId, string $userName, string $password, string $loginKey): bool {
        return self::insert(
            "Credentials", [
                "userId" => $userId,
                "username" => $userName,
                "password" => $password,
                "loginKey" => $loginKey
            ]
        );
    }

    // TODO: The argument may be object? Either User or Credentials.
    public static function updateCreds(array $creds): bool {
        return self::update(
            "Credentials", [
                "password" => $creds['password'],
                "loginKey" => $creds['loginKey'],
                "resetKey" => $creds['resetKey'],
                "resetTime" => $creds['resetTime'],
                "lastReset" => $creds['lastReset']
            ], "`userId` = {$creds['userId']}"
        );
    }

    public static function getCreds(int $userId): ?array {
        $response = self::select(
            "Credentials",
            "`userId` = {$userId}"
        );
        return !$response ? null : self::getResult($response);
    }

    public static function getCredsByLoginKey(string $loginKey): ?array {
        $response = self::select(
            "Credentials",
            "`loginKey` = '{$loginKey}'"
        );
        return !$response ? null : self::getResult($response);
    }

    public static function getCredsByResetKey(string $resetKey): ?array {
        $response = self::select(
            "Credentials",
            "`resetKey` = '{$resetKey}'"
        );
        return !$response ? null : self::getResult($response);
    }

    /*
     * Achievements
     */
    public static function getAchievements(int $userId = -1): ?array {
        $response = self::select(
            "Achievements",
            $userId == -1 ? "" : "`user` = {$userId}",
            "`date`, `id` ASC"
        );
        return !$response ? null : self::getResults($response);
    }

    public static function addAchievement(int $userId, string $achievement, string $date): bool {
        return self::insertOrUpdate(
            "Achievements", [
                "user" => $userId,
                "achievement" => $achievement,
                "date" => $date
            ], [
                "user" => $userId
            ]
        );
    }

    public static function markAsSeenAchievement(int $achievementId): bool {
        return self::update(
            "Achievements", [
                "seen" => "TRUE"
            ], "`id` = {$achievementId}"
        );
    }

    /*
     * SPAM counters
     */
    public static function refreshSpamCounters(int $minTime): bool {
        return self::delete(
            "Spam",
            "`time` < {$minTime}"
        );
    }

    public static function getSpamCounter(User $user, int $type): ?int {
        $response = self::count(
            "Spam",
            "`user` = {$user->id} AND `type` = {$type}"
        );
        return !$response ? null : self::getIntResult($response);
    }

    public static function incrementSpamCounter(User $user, int $type, int $time): bool {
        return self::insert(
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
    public static function getMatch(int $problemId, int $test, int $userOne, int $userTwo): ?array {
        $response = self::select(
            "Matches",
            "`problemId` = {$problemId} AND `test` = {$test} AND `userOne` = {$userOne} AND `userTwo` = {$userTwo}"
        );
        return !$response ? null : self::getResult($response);
    }

    public static function getMatchById(int $matchId): ?array {
        $response = self::select(
            "Matches",
            "`id` = {$matchId}"
        );
        return !$response ? null : self::getResult($response);
    }

    public static function getGameMatches(int $problemId, int $userId = -1): ?array {
        $response = self::select(
            "Matches",
            "`problemId` = {$problemId}" . ($userId == -1 ? "" : "AND (`userOne` = {$userId} OR `userTwo` = {$userId})")
        );
        return !$response ? null : self::getResults($response);
    }

    public static function updateMatch(Match $match): bool {
        return self::insertOrUpdate(
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
    // TODO: Make arguments of this a class?
    public static function addTopic(int $id, string $key, string $link, string $title, string $summary, string $expanded, string $problems) {
        return self::insertOrUpdate(
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

    public static function getTopic(string $key): ?array {
        $response = self::select(
            "Training",
            "`key` = '{$key}'"
        );
        return !$response ? null : self::getResult($response);
    }

    public static function getTrainingTopics(): ?array {
        $response = self::select(
            "Training"
        );
        return !$response ? null : self::getResults($response);
    }

    /*
     * Regrading
     */
    // TODO: Change the `id` column to `key`, as it is confusing.
    public static function getRegradeList(string $id): ?array {
        $response = self::select(
            "Regrades",
            "`id` = '{$id}'"
        );
        return !$response ? null : self::getResults($response);
    }

    public static function getRegradeSubmit(string $id, Submit $submit): ?array {
        $response = self::select(
            "Regrades",
            "`id` = '{$id}' AND `submitId` = {$submit->id}"
        );
        return !$response ? null : self::getResults($response);
    }

    public static function addRegradeSubmit(string $id, Submit $submit): bool {
        return self::insert(
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

    public static function updateRegradeSubmit(string $id, Submit $submit): bool {
        return self::update(
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
    public static function addNotifications(int $userId, string $userName, string $messages, string $seen): ?int {
        return self::insert(
            "Notifications", [
                "userId" => $userId,
                "username" => $userName,
                "messages" => $messages,
                "seen" => $seen
            ]
        );
    }

    // TODO: Convert argument to an object.
    public static function updateNotifications(array $notifications): bool {
        return self::update(
            "Notifications", [
                "messages" => $notifications["messages"],
                "seen" => $notifications["seen"]
            ], "`userId` = {$notifications['userId']}"
        );
    }

    public static function getNotifications(int $userId): ?array {
        $response = self::select(
            "Notifications",
            "`userId` = {$userId}"
        );
        return !$response ? null : self::getResult($response);
    }

    /*
     * Messages
     */
    public static function getMessage(int $id): ?array {
        $response = self::select(
            "Messages",
            "`id` = {$id}"
        );
        return !$response ? null : self::getResult($response);
    }

    public static function getMessageByKey(string $key): ?array {
        $response = self::select(
            "Messages",
            "`key` = '{$key}'"
        );
        return !$response ? null : self::getResult($response);
    }

    public static function getAllMessages(): ?array {
        $response = self::select(
            "Messages"
        );
        return !$response ? null : self::getResults($response);
    }

    public static function getMessagesToEveryone(): ?array {
        $response = self::select(
            "Messages",
            "`userIds` = '-1'"
        );
        return !$response ? null : self::getResults($response);
    }

    public static function addMessage(): bool {
        return self::insert(
            "Messages", [
                "authorId" => -1
            ]
        );
   }

    public static function updateMessage(Message $message): bool {
        return self::update(
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
    public static function getHistory(int $submitId): ?array {
        $response = self::select(
            "History",
            "`submitId` = {$submitId}"
        );
        return !$response ? null : self::getResult($response);
    }

    public static function addHistory(int $submitId): bool {
        return self::insert(
            "History", [
                "submitId" => $submitId
            ]
        );
    }

    public static function updateHistory(int $submitId, array $info): bool {
        return self::update(
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
