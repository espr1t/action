<?php
require_once(__DIR__ . "/../common.php");
require_once(__DIR__ . "/../db/brain.php");

class Message {
    private ?int $id;
    private ?string $key;
    private ?string $sent;
    private ?int $authorId;
    private ?string $authorName;
    private ?string $title;
    private ?string $content;
    private ?array $userIds;
    private ?array $userNames;

    public function __construct() {
        global $user;

        $this->id = -1;
        $this->key = "invalid";
        $this->sent = getUtcTime();
        $this->authorId = $user->getId();
        $this->authorName = $user->getUsername();
        $this->title = "Заглавие";
        $this->content = "";
        $this->userIds = [];
        $this->userNames = [];
    }

    public function getId(): int {return $this->id;}
    public function getKey(): string {return $this->key;}
    public function getSent(): string {return $this->sent;}
    public function getAuthorId(): int {return $this->authorId;}
    public function getAuthorName(): string {return $this->authorName;}
    public function getTitle(): string {return $this->title;}
    public function getContent(): string {return $this->content;}
    /** @return int[] */
    public function getUserIds(): array {return $this->userIds;}
    /** @return string[] */
    public function getUserNames(): array {return $this->userNames;}

    public function setId(int $id): void {$this->id = $id;}
    public function setKey(string $key): void {$this->key = $key;}
    public function setSent(string $sent): void {$this->sent = $sent;}
    public function setAuthorId(int $authorId): void {$this->authorId = $authorId;}
    public function setAuthorName(string $authorName): void {$this->authorName = $authorName;}
    public function setTitle(string $title): void {$this->title = $title;}
    public function setContent(string $content): void {$this->content = $content;}
    public function setUserIds(array $userIds): void {$this->userIds = $userIds;}
    public function setUserNames(array $userNames): void {$this->userNames = $userNames;}


    private static function instanceFromArray(array $info): Message {
        $message = new Message;
        $message->id = getIntValue($info, "id");
        $message->key = getStringValue($info, "key");
        $message->sent = getStringValue($info, "sent");
        $message->authorId = getIntValue($info, "authorId");
        $message->authorName = getStringValue($info, "authorName");
        $message->title = getStringValue($info, "title");
        $message->content = getStringValue($info, "content");
        $message->userIds = getIntArray($info, "userIds");
        $message->userNames = getStringArray($info, "userNames");
        return $message;
    }

    public static function get(int $id): ?Message {
        $info = Brain::getMessage($id);
        return $info == null ? null : Message::instanceFromArray($info);
    }

    public static function getByKey(string $key): ?Message {
        $info = Brain::getMessageByKey($key);
        return $info == null ? null : Message::instanceFromArray($info);
    }

    /** @return Message[] */
    public static function getAll(): array {
        return array_map(
            function ($entry) {
                return Message::instanceFromArray($entry);
            }, Brain::getAllMessages()
        );
    }

    /** @return Message[] */
    public static function getUserMessages(User $user): array {
        return array_map(
            function (int $messageId) {
                return Message::get($messageId);
            }, $user->getMessages()
        );
    }

    public function update(): bool {
        $oldVersion = Brain::getMessage($this->id);
        if (!Brain::updateMessage($this)) {
            return false;
        }
        $oldUserIds = parseIntArray($oldVersion["userIds"]);

        // See which users were removed from recipients
        foreach ($oldUserIds as $userId) {
            if ($userId == 0) {
                error_log("Got user id 0 from \$oldUserIds.");
            }
            if ($userId == -1)
                continue;

            if (!in_array($userId, $this->userIds)) {
                $notifications = Brain::getNotifications($userId);
                $notifications["messages"] = implode(",", array_filter(
                    parseIntArray($notifications["messages"]),
                    function($el) {return $el != $this->id;}
                ));
                $notifications["seen"] = implode(",", array_filter(
                    parseIntArray($notifications["seen"]),
                    function($el) {return $el != $this->id;}
                ));
                if (!Brain::updateNotifications($notifications))
                    return false;
            }
        }

        // See which users were added to recipients
        foreach ($this->userIds as $userId) {
            if ($userId == 0) {
                error_log("Got user id 0 from \$this->userIds");
            }
            if ($userId == -1)
                continue;

            if (!in_array($userId, $oldUserIds)) {
                $notifications = Brain::getNotifications($userId);
                $messages = parseIntArray($notifications["messages"]);
                if (!in_array($this->id, $messages)) {
                    array_push($messages, $this->id);
                }
                $notifications["messages"] = implode(",", $messages);
                if (!Brain::updateNotifications($notifications))
                    return false;
            }
        }

        return true;
    }

    public function send(): bool {
        $result = Brain::addMessage();
        if (!$result) {
            return false;
        }
        $this->id = $result;
        $this->key = randomString(7, "abcdefghijklmnopqrstuvwxyz");
        return $this->update();
    }

}

?>