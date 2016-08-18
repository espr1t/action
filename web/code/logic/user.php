<?php
require_once('common.php');

class User {
    public static $user_info_re = '/^user_\d{5}\.json$/';

    public $id = -1;
    public $access = 0;
    public $registered = '1970-01-01';
    public $username = 'anonymous';
    public $password = 'abracadabra';
    public $name = '';
    public $email = '';
    public $town = '';
    public $country = '';
    public $gender = '';
    public $birthdate = '';
    public $avatar = '';
    public $submits = array();
    
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
            'submits' => $this->submits
        );
    }

    private static function instanceFromArray($info) {
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
        $user->submits = getValue($info, 'submits');
        return $user;
    }

    public static function get($username) {
        $entries = scandir($GLOBALS['PATH_USERS']);
        foreach ($entries as $entry) {
            if (!preg_match(self::$user_info_re, basename($entry))) {
                continue;
            }
            $fileName = sprintf("%s/%s", $GLOBALS['PATH_USERS'], $entry);
            $info = json_decode(file_get_contents($fileName), true);
            if (strcasecmp($info['username'], $username) == 0) {
                return User::instanceFromArray($info);
            }
       }
       return null;
    }

    private function update() {
        $info = $this->arrayFromInstance();
        $fileName = sprintf('%s/user_%05d.json', $GLOBALS['PATH_USERS'], $this->id);
        $file = fopen($fileName, 'w');
        if (!$file) {
            error_log('Unable to open file ' . $fileName . ' for writing!');
            return false;
        }
        if (!fwrite($file, json_encode($info, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
            error_log('Unable to write to file ' . $fileName . '!');
            return false;
        }
        return true;
    }

    public function addSubmission($id) {
        array_push($this->submits, $id);
        return $this->update();
    }

    public function logOut() {
        setcookie($GLOBALS['COOKIE_NAME'], null, -1);
        session_destroy();
        header('Location: /login?action=success');
        exit();
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
            'submits' => array()
        );

        $fileName = sprintf('%s/user_%05d.json', $GLOBALS['PATH_USERS'], $id);
        $file = fopen($fileName, 'w') or die('Unable to create file ' . $fileName . '!');
        fwrite($file, json_encode($info, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return true;
   }
}

?>