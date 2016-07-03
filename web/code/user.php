<?php
require_once('common.php');

class User {
    private static $user_info_re = '/^user_\d{5}\.json$/';

    private $id = 0;
    private $username = 'anonymous';
    private $name = '';
    private $surname = '';
    private $password = '';
    private $town = '';
    private $email = '';
    private $birthdate = '';
    private $gender = '';
    
    public function getId() {
        return $this->id;
    }
    
    public function getUsername() {
        return $this->username;
    }
    
    public function logOut() {
        // TODO
    }

    public static function findUser($username) {
        $entries = scandir($GLOBALS['PATH_USERS']);
        foreach ($entries as $entry) {
            if (!preg_match(self::$user_info_re, basename($entry))) {
                continue;
            }
            $json = file_get_contents($GLOBALS['PATH_USERS'] . $entry);
            $info = json_decode($json);
            if ($info->{'username'} == $username) {
                return  $info->{'id'};
            }
       }
       return -1;
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
            $info = json_decode($json);
            if ($info->{'username'} == $username) {
                return false;
            }
            $id = max(array($id, $info->{'id'} + 1));
       }

        $info = array(
            'id' => $id,
            'access' => 1,
            'registered' => date('Y-m-d'),
            'username' => $username,
            'name' => $name . ' ' . $surname,
            'email' => $email,
            'town' => $town,
            'country' => $country,
            'gender' => $gender,
            'date_of_birth' => $birthdate,
            'avatar' => '',
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

}

?>