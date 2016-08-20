<?php
require_once('db/db.php');
require_once('config.php');

class Brain {
    private $db;

    function __construct() {
        $this->db = new DB();
    }

    function getResult($sqlResponse) {
        $result = $sqlResponse->fetch_assoc();
        $sqlResponse->close();
        return $result;
    }

    function getResults($sqlResponse) {
        while ($row = $sqlResponse->fetch_assoc()) {
            $results[] = $row;
        }
        $sqlResponse->close();
        return $results;
    }

    function getIntResults($sqlResponse) {
        $asIntegers = array();
        while ($row = $sqlResponse->fetch_row()) {
            array_push($asIntegers, intval($row[0]));
        }
        $sqlResponse->close();
        return $asIntegers;
    }

    function publishNews($date, $title, $content) {
        $response = $this->db->query("
            INSERT INTO `News`(`date`, `title`, `content`)
            VALUES (
                '" . $this->db->escape($date) . "',
                '" . $this->db->escape($title) . "',
                '" . $this->db->escape($content) . "'
            );
        ");

        if (!$response) {
            error_log('Could not publish news with title "' . $title . '"!');
            return false;
        }
        return true;
    }

    function getNews() {
        $response = $this->db->query("
            SELECT * FROM `News` ORDER BY date DESC LIMIT 20;
        ");
        if (!$response) {
            error_log('Could not execute getNews() query properly!');
            return false;
        }
        return $this->getResults($response);
    }

    function getUsers() {
        $response = $this->db->query("
            SELECT * FROM `Users`;
        ");
        if (!$response) {
            error_log('Could not execute getUsers() query properly!');
            return false;
        }
        return $this->getResults($response);
    }

    function getSolved($userId) {
        $response = $this->db->query("
            SELECT DISTINCT `problem` FROM `Submits`
            WHERE `user` = " . $userId . " AND `status` = " . $GLOBALS['STATUS_ACCEPTED'] . ";
        ");
        if (!$response) {
            error_log('Could not execute getSolved() query for user with id ' . $userId . '!');
            return false;
        }
        return $this->getIntResults($response);
    }

    function getAchievements($userId) {
        $response = $this->db->query("
            SELECT DISTINCT `achievement` FROM `Achievements`
            WHERE `user` = " . $userId . ";
        ");
        if (!$response) {
            error_log('Could not execute getAchievements() query for user with id ' . $userId . '!');
            return false;
        }
        return $this->getIntResults($response);
    }

    function getUserByName($username) {
        $response = $this->db->query("
            SELECT * FROM `Users` WHERE username = '" . $username . "';
        ");
        if (!$response) {
            error_log('Could not execute getUserByName() query with username = "' . $username . '"!');
            return false;
        }
        return $this->getResult($response);
    }

    function updateUser($user) {
        $response = $this->db->query("
            UPDATE `Users` SET
                `access`=`" . $user->access . "`,
                `registered`=`" . $user->registered . "`,
                `username`=`" . $user->username . "`,
                `password`=`" . $user->password . "`,
                `name`=`" . $user->name . "`,
                `email`=`" . $user->email . "`,
                `town`=`" . $user->town . "`,
                `country`=`" . $user->country . "`,
                `gender`=`" . $user->gender . "`,
                `birthdate`=`" . $user->birthdate . "`,
                `avatar`=`" . $this->db->escape($user->avatar) . "`
            WHERE `id` = " . $user->id . ";
        ");
        if (!$response) {
            error_log('Could not update info for user "' . $user->username . '"!');
            return false;
        }
        return true;
    }

    function createUser($user) {
        $response = $this->db->query("
            INSERT INTO `Users` (`access`, `registered`, `username`, `password`, `name`, `email`, `town`, `country`, `gender`, `birthdate`, `avatar`)
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
            );
        ");
        if (!$response) {
            error_log('Could not create user "' . $user->username . '"!');
            return false;
        }
        return true;
    }

}

?>