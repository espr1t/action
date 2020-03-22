<?php
require_once(__DIR__ . '/../config.php');
require_once(__DIR__ . '/db.php');

class Brain {
    private $db;

    function __construct() {
        $this->db = new DB();
    }

    // mysqli result converters
    function getResult($sqlResponse) {
        $result = $sqlResponse->fetch_assoc();
        $sqlResponse->close();
        return $result;
    }

    function getResults($sqlResponse) {
        $results = array();
        while ($row = $sqlResponse->fetch_assoc()) {
            array_push($results, $row);
        }
        $sqlResponse->close();
        return $results;
    }

    function getIntResults($sqlResponse) {
        $results = array();
        while ($row = $sqlResponse->fetch_row()) {
            array_push($results, intval($row[0]));
        }
        $sqlResponse->close();
        return $results;
    }

    // Generic
    function getCount($tableName) {
        $response = $this->db->query("
            SELECT COUNT(*) FROM `" . $tableName . "`
        ");
        if (!$response) {
            error_log('Could not execute getCount() query on table `' . $tableName . '`!');
            return null;
        }
        return $this->getIntResults($response)[0];
    }

    // News
    function addNews() {
        $response = $this->db->query("
            INSERT INTO `News`(date, title, content, icon, type)
            VALUES ('', '', '', '', '')
        ");

        if (!$response) {
            error_log('Could not execute addNews() query properly!');
            return null;
        }
        return $this->db->lastId();
    }

    function updateNews($news) {
        $response = $this->db->query("
            UPDATE `News` SET
                date = '" . $news->date . "',
                title = '" . $this->db->escape($news->title) . "',
                content = '" . $this->db->escape($news->content) . "',
                icon = '" . $this->db->escape($news->icon) . "',
                type = '" . $this->db->escape($news->type) . "'
            WHERE id = " . $news->id . "
        ");
        if (!$response) {
            error_log('Could not update news with id "' . $news->id . '"!');
            return null;
        }
        return true;
    }

    function getAllNews() {
        $response = $this->db->query("
            SELECT * FROM `News`
            ORDER BY date DESC
            LIMIT 20
        ");
        if (!$response) {
            error_log('Could not execute getNews() query properly!');
            return null;
        }
        return $this->getResults($response);
    }

    function getNews($id) {
        $response = $this->db->query("
            SELECT * FROM `News`
            WHERE id = " . $id . "
            ORDER BY date DESC
            LIMIT 1
        ");
        if (!$response) {
            error_log('Could not execute getNews(' . $id . ') query properly!');
            return null;
        }
        return $this->getResult($response);
    }

    // Reports
    function addReport($user, $page, $content) {
        $response = $this->db->query("
            INSERT INTO `Reports`(user, date, page, content)
            VALUES ('" . $user . "', NOW(),
                '" . $this->db->escape($page) . "',
                '" . $this->db->escape($content) . "'
            )
        ");

        if (!$response) {
            error_log('Could not execute addReport() query properly!');
            return null;
        }
        return $this->db->lastId();
    }

    function getReports($userId = -1) {
        $response = $this->db->query("
            SELECT * FROM `Reports`
            " . ($userId != -1 ? ("WHERE user = " . $userId) : "") . "
        ");
        if (!$response) {
            error_log('Could not execute getReports(' . $userId . ') query properly!');
            return null;
        }
        return $this->getResults($response);
    }

    // Problems
    function addProblem() {
        $response = $this->db->query("
            INSERT INTO `Problems` (name, author, folder, timeLimit, memoryLimit, type, difficulty, statement, description, tags, origin, checker, tester, addedBy, visible)
            VALUES ('', '', '', 0, 0, 'ioi', 'trivial', '', '', '', '', '', '', '', '0')
        ");
        if (!$response) {
            error_log('Could not add new problem!');
            return null;
        }
        return $this->db->lastId();
    }

    function updateProblem($problem) {
        $response = $this->db->query("
            UPDATE `Problems` SET
                name = '" . $problem->name . "',
                author = '" . $problem->author . "',
                folder = '" . $problem->folder . "',
                timeLimit = '" . $problem->timeLimit . "',
                memoryLimit = '" . $problem->memoryLimit . "',
                type = '" . $problem->type . "',
                difficulty = '" . $problem->difficulty . "',
                statement = '" . $this->db->escape($problem->statement) . "',
                tags = '" . implode(',', $problem->tags) . "',
                origin = '" . $problem->origin . "',
                checker = '" . $problem->checker . "',
                tester = '" . $problem->tester . "',
                floats = '" . ($problem->floats ? '1' : '0') . "',
                addedBy = '" . $problem->addedBy . "',
                visible = '" . ($problem->visible ? '1' : '0') . "'
            WHERE id = " . $problem->id . "
        ");
        if (!$response) {
            error_log('Could not update info for problem "' . $problem->name . '"!');
            return null;
        }
        return true;
    }

    function updateChecker($problem) {
        $response = $this->db->query("
            UPDATE `Problems` SET
                checker = '" . $problem->checker . "'
            WHERE id = " . $problem->id . "
        ");
        if (!$response) {
            error_log('Could not update checker of problem "' . $problem->name . '"!');
            return null;
        }
        return true;
    }

    function updateTester($problem) {
        $response = $this->db->query("
            UPDATE `Problems` SET
                tester = '" . $problem->tester . "'
            WHERE id = " . $problem->id . "
        ");
        if (!$response) {
            error_log('Could not update tester of problem "' . $problem->name . '"!');
            return null;
        }
        return true;
    }

    function getProblem($problemId) {
        $response = $this->db->query("
            SELECT * FROM `Problems`
            WHERE id = " . $problemId . "
            LIMIT 1
        ");
        if (!$response) {
            error_log('Could not execute getProblem() query properly!');
            return null;
        }
        return $this->getResult($response);
    }

    function getAllProblems() {
        $response = $this->db->query("
            SELECT * FROM `Problems`
            WHERE type != 'game' AND type != 'relative' AND type != 'interactive'
            ORDER BY id
        ");
        if (!$response) {
            error_log('Could not execute getAllProblems() query properly!');
            return null;
        }
        return $this->getResults($response);
    }

    function getAllGames() {
        $response = $this->db->query("
            SELECT * FROM `Problems`
            WHERE type = 'game' OR type = 'relative' OR type = 'interactive'
            ORDER BY id
        ");
        if (!$response) {
            error_log('Could not execute getAllGames() query properly!');
            return null;
        }
        return $this->getResults($response);
    }

    // Solutions
    function getProblemSolutions($problemId) {
        $response = $this->db->query("
            SELECT * FROM `Solutions`
            WHERE problemId = " . $problemId . "
        ");
        if (!$response) {
            error_log('Could not execute getProblemSolutions() query properly!');
            return null;
        }
        return $this->getResults($response);
    }

    function addSolution($problemId, $name, $submitId, $source, $language) {
        $response = $this->db->query("
            INSERT INTO `Solutions` (problemId, name, submitId, source, language)
            VALUES (
                '" . $problemId . "',
                '" . $name . "',
                '" . $submitId . "',
                '" . $this->db->escape($source) . "',
                '" . $this->db->escape($language) . "'
            )
        ");
        if (!$response) {
            error_log('Could not add solution with name "' . $name . '"!');
            return null;
        }
        return $this->db->lastId();
    }

    function deleteSolution($problemId, $name) {
        $response = $this->db->query("
            DELETE FROM `Solutions`
            WHERE problemId = " . $problemId . " AND name = '" . $name . "'
        ");
        if (!$response) {
            error_log('Could not delete solution with name "' . $name . '"!');
            return null;
        }
        return true;
    }

    // Tests
    function getProblemTests($problemId) {
        $response = $this->db->query("
            SELECT * FROM `Tests`
            WHERE problem = " . $problemId . "
            ORDER BY position
        ");
        if (!$response) {
            error_log('Could not execute getProblemTests() query properly!');
            return null;
        }
        return $this->getResults($response);
    }

    function getTestId($problemId, $position, $insertIfNotFound) {
        // Find the ID of the testcase (or create a new one and get its ID if not present)
        $response = $this->db->query("
            SELECT * FROM `Tests`
            WHERE problem = " . $problemId . " AND position = " . $position . "
            LIMIT 1
        ");
        if (!$response) {
            error_log('Could not execute updateTest() query properly!');
            return -1;
        }
        $result = $this->getResult($response);

        $id = -1;
        if (!$result) {
            // Insert in the database if a new test
            if ($insertIfNotFound) {
                $response = $this->db->query("
                    INSERT INTO `Tests` (problem, position, inpFile, inpHash, solFile, solHash, score)
                    VALUES (" . $problemId . ", " . $position . ", '', '', '', '', 10)
                ");
                if (!$response) {
                    error_log('Could not add new test for problem ' . $problemId . ' with position ' . $position . '!');
                    return null;
                }
                $id = $this->db->lastId();
            }
        } else {
            $id = $result['id'];
        }
        return $id;
    }

    function updateTestScore($problemId, $position, $score) {
        $id = $this->getTestId($problemId, $position, false);
        if ($id == -1) {
            return null;
        }
        $response = $this->db->query("
            UPDATE `Tests` SET
                score = " . $score . "
            WHERE id = " . $id . "
        ");
        if (!$response) {
            error_log('Could not update test score for problem ' . $problemId . ' with position ' . $position . '!');
            return null;
        }
        return true;
    }

    function updateTestFile($problemId, $position, $name, $hash) {
        $id = $this->getTestId($problemId, $position, true);
        if ($id == -1) {
            return null;
        }

        $extension = end(explode('.', $name));
        $response = $this->db->query("
            UPDATE `Tests` SET
                " . (($extension == 'in' || $extension == 'inp') ? 'inpFile' : 'solFile') . " = '" . $name . "',
                " . (($extension == 'in' || $extension == 'inp') ? 'inpHash' : 'solHash') . " = '" . $hash . "'
            WHERE id = " . $id . "
        ");
        if (!$response) {
            error_log('Could not update test file for problem ' . $problemId . ' with position ' . $position . '!');
            return null;
        }
        return true;
    }

    function deleteTest($problemId, $position) {
        $response = $this->db->query("
            DELETE FROM `Tests`
            WHERE problem = " . $problemId . " AND position = " . $position . "
        ");
        if (!$response) {
            error_log('Could not delete test from problem ' . $problemId . ' at position ' . $position . '!');
            return null;
        }
        return true;
    }

    // Submits
    function addSubmit($submit) {
        $response = $this->db->query("
            INSERT INTO `Submits` (submitted, graded, userId, userName, problemId, problemName, language, results, exec_time, exec_memory, status, message, full, hidden, ip, info)
            VALUES (
                '" . $submit->submitted . "',
                '" . $submit->graded . "',
                '" . $submit->userId . "',
                '" . $submit->userName . "',
                '" . $submit->problemId . "',
                '" . $submit->problemName . "',
                '" . $this->db->escape($submit->language) . "',
                '" . implode(',', $submit->results) . "',
                '" . implode(',', $submit->exec_time) . "',
                '" . implode(',', $submit->exec_memory) . "',
                '" . $submit->status . "',
                '" . $this->db->escape($submit->message) . "',
                '" . ($submit->full ? 1 : 0) . "',
                '" . ($submit->hidden ? 1 : 0) . "',
                '" . $this->db->escape($submit->ip) . "',
                '" . $this->db->escape($submit->info) . "'
            )
        ");
        if (!$response) {
            error_log('Could not add new submit from user "' . $submit->userName . '"!');
            return null;
        }
        return $this->db->lastId();
    }

    function addSource($submit) {
        $response = $this->db->query("
            INSERT INTO `Sources` (submitId, userId, problemId, language, source)
            VALUES (
                '" . $submit->id . "',
                '" . $submit->userId . "',
                '" . $submit->problemId . "',
                '" . $this->db->escape($submit->language) . "',
                '" . $this->db->escape($submit->source) . "'
            )
        ");
        if (!$response) {
            error_log('Could not add source for submit "' . $submit->id . '"!');
            return null;
        }
        return $this->db->lastId();
    }

    function getSource($submitId) {
        $response = $this->db->query("
            SELECT * FROM `Sources`
            WHERE submitId = " . $submitId . "
            LIMIT 1
        ");
        if (!$response) {
            error_log('Could not execute getSource() query for submit = ' . $submitId . '!');
            return null;
        }
        return $this->getResult($response);
    }

    function updateSubmit($submit) {
        $response = $this->db->query("
            UPDATE `Submits` SET
                graded = '" . $submit->graded . "',
                results = '" . implode(',', $submit->results) . "',
                exec_time = '" . implode(',', $submit->exec_time) . "',
                exec_memory = '" . implode(',', $submit->exec_memory) . "',
                status = '" . $submit->status . "',
                message = '" . $this->db->escape($submit->message) . "',
                info = '" . $this->db->escape($submit->info) . "'
            WHERE id = " . $submit->id . "
        ");
        if (!$response) {
            error_log('Could not update submit with id ' . $submit->id . '!');
            return null;
        }
        return true;
    }

    function getSubmit($submitId) {
        $response = $this->db->query("
            SELECT * FROM `Submits`
            WHERE id = " . $submitId . "
            LIMIT 1
        ");
        if (!$response) {
            error_log('Could not execute getSubmit() query with submitId = ' . $submitId . '!');
            return null;
        }
        return $this->getResult($response);
    }

    function getAllSubmits($status = 'all') {
        $response = $this->db->query("
            SELECT * FROM `Submits`
            " . ($status == 'all' ? '' : 'WHERE status = \'' . $status . '\'') . "
        ");
        if (!$response) {
            error_log('Could not execute getAllSubmits() query!');
            return null;
        }
        return $this->getResults($response);
    }

    function getAllSources() {
        $response = $this->db->query("
            SELECT * FROM `Sources`
        ");
        if (!$response) {
            error_log('Could not execute getAllSources() query!');
            return null;
        }
        return $this->getResults($response);
    }

    function getProblemSubmits($problemId, $status = 'all') {
        if ($status == 'all') {
            $response = $this->db->query("
                SELECT * FROM `Submits`
                WHERE problemId = " . $problemId . "
            ");
        } else {
            $response = $this->db->query("
                SELECT * FROM `Submits`
                WHERE problemId = " . $problemId . " AND status = '" . $status . "'
                ORDER BY submitted
            ");
        }
        if (!$response) {
            error_log('Could not execute getProblemSubmits() query properly!');
            return null;
        }
        return $this->getResults($response);
    }

    function getUserSubmits($userId, $problemId = -1) {
        $response = $this->db->query("
            SELECT * FROM `Submits`
            WHERE userId = " . $userId . ($problemId == -1 ? '' : (' AND problemId = ' . $problemId)) . "
        ");
        if (!$response) {
            error_log('Could not execute getUserSubmits() query with userId = ' . $userId . ' and problemId = ' . $problemId . '!');
            return null;
        }
        return $this->getResults($response);
    }

    function getUserSources($userId, $problemId = -1) {
        $response = $this->db->query("
            SELECT * FROM `Sources`
            WHERE userId = " . $userId . ($problemId == -1 ? '' : (' AND problemId = ' . $problemId)) . "
        ");
        if (!$response) {
            error_log('Could not execute getUserSources() query with userId = ' . $userId . ' and problemId = ' . $problemId . '!');
            return null;
        }
        return $this->getResults($response);
    }

    function getSolved($userId) {
        $response = $this->db->query("
            SELECT DISTINCT problemId FROM `Submits`
            WHERE userId = " . $userId . " AND status = '" . $GLOBALS['STATUS_ACCEPTED'] . "'
        ");
        if (!$response) {
            error_log('Could not execute getSolved() query for user with id ' . $userId . '!');
            return null;
        }
        return $this->getIntResults($response);
    }

    function getAllSolved() {
        $response = $this->db->query("
            SELECT DISTINCT problemId, userId FROM `Submits`
            WHERE status = '" . $GLOBALS['STATUS_ACCEPTED'] . "'
        ");
        if (!$response) {
            error_log('Could not execute getAllSolved() query!');
            return null;
        }
        return $this->getResults($response);
    }

    // Pending
    function getPending() {
        $response = $this->db->query("
            SELECT * FROM `Pending` ORDER BY submitId ASC
        ");
        if (!$response) {
            error_log('Could not execute getPending() query properly!');
            return null;
        }
        return $this->getResults($response);
    }

    function addPending($submit) {
        $response = $this->db->query("
            INSERT INTO `Pending`(submitId, userId, userName, problemId, problemName, time, language, progress, status)
            VALUES (
                '" . $submit->id . "',
                '" . $submit->userId . "',
                '" . $submit->userName . "',
                '" . $submit->problemId . "',
                '" . $submit->problemName . "',
                '" . $submit->submitted . "',
                '" . $submit->language . "',
                '" . 0 . "',
                '" . $submit->status . "'
            )
        ");
        if (!$response) {
            error_log('Could not add submit ' . $submit->id . ' to pending queue!');
            return null;
        }
        return $this->db->lastId();
    }

    function updatePending($submit) {
        $response = $this->db->query("
            UPDATE `Pending` SET
                progress = '" . $submit->calcProgress() . "'
            WHERE submitId = " . $submit->id . "
        ");

        if (!$response) {
            error_log('Could not update submit ' . $submit->id . ' in pending queue!');
            return null;
        }
        return true;
    }

    function erasePending($submitId) {
        $response = $this->db->query("
            DELETE FROM `Pending`
            WHERE submitId = " . $submitId . "
        ");
        if (!$response) {
            error_log('Could not erase submit ' . $submitId . ' from pending queue!');
            return null;
        }
        return true;
    }

    // Latest
    function getLatest() {
        $response = $this->db->query("
            SELECT * FROM `Latest` ORDER BY submitId DESC
        ");
        if (!$response) {
            error_log('Could not execute getLatest() query properly!');
            return null;
        }
        return $this->getResults($response);
    }

    function addLatest($submit) {
        $response = $this->db->query("
            INSERT INTO `Latest`(submitId, userId, userName, problemId, problemName, time, language, progress, status)
            VALUES (
                '" . $submit->id . "',
                '" . $submit->userId . "',
                '" . $submit->userName . "',
                '" . $submit->problemId . "',
                '" . $submit->problemName . "',
                '" . $submit->submitted . "',
                '" . $submit->language . "',
                '" . 1 . "',
                '" . $submit->status . "'
            )
        ");
        if (!$response) {
            error_log('Could not add submit ' . $submit->id . ' to latest queue!');
            return null;
        }
        return $this->db->lastId();
    }

    function eraseLatest($submitId) {
        $response = $this->db->query("
            DELETE FROM `Latest`
            WHERE submitId = " . $submitId . "
        ");
        if (!$response) {
            error_log('Could not erase submit ' . $submitId . ' from latest queue!');
            return null;
        }
        return true;
    }

    function trimLatest($submitId) {
        $response = $this->db->query("
            DELETE FROM `Latest` WHERE submitId <= " . ($submitId - 100) . "
        ");
        if (!$response) {
            error_log('Could not trim Latest submits!');
            return null;
        }
        return true;
    }

    // Users
    function addUser($user) {
        $response = $this->db->query("
            INSERT INTO `Users`(access, registered, username, name, email, town, country, gender, birthdate, avatar)
            VALUES (
                '" . $user->access . "',
                '" . $user->registered . "',
                '" . $user->username . "',
                '" . $user->name . "',
                '" . $user->email . "',
                '" . $user->town . "',
                '" . $user->country . "',
                '" . $user->gender . "',
                '" . $user->birthdate . "',
                '" . $this->db->escape($user->avatar) . "'
            )
        ");
        if (!$response) {
            error_log('Could not create user "' . $user->username . '"!');
            return null;
        }
        return $this->db->lastId();
    }

    function updateUser($user) {
        $response = $this->db->query("
            UPDATE `Users` SET
                access = '" . $user->access . "',
                registered = '" . $user->registered . "',
                username = '" . $user->username . "',
                password = '" . $user->password . "',
                loginKey = '" . $user->loginKey . "',
                name = '" . $user->name . "',
                email = '" . $user->email . "',
                town = '" . $user->town . "',
                country = '" . $user->country . "',
                gender = '" . $user->gender . "',
                birthdate = '" . $user->birthdate . "',
                avatar = '" . $this->db->escape($user->avatar) . "'
            WHERE id = " . $user->id . "
        ");
        if (!$response) {
            error_log('Could not update info for user "' . $user->username . '"!');
            return null;
        }
        return true;
    }

    function getUser($userId) {
        $response = $this->db->query("
            SELECT * FROM `Users`
            WHERE id = '" . $userId . "'
            LIMIT 1
        ");
        if (!$response) {
            error_log('Could not execute getUser() query with id = "' . $userId . '"!');
            return null;
        }
        return $this->getResult($response);
    }

    function getUserByUsername($username) {
        $response = $this->db->query("
            SELECT * FROM `Users`
            WHERE username = '" . $username . "'
            LIMIT 1
        ");
        if (!$response) {
            error_log('Could not execute getUserByUsername() query with username = "' . $username . '"!');
            return null;
        }
        return $this->getResult($response);
    }

    function getAllUsers() {
        $response = $this->db->query("
            SELECT * FROM `Users` ORDER BY id
        ");
        if (!$response) {
            error_log('Could not execute getAllUsers() query properly!');
            return null;
        }
        return $this->getResults($response);
    }

    // User Info
    function addUserInfo($user) {
        $response = $this->db->query("
            INSERT INTO `UsersInfo`(id, username, actions, totalTime, lastSeen, profileViews, lastViewers, loginCount)
            VALUES (
                '" . $user->id . "',
                '" . $user->username . "',
                '" . $user->actions . "',
                '" . $user->totalTime . "',
                '" . $user->lastSeen . "',
                '" . $user->profileViews . "',
                '" . $user->lastViewers . "',
                '" . $user->loginCount . "'
            )
        ");
        if (!$response) {
            error_log('Could not add user info for user "' . $user->username . '"!');
            return false;
        }
        return true;
    }

    function updateUserInfo($user) {
        $response = $this->db->query("
            UPDATE `UsersInfo` SET
                actions = '" . $user->actions . "',
                totalTime = '" . $user->totalTime . "',
                lastSeen = '" . $user->lastSeen . "',
                profileViews = '" . $user->profileViews . "',
                lastViewers = '" . $user->lastViewers . "',
                loginCount = '" . $user->loginCount . "',
                lastIP = '" . $user->lastIP . "'
            WHERE id = " . $user->id . "
        ");
        if (!$response) {
            error_log('Could not update activity info for user "' . $user->username . '"!');
            return null;
        }
        return true;
    }

    function getUserInfo($userId) {
        $response = $this->db->query("
            SELECT * FROM `UsersInfo`
            WHERE id = '" . $userId . "'
            LIMIT 1
        ");
        if (!$response) {
            error_log('Could not execute getUserInfo() query with id = "' . $userId . '"!');
            return null;
        }
        return $this->getResult($response);
    }

    function getAllUsersInfo() {
        $response = $this->db->query("
            SELECT * FROM `UsersInfo` ORDER BY id
        ");
        if (!$response) {
            error_log('Could not execute getAllUsersInfo() query properly!');
            return null;
        }
        return $this->getResults($response);
    }

    // Credentials
    function addCredentials($userId, $userName, $password, $loginKey) {
        $response = $this->db->query("
            INSERT INTO `Credentials`(userId, username, password, loginKey, resetKey, resetTime)
            VALUES (
                '" . $userId . "',
                '" . $userName . "',
                '" . $password . "',
                '" . $loginKey . "',
                '',
                ''
            )
        ");
        if (!$response) {
            error_log('Could not insert credentials entry for username "' . $userName . '"!');
            return null;
        }
        return true;
    }

    function updateCreds($creds) {
        $response = $this->db->query("
            UPDATE `Credentials` SET
                password = '" . $creds['password'] . "',
                loginKey = '" . $creds['loginKey'] . "',
                resetKey = '" . $creds['resetKey'] . "',
                resetTime = '" . $creds['resetTime'] . "',
                lastReset = '" . $creds['lastReset'] . "'
            WHERE userId = " . $creds['userId'] . "
        ");
        if (!$response) {
            error_log('Could not update credentials for user "' . $creds['username'] . '"!');
            return null;
        }
        return true;
    }

    function getCreds($userId) {
        $response = $this->db->query("
            SELECT * FROM `Credentials`
            WHERE userId = '" . $userId . "'
            LIMIT 1
        ");
        if (!$response) {
            error_log('Could not execute getCreds() query with id = "' . $userId . '"!');
            return null;
        }
        return $this->getResult($response);
    }

    function getCredsByLoginKey($loginKey) {
        $response = $this->db->query("
            SELECT * FROM `Credentials`
            WHERE loginKey = '" . $loginKey . "'
            LIMIT 1
        ");

        if (!$response) {
            error_log('Could not execute getCredsByLoginKey() query with loginKey = "' . $loginKey . '"!');
            return null;
        }
        return $this->getResult($response);
    }

    function getCredsByResetKey($resetKey) {
        $response = $this->db->query("
            SELECT * FROM `Credentials`
            WHERE resetKey = '" . $resetKey . "'
            LIMIT 1
        ");

        if (!$response) {
            error_log('Attempt to reset password with invalid resetKey: "' . $resetKey . '"!');
            return null;
        }
        return $this->getResult($response);
    }

    // Achievements
    function getAchievements($userId = -1) {
        $response = $this->db->query("
            SELECT * FROM `Achievements`
            " . ($userId == -1 ? '' : 'WHERE user = ' . $userId) . "
            ORDER BY date, id ASC
        ");
        if (!$response) {
            error_log('Could not execute getAchievements() query for user with id ' . $userId . '!');
            return null;
        }
        return $this->getResults($response);
    }

    function addAchievement($userId, $achievement, $date) {
        $response = $this->db->query("
            INSERT INTO `Achievements` (user, achievement, date)
            VALUES(" . $userId . ", '" . $achievement ."', '" . $date . "')
            ON DUPLICATE KEY UPDATE user = " . $userId . "
        ");
        if (!$response) {
            error_log('Could not execute addAchievements() query for user with id ' . $userId . ' and achievement "' . $achievement . '"!');
            return null;
        }
        return true;
    }

    function markAsSeenAchievement($achievementId) {
        $response = $this->db->query("
            UPDATE `Achievements` SET
                seen = TRUE
            WHERE id = " . $achievementId . "
        ");
        if (!$response) {
            error_log('Could not execute markAsSeenAchievement() query for achievement with id ' . $achievementId . '"!');
            return null;
        }
        return true;
    }

    // Spam counters
    function refreshSpamCounters($minTime) {
        $response = $this->db->query("
            DELETE FROM `Spam`
            WHERE time < " . $minTime . "
        ");
        if (!$response) {
            error_log('Could not refresh spam counters with minTime = ' . $minTime . '!');
            return null;
        }
        return true;
    }

    function getSpamCounter($user, $type) {
        $response = $this->db->query("
            SELECT time FROM `Spam`
            WHERE user = " . $user->id . " AND type = " . $type . "
        ");
        if (!$response) {
            error_log('Could not get spam counter of type = ' . $type . ' for user ' . $user->name . '!');
            return null;
        }
        return count($this->getResults($response));
    }

    function incrementSpamCounter($user, $type, $time) {
        $response = $this->db->query("
            INSERT INTO `Spam` (type, user, time)
            VALUES (
                " . $type . ", " . $user->id . ", " . $time . ")
        ");
        if (!$response) {
            error_log('Could not increment spam counter of type = ' . $type . ' for user ' . $user->name . '!');
            return null;
        }
        return true;
    }

    // Games
    function getMatch($problemId, $test, $userOne, $userTwo) {
        $response = $this->db->query("
            SELECT * FROM `Matches`
            WHERE problemId = " . $problemId . " AND test = " . $test . " AND userOne = " . $userOne . " AND userTwo = " . $userTwo . "
            LIMIT 1
        ");
        if (!$response) {
            error_log('Could not execute getMatch() query properly!');
            return null;
        }
        return $this->getResult($response);
    }

    function getMatchById($matchId) {
        $response = $this->db->query("
            SELECT * FROM `Matches`
            WHERE id = " . $matchId . "
            LIMIT 1
        ");
        if (!$response) {
            error_log('Could not execute getMatchById() query properly!');
            return null;
        }
        return $this->getResult($response);
    }

    function getGameMatches($problemId, $userId='all') {
        if ($userId == 'all') {
            $response = $this->db->query("
                SELECT * FROM `Matches`
                WHERE problemId = " . $problemId . "
            ");
        } else {
            $response = $this->db->query("
                SELECT * FROM `Matches`
                WHERE problemId = " . $problemId . " AND (userOne = " . $userId . " OR userTwo = " . $userId . ")
            ");
        }
        if (!$response) {
            error_log('Could not execute getGameMatches() query properly!');
            return null;
        }
        return $this->getResults($response);
    }

    function updateMatch($match) {
        $response = $this->db->query("
            INSERT INTO `Matches` (problemId, test, userOne, userTwo, submitOne, submitTwo, scoreOne, scoreTwo, message, log)
                VALUES (
                    '" . $match->problemId . "',
                    '" . $match->test . "',
                    '" . $match->userOne . "',
                    '" . $match->userTwo . "',
                    '" . $match->submitOne . "',
                    '" . $match->submitTwo . "',
                    '" . $match->scoreOne . "',
                    '" . $match->scoreTwo . "',
                    '" . $this->db->escape($match->message) . "',
                    '" . $this->db->escape($match->log) . "'
                )
            ON DUPLICATE KEY UPDATE
                submitOne = '" . $match->submitOne . "',
                submitTwo = '" . $match->submitTwo . "',
                scoreOne = '" . $match->scoreOne . "',
                scoreTwo = '" . $match->scoreTwo . "',
                message = '" . $this->db->escape($match->message) . "',
                log = '" . $this->db->escape($match->log) . "'
        ");
        if (!$response) {
            error_log('Could not add or update match on problem ' . $match->problemId . '!');
            return null;
        }
        return true;
    }

    // Training
    function addTopic($id, $key, $link, $title, $summary, $expanded, $problems) {
        $response = $this->db->query("
            INSERT INTO `Training` (`id`, `key`, `link`, `title`, `summary`, `expanded`, `problems`)
            VALUES(" . $id . ", '" . $key . "', '" . $link . "', '" . $title . "', '" . $summary . "', '" . $expanded . "', '" . $problems . "')
            ON DUPLICATE KEY UPDATE link = '" . $link . "', title = '" . $title . "', summary = '" . $summary . "', expanded = '" . $expanded . "', problems = '" . $problems . "'
        ");
        if (!$response) {
            error_log('Could not execute addTopic() query for topic "' . $key . '"!');
            return null;
        }
        return true;
    }

    function getTopic($key) {
        $response = $this->db->query("
            SELECT * FROM `Training`
            WHERE `key` = '" . $key . "'
        ");
        if (!$response) {
            error_log('Could not execute getTopic() query for topic "' . $key . '"!');
            return null;
        }
        return $this->getResult($response);
    }

    function getTrainingTopics() {
        $response = $this->db->query("
            SELECT * FROM `Training`
        ");
        if (!$response) {
            error_log('Could not execute getTrainingTopics() query!');
            return null;
        }
        return $this->getResults($response);
    }

    // Regrading
    function getRegradeList($id) {
        $response = $this->db->query("
            SELECT * FROM `Regrades`
            WHERE id = '" . $id . "'
        ");
        if (!$response) {
            error_log('Could not execute getRegradeList() query for regradeId "' . $id . '"!');
            return null;
        }
        return $this->getResults($response);
    }

    function getRegradeSubmit($id, $submit) {
        $response = $this->db->query("
            SELECT * FROM `Regrades`
            WHERE id = '" . $id . "' AND submitId = '" . $submit->id . "'
        ");
        if (!$response) {
            error_log('Could not execute getRegradeSubmit() query for regradeId "' . $id . '" and submitId "' . $submit->id . '"!');
            return null;
        }
        return $this->getResult($response);
    }

    function addRegradeSubmit($id, $submit) {
        $maxTime = max($submit->exec_time);
        $maxMemory = max($submit->exec_memory);
        $response = $this->db->query("
            INSERT INTO `Regrades` (id, submitId, userName, problemName, submitted, regraded, oldTime, newTime, oldMemory, newMemory, oldStatus, newStatus)
            VALUES('" . $id . "',
                   '" . $submit->id . "',
                   '" . $submit->userName . "',
                   '" . $submit->problemName . "',
                   '" . $submit->submitted . "',
                   '" . date('Y-m-d H:i:s') . "',
                   '" . $maxTime . "',
                   '" . -1.0 . "',
                   '" . $maxMemory . "',
                   '" . -1.0 . "',
                   '" . $submit->status . "',
                   '" . $GLOBALS['STATUS_WAITING'] . "'
            )
        ");
        if (!$response) {
            error_log('Could not execute addRegradeSubmit() query for regradeId "' . $id . '" and submitId "' . $submit->id . '"!');
            return null;
        }
        return true;
    }

    function updateRegradeSubmit($id, $submit) {
        $maxTime = max($submit->exec_time);
        $maxMemory = max($submit->exec_memory);
        $response = $this->db->query("
            UPDATE `Regrades` SET
                newTime = '" . $maxTime . "',
                newMemory = '" . $maxMemory . "',
                newStatus = '" . $submit->status . "'
            WHERE id = '" . $id . "' AND submitId = '" . $submit->id . "'
        ");
        if (!$response) {
            error_log('Could not execute updateRegradeSubmit() query for regradeId "' . $id . '" and submitId "' . $submit->id . '"!');
            return null;
        }
        return true;
    }

    // Notifications
    function addNotifications($userId, $userName, $messages, $seen) {
        $response = $this->db->query("
            INSERT INTO `Notifications`(userId, username, messages, seen)
            VALUES (
                '" . $userId . "',
                '" . $userName . "',
                '" . $messages . "',
                '" . $seen . "'
            )
        ");
        if (!$response) {
            error_log('Could not insert notifications entry for username "' . $userName . '"!');
            return null;
        }
        return true;
    }

    function updateNotifications($notifications) {
        $response = $this->db->query("
            UPDATE `Notifications` SET
                messages = '" . $notifications['messages'] . "',
                seen = '" . $notifications['seen'] . "'
            WHERE userId = " . $notifications['userId'] . "
        ");
        if (!$response) {
            error_log('Could not update notifications for user "' . $notifications['username'] . '"!');
            return null;
        }
        return true;
    }

    function getNotifications($userId) {
        $response = $this->db->query("
            SELECT * FROM `Notifications`
            WHERE userId = '" . $userId . "'
            LIMIT 1
        ");

        if (!$response) {
            error_log('Could not execute getNotifications() query for userId "' . $userId . '"!');
            return null;
        }
        return $this->getResult($response);
    }

    function getMessage($id) {
        $response = $this->db->query("
            SELECT * FROM `Messages`
            WHERE id = '" . $id . "'
        ");
        if (!$response) {
            error_log('Could not execute getMessage() query with id = "' . $id . '"!');
            return null;
        }
        return $this->getResult($response);
    }

    function getMessageByKey($key) {
        $response = $this->db->query("
            SELECT * FROM `Messages`
            WHERE `key` = '" . $key . "'
        ");
        if (!$response) {
            error_log('Could not execute getMessageByKey() query with key = "' . $key . '"!');
            return null;
        }
        return $this->getResult($response);
    }

    function getAllMessages() {
        $response = $this->db->query("
            SELECT * FROM `Messages`
        ");
        if (!$response) {
            error_log('Could not execute getAllMessages() query!');
            return null;
        }
        return $this->getResults($response);
    }

    function getMessagesToEveryone() {
        $response = $this->db->query("
            SELECT * FROM `Messages`
            WHERE userIds = '-1'
        ");
        if (!$response) {
            error_log('Could not execute getMessagesToEveryone() query!');
            return null;
        }
        return $this->getResults($response);
    }

    function addMessage() {
        $response = $this->db->query("
            INSERT INTO `Messages` (`key`, `sent`, `authorId`, `authorName`, `title`, `content`, `userIds`, `userNames`)
            VALUES ('', '', -1, '', '', '', '', '')
        ");

        if (!$response) {
            error_log('Could not execute addMessage() query properly!');
            return null;
        }
        return $this->db->lastId();
    }

    function updateMessage($message) {
        $response = $this->db->query("
            UPDATE `Messages` SET
                `key` = '" . $message->key . "',
                `sent` = '" . $message->sent . "',
                `authorId` = '" . $message->authorId . "',
                `authorName` = '" . $message->authorName . "',
                `title` = '" . $this->db->escape($message->title) . "',
                `content` = '" . $this->db->escape($message->content) . "',
                `userIds` = '" . implode(',', $message->userIds) . "',
                `userNames` = '" . implode(',', $message->userNames) . "'
            WHERE `id` = " . $message->id . "
        ");
        if (!$response) {
            error_log('Could not update message with id "' . $message->id . '"!');
            return null;
        }
        return true;
    }


    // Submit run history
    function getHistory($submitId) {
        $response = $this->db->query("
            SELECT * FROM `History`
            WHERE submitId = '" . $submitId . "'
            LIMIT 1
        ");

        if (!$response) {
            error_log('Could not execute getHistory() query for submitId "' . $submitId . '"!');
            return null;
        }
        return $this->getResult($response);
    }

    function addHistory($submitId) {
        $response = $this->db->query("
            INSERT INTO `History` (submitId, time01, time02, time03, time04, time05)
            VALUES ('" . $submitId . "', '', '', '', '', '')
        ");
        if (!$response) {
            error_log('Could not insert new entry in addHistory() for submitId "' . $submitId . '"!');
            return null;
        }
        return true;
    }

    function updateHistory($submitId, $info) {
        $response = $this->db->query("
            UPDATE `History` SET
                time01 = '" . $info['time01'] . "',
                time02 = '" . $info['time02'] . "',
                time03 = '" . $info['time03'] . "',
                time04 = '" . $info['time04'] . "',
                time05 = '" . $info['time05'] . "'
            WHERE submitId = '" . $submitId . "'
        ");
        if (!$response) {
            error_log('Could not execute updateHistory() query for submitId "' . $submitId . '"!');
            return null;
        }
        return true;
    }

}

?>
