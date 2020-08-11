<?php
require_once(__DIR__ . '/../common.php');
require_once(__DIR__ . '/../db/brain.php');

class Message {
    public $id = -1;
    public $key = '';
    public $sent = '';
    public $authorId = -1;
    public $authorName = '';
    public $title = '';
    public $content = '';
    public $userIds = array();
    public $userNames = array();

    public function __construct() {
        $this->id = -1;
        $this->key = 'invalid';
        $this->sent = getUtcTime();
        $this->authorId = $GLOBALS['user']->id;
        $this->authorName = $GLOBALS['user']->username;
        $this->title = 'Заглавие';
        $this->content = '';
        $this->userIds = [];
        $this->userNames = [];
    }

    private static function instanceFromArray($info) {
        $message = new Message;
        $message->id = intval(getValue($info, 'id'));
        $message->key = getValue($info, 'key');
        $message->sent = getValue($info, 'sent');
        $message->authorId = intval(getValue($info, 'authorId'));
        $message->authorName = getValue($info, 'authorName');
        $message->title = getValue($info, 'title');
        $message->content = getValue($info, 'content');
        $message->userIds = parseIntArray(getValue($info, 'userIds'));
        $message->userNames = parseStringArray(getValue($info, 'userNames'));
        return $message;
    }

    public static function get($key) {
        $brain = new Brain();
        try {
            $info = $brain->getMessageByKey($key);
            return $info == null ? null : Message::instanceFromArray($info);
        } catch (Exception $ex) {
            error_log('Could not get message with id ' . $id . '. Exception: ' . $ex->getMessage());
        }
        return null;
    }

    public function update() {
        $brain = new Brain();
        $oldVersion = $brain->getMessage($this->id);
        if (!$brain->updateMessage($this)) {
            return false;
        }
        $oldUserIds = parseIntArray($oldVersion['userIds']);

        // See which users were removed from recipients
        foreach ($oldUserIds as $userId) {
            if ($userId == 0) {
                error_log("Got user id 0 from \$oldUserIds");
            }
            if ($userId == -1)
                continue;

            if (!in_array($userId, $this->userIds)) {
                $notifications = $brain->getNotifications($userId);
                $notifications['messages'] = implode(',', array_filter(
                    parseIntArray($notifications['messages']),
                    function($el) {return $el != $this->id;}
                ));
                $notifications['seen'] = implode(',', array_filter(
                    parseIntArray($notifications['seen']),
                    function($el) {return $el != $this->id;}
                ));
                if (!$brain->updateNotifications($notifications))
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
                $notifications = $brain->getNotifications($userId);
                $messages = parseIntArray($notifications['messages']);
                if (!in_array($this->id, $messages)) {
                    array_push($messages, $this->id);
                }
                $notifications['messages'] = implode(',', $messages);
                if (!$brain->updateNotifications($notifications))
                    return false;
            }
        }

        return true;
    }

    public function send() {
        $brain = new Brain();
        $result = $brain->addMessage();
        if (!$result) {
            return false;
        }
        $this->id = $result;
        $this->key = randomString(7, 'abcdefghijklmnopqrstuvwxyz');
        return $this->update();
    }

}

?>