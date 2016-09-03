<?php
require_once(__DIR__ . '/../db/db.php');
require_once(__DIR__ . '/config.php');

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

    // News
    function addNews($date, $title, $content) {
        $response = $this->db->query("
            INSERT INTO `News`(date, title, content)
            VALUES (
                '" . $this->db->escape($date) . "',
                '" . $this->db->escape($title) . "',
                '" . $this->db->escape($content) . "'
            )
        ");

        if (!$response) {
            error_log('Could not publish news with title "' . $title . '"!');
            return null;
        }
        return $this->db->lastId();
    }

    function getNews() {
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

    // Problems
    function updateProblem($problem) {
        $response = $this->db->query("
            UPDATE `Problems` SET
                name = `" . $problem->name . "`,
                author = `" . $problem->author . "`,
                folder = `" . $problem->folder . "`,
                timeLimit = `" . $problem->timeLimit . "`,
                memoryLimit = `" . $problem->memoryLimit . "`,
                type = `" . $problem->type . "`,
                difficulty = `" . $problem->difficulty . "`,
                tags = `" . implode(',', $probme->tags) . "`,
                origin = `" . $problem->origin . "`,
                checker = `" . $problem->checker . "`,
                executor = `" . $problem->executor . "`
            WHERE `id` = " . $problem->id . "
        ");
        if (!$response) {
            error_log('Could not update info for problem "' . $problem->name . '"!');
            return null;
        }
        return true;
    }

    function getProblem($problemId) {
        $response = $this->db->query("
            SELECT * FROM `Problems` where id = " . $problemId . "
        ");
        if (!$response) {
            error_log('Could not execute getProblem() query properly!');
            return null;
        }
        return $this->getResults($response);
    }

    function getAllProblems() {
        $response = $this->db->query("
            SELECT * FROM `Problems`
            ORDER BY id
        ");
        if (!$response) {
            error_log('Could not execute getAllProblems() query properly!');
            return null;
        }
        return $this->getResults($response);
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

    // Submits
    function addSubmit($submit) {
        $response = $this->db->query("
            INSERT INTO `Submits` (time, userId, userName, problemId, problemName, source, language, results, status, message)
            VALUES (
                '" . $submit->time . "',
                '" . $submit->userId . "',
                '" . $submit->userName . "',
                '" . $submit->problemId . "',
                '" . $submit->problemName . "',
                '" . $this->db->escape($submit->source) . "',
                '" . strtolower($submit->language) . "',
                '" . implode(',', $submit->results) . "',
                '" . $submit->status . "',
                '" . $this->db->escape($submit->message) . "'
            )
        ");
        if (!$response) {
            error_log('Could not add new submit from user "' . $submit->userName . '"!');
            return null;
        }
        return $this->db->lastId();
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

    function getProblemSubmits($problemId, $status) {
        $response = $this->db->query("
            SELECT id FROM `Submits`
            WHERE problemId = " . $problemId . " AND status = " . $status . "
            GROUP BY userId
        ");
        if (!$response) {
            error_log('Could not execute getProblemSubmits() query properly!');
            return null;
        }
        return $this->getIntResults($response);
    }

    function getUserSubmits($userId, $problemId) {
        $response = $this->db->query("
            SELECT * FROM `Submits`
            WHERE userId = " . $userId . " AND problemId = " . $problemId . "
        ");
        if (!$response) {
            error_log('Could not execute getUserSubmits() query with userId = ' . $userId . ' and problemId = ' . $problemId . '!');
            return null;
        }
        return $this->getResults($response);
    }

    function getSolved($userId) {
        $response = $this->db->query("
            SELECT DISTINCT problemId FROM `Submits`
            WHERE userId = " . $userId . " AND status = " . $GLOBALS['STATUS_ACCEPTED'] . "
        ");
        if (!$response) {
            error_log('Could not execute getSolved() query for user with id ' . $userId . '!');
            return null;
        }
        return $this->getIntResults($response);
    }

    // Pending
    function getPending() {
        $response = $this->db->query("
            SELECT * FROM `Pending`;
        ");
        if (!$response) {
            error_log('Could not execute getPending() query properly!');
            return null;
        }
        return $this->getResults($response);
    }

    function addPending($submit) {
        $response = $this->db->query("
            INSERT INTO `Pending`(submit, userId, userName, problemId, problemName, time, progress, status)
            VALUES (
                '" . $submit->id . "',
                '" . $submit->userId . "',
                '" . $submit->userName . "',
                '" . $submit->problemId . "',
                '" . $submit->problemName . "',
                '" . $submit->time . "',
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

    // Latest
    function getLatest() {
        $response = $this->db->query("
            SELECT * FROM `Latest`
        ");
        if (!$response) {
            error_log('Could not execute getLatest() query properly!');
            return null;
        }
        return $this->getResults($response);
    }

    // Users
    function addUser($user) {
        $response = $this->db->query("
            INSERT INTO `Users`(access, registered, username, password, name, email, town, country, gender, birthdate, avatar)
            VALUES (
                '" . $user->access . "',
                '" . $user->registered . "',
                '" . $user->username . "',
                '" . $user->password . "',
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
                access = `" . $user->access . "`,
                registered = `" . $user->registered . "`,
                username = `" . $user->username . "`,
                password = `" . $user->password . "`,
                name = `" . $user->name . "`,
                email = `" . $user->email . "`,
                town = `" . $user->town . "`,
                country = `" . $user->country . "`,
                gender = `" . $user->gender . "`,
                birthdate = `" . $user->birthdate . "`,
                avatar = `" . $this->db->escape($user->avatar) . "`
            WHERE id = " . $user->id . "
        ");
        if (!$response) {
            error_log('Could not update info for user "' . $user->username . '"!');
            return null;
        }
        return true;
    }

    function getUsers() {
        $response = $this->db->query("
            SELECT * FROM `Users`
        ");
        if (!$response) {
            error_log('Could not execute getUsers() query properly!');
            return null;
        }
        return $this->getResults($response);
    }

    function getUserByName($username) {
        $response = $this->db->query("
            SELECT * FROM `Users`
            WHERE username = '" . $username . "'
            LIMIT 1
        ");
        if (!$response) {
            error_log('Could not execute getUserByName() query with username = "' . $username . '"!');
            return null;
        }
        return $this->getResult($response);
    }

    // Achievements
    function getAchievements($userId) {
        $response = $this->db->query("
            SELECT DISTINCT achievement FROM `Achievements`
            WHERE user = " . $userId . "
        ");
        if (!$response) {
            error_log('Could not execute getAchievements() query for user with id ' . $userId . '!');
            return null;
        }
        return $this->getIntResults($response);
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
            INSERT INTO `Spam`(type, user, time)
            VALUES (
                " . $type . ", " . $user->id . ", " . $time . ")
        ");
        if (!$response) {
            error_log('Could not increment spam counter of type = ' . $type . ' for user ' . $user->name . '!');
            return null;
        }
        return true;
    }

}

?>