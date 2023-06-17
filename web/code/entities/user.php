<?php
require_once(__DIR__ . '/../config.php');
require_once(__DIR__ . '/../common.php');
require_once(__DIR__ . '/../db/brain.php');

class User {
    private ?int $id = null;
    private ?int $access = null;
    private ?string $registered = null;
    private ?string $username = null;
    private ?string $name = null;
    private ?string $email = null;
    private ?string $town = null;
    private ?string $country = null;
    private ?string $gender = null;
    private ?string $birthdate = null;
    private ?string $avatar = null;
    private ?int $actions = null;
    private ?int $totalTime = null;
    private ?string $lastSeen = null;
    private ?int $profileViews = null;
    private ?array $lastViewers = null;
    private ?int $loginCount = null;
    private ?string $lastIP = null;

    public function getId(): int {return $this->id;}
    public function getAccess(): int {return $this->access;}
    public function getRegistered(): string {return $this->registered;}
    public function getUsername(): string {return $this->username;}
    public function getName(): string {return $this->name;}
    public function getEmail(): string {return $this->email;}
    public function getTown(): string {return $this->town;}
    public function getCountry(): string {return $this->country;}
    public function getGender(): string {return $this->gender;}
    public function getBirthdate(): string {return $this->birthdate;}
    public function getAvatar(): string {return $this->avatar;}
    public function getActions(): int {return $this->actions;}
    public function getTotalTime(): int {return $this->totalTime;}
    public function getLastSeen(): string {return $this->lastSeen;}
    public function getProfileViews(): int {return $this->profileViews;}
    public function getLastViewers(): array {return $this->lastViewers;}
    public function getLoginCount(): int {return $this->loginCount;}
    public function getLastIP(): string {return $this->lastIP;}

    public function __construct() {
        $this->id = -1;
        $this->access = 0;
        $this->registered = "1970-01-01";
        $this->username = "anonymous";
        $this->name = "";
        $this->email = "";
        $this->town = "";
        $this->country = "";
        $this->gender = "";
        $this->birthdate = "";
        $this->avatar = "";
        $this->actions = 0;
        $this->totalTime = 0;
        $this->lastSeen = "2017-01-01 00:00:00";
        $this->profileViews = 0;
        $this->lastViewers = array();
        $this->loginCount = 0;
        $this->lastIP = "";
    }

    private static function parseMainInfo(User $user, array $info): void {
        $user->id = getIntValue($info, "id");
        $user->access = getIntValue($info, "access");
        $user->registered = getStringValue($info, "registered");
        $user->username = getStringValue($info, "username");
        $user->name = getStringValue($info, "name");
        $user->email = getStringValue($info, "email");
        $user->town = getStringValue($info, "town");
        $user->country = getStringValue($info, "country");
        $user->gender = getStringValue($info, "gender");
        $user->birthdate = getStringValue($info, "birthdate");
        $user->avatar = getStringValue($info, "avatar");
    }

    private static function parseSecondaryInfo(User $user, array $info): void {
        $user->id = getIntValue($info, "id");
        $user->username = getStringValue($info, "username");
        $user->actions = getIntValue($info, "actions");
        $user->totalTime = getIntValue($info, "totalTime");
        $user->lastSeen = getStringValue($info, "lastSeen");
        $user->profileViews = getIntValue($info, "profileViews");
        $user->lastViewers = getStringArray($info, "lastViewers");
        $user->loginCount = getIntValue($info, "loginCount");
        $user->lastIP = getStringValue($info, "lastIP");
    }

    private static function instanceFromArrays(?array $main, ?array $secondary): ?User {
        if (!$main && !$secondary)
            return null;
        if (!$main && !($main = Brain::getUserById($secondary["id"])))
            return null;
        if (!$secondary && !($secondary = Brain::getUserInfoById($main["id"])))
            return null;

        $user = new User;
        User::parseMainInfo($user, $main);
        User::parseSecondaryInfo($user, $secondary);
        return $user;
    }

    public static function getById(int $id): ?User {
        return self::instanceFromArrays(Brain::getUserById($id), null);
    }

    public static function getByUsername(string $username): ?User {
        return self::instanceFromArrays(Brain::getUserByUsername($username), null);
    }

    public static function getByLoginKey(string $loginKey): ?User {
        if ($loginKey == "")
            return null;
        $credentials = Brain::getCredsByLoginKey($loginKey);
        return !$credentials ? null : User::getById(intval($credentials["userId"]));
    }

    public function update(): bool {
        return Brain::updateUser($this) && Brain::updateUserInfo($this);
    }

    public function updateStats(): bool {
        if ($this->getId() >= 1) {
            $this->actions += 1;

            // If last seen more than 5 minutes ago, count 5 minutes of
            // activity, otherwise add the difference between now and then.
            $this->totalTime += min(array(time() - strtotime($this->lastSeen), 5 * 60));
            $this->lastSeen = date("Y-m-d H:i:s", time());
            $this->lastIP = getUserIP();
            return Brain::updateUserInfo($this);
        }
        return true;
    }

    public function updateProfileViews(User $viewer): bool {
        // Already one of the last viewers
        if (in_array($viewer->getUsername(), $this->getLastViewers()))
            return true;

        array_unshift($this->lastViewers, $viewer->getUsername());
        if (count($this->lastViewers) > 5) {
            $this->lastViewers = array_slice($this->lastViewers, 0, 5);
        }
        $this->profileViews++;
        return Brain::updateUserInfo($this);
    }

    public function updateLoginCount(): void {
        $this->loginCount++;
        Brain::updateUserInfo($this);
    }

    public static function createUser(string $username, string $name, string $surname, string $password, string $email,
            string $birthdate, string $town, string $country, string $gender): ?User {
        // Check if user with the same username already exists.
        if (User::getByUsername($username) != null) {
            return null;
        }

        $user = new User();
        $user->access = $GLOBALS["DEFAULT_USER_ACCESS"];
        $user->registered = date("Y-m-d");
        $user->username = $username;
        $user->name = "{$name} {$surname}";
        $user->email = $email;
        $user->town = $town;
        $user->country = $country;
        $user->gender = $gender;
        $user->birthdate = $birthdate;

        // Add the user to the DB or return null if an error occurs
        $user->id = Brain::addUser($user);
        if ($user->id === null) {
            return null;
        }

        // Grant admin rights to the first user
        if ($user->id == 1) {
            $user->access = $GLOBALS["ADMIN_USER_ACCESS"];
            $user->update();
        }

        // Add user info entry
        Brain::addUserInfo($user);

        // Add credentials entry
        Brain::addCreds($user->id, $username, $password, "");

        // Add notifications entry
        Brain::addNotifications($user->id, $username, [], []);

        // Record the user creation in the logs
        write_log($GLOBALS["LOG_REGISTERS"], "User {$user->getUsername()} has been registered.");
        return $user;
    }

    /** @return User[] */
    static public function getActive(): array {
        return array_map(
            function ($entry) {
                return User::instanceFromArrays(null, $entry);
            }, Brain::getActiveUsersInfo()
        );
    }

    /** @return User[] */
    static public function getAllUsers(): array {
        $main = Brain::getAllUsers();
        $secondary = Brain::getAllUsersInfo();
        $users = array();
        for ($i = 0; $i < count($main); $i++) {
            $users[] = self::instanceFromArrays($main[$i], $secondary[$i]);
        }
        return $users;
    }

    /** @return int[] */
    public function getMessages(): array {
        $notifications = Brain::getNotifications($this->getId());
        $toEveryone = Brain::getMessagesToEveryone();
        $messages = parseIntArray($notifications["messages"]);
        foreach ($toEveryone as $message) {
            // Only show messages sent after the user registered (except the welcome message)
            if ($message["id"] == 1 || $message["sent"] >= $this->registered) {
                if (!in_array($message["id"], $messages)) {
                    array_push($messages, $message["id"]);
                }
            }
        }
        sort($messages);
        return $messages;
    }

    public function numUnreadMessages(): int {
        $notifications = Brain::getNotifications($this->id);
        $seen = parseIntArray($notifications["seen"]);
        $messages = $this->getMessages();
        return count($messages) - count($seen);
    }

    public function logOut(): void {
        setcookie($GLOBALS["COOKIE_NAME"], null, -1);
        session_destroy();
        session_start();

        // Record the logout to the logs
        write_log($GLOBALS["LOG_LOGOUTS"], "User {$this->username} has logged out.");
        redirect("/login", "INFO", "Успешно излязохте от системата.");
    }

}

?>