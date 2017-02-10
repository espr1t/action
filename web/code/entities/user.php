<?php
require_once(__DIR__ . '/../config.php');
require_once(__DIR__ . '/../db/brain.php');
require_once(__DIR__ . '/widgets.php');

class User {
    public static $user_info_re = '/^user_\d{5}\.json$/';

    public $id = -1;
    public $access = 0;
    public $registered = '1970-01-01';
    public $username = 'anonymous';
    public $password = 'abracadabra';
    public $loginKey = '';
    public $name = '';
    public $email = '';
    public $town = '';
    public $country = '';
    public $gender = '';
    public $birthdate = '';
    public $avatar = '';
    
    public function logOut() {
        setcookie($GLOBALS['COOKIE_NAME'], null, -1);
        session_destroy();
        session_start();
        redirect('/login', 'INFO', 'Успешно излязохте от системата.');
    }

    public function update() {
        $brain = new Brain();
        return $brain->updateUser($this);
    }

    private static function instanceFromArray($info) {
        $user = new User;
        $user->id = getValue($info, 'id');
        $user->access = getValue($info, 'access');
        $user->registered = getValue($info, 'registered');
        $user->username = getValue($info, 'username');
        $user->password = getValue($info, 'password');
        $user->loginKey = getValue($info, 'loginKey');
        $user->name = getValue($info, 'name');
        $user->email = getValue($info, 'email');
        $user->town = getValue($info, 'town');
        $user->country = getValue($info, 'country');
        $user->gender = getValue($info, 'gender');
        $user->birthdate = getValue($info, 'birthdate');
        $user->avatar = getValue($info, 'avatar');
        return $user;
    }

    public static function get($userKey) {
        $brain = new Brain();
        if (is_numeric($userKey)) {
            $result = $brain->getUser($userKey);
        } else {
            $result = $brain->getUserByUsername($userKey);
        }
        return !$result ? null : User::instanceFromArray($result);
    }

    public static function getByLoginKey($loginKey) {
        $brain = new Brain();
        $result = $brain->getUserByLoginKey($loginKey);
        return !$result ? null : User::instanceFromArray($result);
    }

    public static function createUser($username, $name, $surname, $password, $email, $birthdate, $town, $country, $gender) {
        // Check if user with the same username already exists.
        if (User::get($username) != null) {
            return false;
        }

        $user = new User();
        $user->access = $GLOBALS['DEFAULT_USER_ACCESS'];
        $user->registered = date('Y-m-d');
        $user->username = $username;
        $user->password = $password;
        $user->loginKey = '';
        $user->name = $name . ' ' . $surname;
        $user->email = $email;
        $user->town = $town;
        $user->country = $country;
        $user->gender = $gender;
        $user->birthdate = $birthdate;
        // $user->avatar = $avatar;

        $brain = new Brain();
        $user->id = $brain->addUser($user);
        if (!$user->id) {
            return null;
        }

        // Grant admin rights to the first user
        if ($user->id == 1) {
            $user->access = $GLOBALS['ADMIN_USER_ACCESS'];
            $user->update();
        }

        return $user;
   }
}

?>