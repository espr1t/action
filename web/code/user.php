<?php
require_once('common.php');

class User {
    public static $user_info_re = '/^user_\d{5}\.json$/';

    private $id = -1;
    private $access = 0;
    private $registered = '1970-01-01';
    private $username = 'anonymous';
    private $password = 'abracadabra';
    private $name = '';
    private $email = '';
    private $town = '';
    private $country = '';
    private $gender = '';
    private $birthdate = '';
    private $avatar = '';
    private $submissions = array();
    private $tried = array();
    private $solved = array();
    private $contests = array();
    
    public function logOut() {
        setcookie($GLOBALS['COOKIE_NAME'], null, -1);
        session_destroy();
        header('Location: /login?action=success');
        exit();
    }

    private static function instanceFromJson($info) {
        $user = new User;
        $user->id = getValue($info, 'id');
        $user->access = getValue($info, 'access');
        $user->registered = getValue($info, 'registered');
        $user->username = getValue($info, 'username');
        $user->password = getValue($info, 'password');
        $user->name = getValue($info, 'name');
        $user->email = getValue($info, 'email');
        $user->town = getValue($info, 'town');
        $user->country = getValue($info, 'country');
        $user->gender = getValue($info, 'gender');
        $user->birthdate = getValue($info, 'birthdate');
        $user->submissions = getValue($info, 'submissions');
        $user->tried = getValue($info, 'tried');
        $user->solved = getValue($info, 'solved');
        $user->contests = getValue($info, 'contests');
        return $user;
    }

    public static function getUser($username) {
        $entries = scandir($GLOBALS['PATH_USERS']);
        foreach ($entries as $entry) {
            if (!preg_match(self::$user_info_re, basename($entry))) {
                continue;
            }
            $json = file_get_contents($GLOBALS['PATH_USERS'] . $entry);
            $info = json_decode($json, true);
            if ($info['username'] == $username) {
                return User::instanceFromJson($info);
            }
       }
       return null;
    }

    public static function createUser($username, $name, $surname, $password, $email, $birthdate, $town, $country, $gender) {
        // Check if user with the same username already exists.
        $id = 0;
        $entries = scandir($GLOBALS['PATH_USERS']);
        foreach ($entries as $entry) {
            if (!preg_match(self::$user_info_re, basename($entry))) {
                continue;
            }
            $json = file_get_contents($GLOBALS['PATH_USERS'] . $entry);
            $info = json_decode($json, true);
            if ($info['username'] == $username) {
                return false;
            }
            $id = max(array($id, $info['id'] + 1));
       }

        $info = array(
            'id' => $id,
            'access' => 1,
            'registered' => date('Y-m-d'),
            'username' => $username,
            'password' => $password,
            'name' => $name . ' ' . $surname,
            'email' => $email,
            'town' => $town,
            'country' => $country,
            'gender' => $gender,
            'birthdate' => $birthdate,
            'submissions' => array(),
            'tried' => array(),
            'solved' => array(),
            'contests' => array()
        );

        $fileName = sprintf('%s/user_%05d.json', $GLOBALS['PATH_USERS'], $id);
        $userFile = fopen($fileName, 'w') or die('Unable to create file ' . $fileName . '!');
        fwrite($userFile, json_encode($info, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return true;
   }

    public function getId() {
        return $this->id;
    }

    public function getUsername() {
        return $this->username;
    }

    public function getPassword() {
        return $this->password;
    }

    public function getAccess() {
        return $this->access;
    }
}

?>