<?php
require_once(__DIR__ . '/../config.php');
require_once(__DIR__ . '/../common.php');
require_once(__DIR__ . '/../db/brain.php');

class User {
    public static $user_info_re = '/^user_\d{5}\.json$/';

    public $id = -1;
    public $access = 0;
    public $registered = '1970-01-01';
    public $username = 'anonymous';
    public $name = '';
    public $email = '';
    public $town = '';
    public $country = '';
    public $gender = '';
    public $birthdate = '';
    public $avatar = '';
    public $actions = 0;
    public $totalTime = 0;
    public $lastSeen = '2017-01-01 00:00:00';
    public $profileViews = 0;
    public $lastViewers = '';
    public $loginCount = 0;
    public $lastIP = '';

    public function logOut() {
        setcookie($GLOBALS['COOKIE_NAME'], null, -1);
        session_destroy();
        session_start();

        // Record the logout to the logs
        $logMessage = sprintf('User %s has logged out.', $this->username);
        write_log($GLOBALS['LOG_LOGOUTS'], $logMessage);

        redirect('/login', 'INFO', 'Успешно излязохте от системата.');
    }

    public function update(): bool {
        return Brain::updateUser($this);
    }

    public function updateStats() {
        if ($this->id >= 1) {
            $this->actions += 1;

            // If last seen more than 5 minutes ago, count 5 minutes of
            // activity, otherwise add the difference between now and then.
            $this->totalTime += min(array(time() - strtotime($this->lastSeen), 5 * 60));
            $this->lastSeen = date('Y-m-d H:i:s', time());
            $this->lastIP = getUserIP();
            Brain::updateUserInfo($this);
        }
    }

    private static function parseMainInfo($user, $info) {
        $user->id = intval(getValue($info, 'id'));
        $user->access = getValue($info, 'access');
        $user->registered = getValue($info, 'registered');
        $user->username = getValue($info, 'username');
        $user->name = getValue($info, 'name');
        $user->email = getValue($info, 'email');
        $user->town = getValue($info, 'town');
        $user->country = getValue($info, 'country');
        $user->gender = getValue($info, 'gender');
        $user->birthdate = getValue($info, 'birthdate');
        $user->avatar = getValue($info, 'avatar');
    }

    private static function parseSecondaryInfo($user, $info) {
        $user->id = intval(getValue($info, 'id'));
        $user->username = getValue($info, 'username');
        $user->actions = intval(getValue($info, 'actions'));
        $user->totalTime = intval(getValue($info, 'totalTime'));
        $user->lastSeen = getValue($info, 'lastSeen');
        $user->profileViews = intval(getValue($info, 'profileViews'));
        $user->lastViewers = getValue($info, 'lastViewers');
        $user->loginCount = intval(getValue($info, 'loginCount'));
        $user->lastIP = getValue($info, 'lastIP');
    }

    public static function instanceFromArray($main, $secondary) {
        $user = new User;
        if ($main != null)
            User::parseMainInfo($user, $main);
        if ($secondary != null)
            User::parseSecondaryInfo($user, $secondary);
        return $user;
    }

    public static function get($userKey) {
        if (is_numeric($userKey)) {
            $main = Brain::getUser($userKey);
        } else {
            $main = Brain::getUserByUsername($userKey);
        }
        if (!$main) return null;
        $info = Brain::getUserInfo($main['id']);
        return User::instanceFromArray($main, $info);
    }

    public static function getByLoginKey($loginKey) {
        if ($loginKey == '')
            return null;
        $creds = Brain::getCredsByLoginKey($loginKey);
        return !$creds ? null : User::get($creds['userId']);
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
        $user->name = $name . ' ' . $surname;
        $user->email = $email;
        $user->town = $town;
        $user->country = $country;
        $user->gender = $gender;
        $user->birthdate = $birthdate;
        // $user->avatar = $avatar;

        $user->id = Brain::addUser($user);
        if (!$user->id) {
            return null;
        }

        // Add user info entry
        Brain::addUserInfo($user);

        // Add credentials entry
        Brain::addCreds($user->id, $username, $password, '');

        // Add notifications entry
        Brain::addNotifications($user->id, $username, '', '');

        // Grant admin rights to the first user
        if ($user->id == 1) {
            $user->access = $GLOBALS['ADMIN_USER_ACCESS'];
            $user->update();
        }

        // Record the user creation in the logs
        $logMessage = sprintf('User %s has been registered.', $user->username);
        write_log($GLOBALS['LOG_REGISTERS'], $logMessage);

        return $user;
    }

    static public function getActive() {
        return array_map(
            function ($entry) {
                // printArray($entry);
                return User::instanceFromArray(null, $entry);
            }, Brain::getActiveUsersInfo()
        );
    }

    public function getMessages() {
        $notifications = Brain::getNotifications($this->id);
        $toEveryone = Brain::getMessagesToEveryone();
        $messages = parseIntArray($notifications['messages']);
        foreach ($toEveryone as $message) {
            // Only show messages sent after the user registered (except the welcome message)
            if ($message['id'] == 1 || $message['sent'] >= $this->registered->format('Y-m-d')) {
                if (!in_array($message['id'], $messages)) {
                    array_push($messages, $message['id']);
                }
            }
        }
        sort($messages);
        return $messages;
    }

    public function numUnreadMessages() {
        $notifications = Brain::getNotifications($this->id);
        $seen = parseIntArray($notifications['seen']);
        $messages = $this->getMessages();
        return count($messages) - count($seen);
    }
}

?>