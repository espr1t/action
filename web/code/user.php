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

    public function updateInfo() {
        $info = $this->arrayFromInstance();
        $fileName = sprintf('%s/user_%05d.json', $GLOBALS['PATH_USERS'], $this->id);
        $file = fopen($fileName, 'w') or die('Unable to create file ' . $fileName . '!');
        fwrite($file, json_encode($info, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    private function arrayFromInstance() {
        return array(
            'id' => $this->id,
            'access' => $this->access,
            'registered' => $this->registered,
            'username' => $this->username,
            'password' => $this->password,
            'name' => $this->name,
            'email' => $this->email,
            'town' => $this->town,
            'country' => $this->country,
            'gender' => $this->gender,
            'birthdate' => $this->birthdate,
            'avatar' => $this->avatar,
            'submissions' => $this->submissions,
            'tried' => $this->tried,
            'solved' => $this->solved
        );
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
        $user->avatar = getValue($info, 'avatar');
        $user->submissions = getValue($info, 'submissions');
        $user->tried = getValue($info, 'tried');
        $user->solved = getValue($info, 'solved');
        return $user;
    }

    public static function getUser($username) {
        $entries = scandir($GLOBALS['PATH_USERS']);
        foreach ($entries as $entry) {
            if (!preg_match(self::$user_info_re, basename($entry))) {
                continue;
            }
            $fileName = sprintf("%s/%s", $GLOBALS['PATH_USERS'], $entry);
            $info = json_decode(file_get_contents($fileName), true);
            if (strcasecmp($info['username'], $username) == 0) {
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
            $fileName = sprintf("%s/%s", $GLOBALS['PATH_USERS'], $entry);
            $info = json_decode(file_get_contents($fileName), true);
            if (strcasecmp($info['username'], $username) == 0) {
                return false;
            }
            $id = max(array($id, $info['id'] + 1));
       }

        $info = array(
            'id' => $id,
            'access' => $GLOBALS['DEFAULT_USER_ACCESS'],
            'registered' => date('Y-m-d'),
            'username' => $username,
            'password' => $password,
            'name' => $name . ' ' . $surname,
            'email' => $email,
            'town' => $town,
            'country' => $country,
            'gender' => $gender,
            'birthdate' => $birthdate,
            'avatar' => '',
            'submissions' => array(),
            'tried' => array(),
            'solved' => array()
        );

        $fileName = sprintf('%s/user_%05d.json', $GLOBALS['PATH_USERS'], $id);
        $file = fopen($fileName, 'w') or die('Unable to create file ' . $fileName . '!');
        fwrite($file, json_encode($info, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

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

    public function getName() {
        return $this->name;
    }

    public function getTown() {
        return $this->town;
    }

    public function getCountry() {
        return $this->country;
    }

    public function getBirthdate() {
        return $this->birthdate;
    }

    public function getGender() {
        return $this->gender;
    }

    public function getRegistered() {
        return $this->registered;
    }

    public function getAvatar() {
        return $this->avatar;
    }

    public function &getSolved() {
        return $this->solved;
    }

    public function &getTried() {
        return $this->tried;
    }

    public function &getSubmissions() {
        return $this->submissions;
    }
}

?>