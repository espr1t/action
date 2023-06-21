<?php
require_once("db/brain.php");
require_once("entities/message.php");
require_once("common.php");
require_once("page.php");

class MessagesPage extends Page {
    public function getTitle(): string {
        return "O(N)::Messages";
    }

    /**
     * @param Message[] $messages
     * @param int[] $seen
     * @return string
     */
    public static function getMessageList(array $messages, ?array $seen): string {
        $baseUrl = explode("/messages", getCurrentUrl())[0] . "/messages/";
        $messageList = "";
        foreach ($messages as $message) {
            $preview = $message->getContent();
            if (strlen($preview) > 250) {
                $preview = mb_substr($preview, 0, 247) . "...";
            }
            // TODO: Consider using date_create_immutable_from_format()
            $date = explode(" ", $message->getSent())[0];
            $time = explode(" ", $message->getSent())[1];
            $seenIcon = ($seen == null || in_array($message->getId(), $seen)) ? "" :
                            "<span style='color: #D84A38;'><i class='fas fa-bell'></i></span>";
            $messageBox = "
                <div class='box boxlink'>
                    <div class='message-seen'>{$seenIcon}</div>
                    <div class='message-title'>{$message->getTitle()}</div>
                    <div class='message-content'>{$preview}</div>
                    <div class='message-footer'>Изпратено от <strong>{$message->getAuthorName()}</strong> на {$date} в {$time} часа.</div>
                </div>
            ";
            $messageLink = $baseUrl . $message->getKey();
            $messageList .= "<a href='{$messageLink}' class='decorated'>{$messageBox}</a>";
        }
        return $messageList;
    }

    private function getMessageContent(Message $message): string {
        $date = explode(" ", $message->getSent())[0];
        $time = explode(" ", $message->getSent())[1];
        return "
                    <div class='message-title'>{$message->getTitle()}</div>
                    <div class='message-content'>{$message->getContent()}</div>
                    <div class='message-footer'>Изпратено от <strong>{$message->getAuthorName()}</strong> на {$date} в {$time} часа.</div>
        ";
    }

    public function getContent(): string {
        $content = inBox("
            <h1>Съобщения</h1>
        ");

        $messages = Message::getUserMessages($this->user);
        $notifications = Brain::getNotifications($this->user->getId());
        $seen = getIntArray($notifications, "seen");
        $messages = array_reverse($messages);
        $content .= $this->getMessageList($messages, $seen);
        if (isset($_GET["messageKey"])) {
            $message = Message::getByKey($_GET["messageKey"]);
            if ($message == null) {
                redirect("/messages", "ERROR", "Не съществува съобщение с този идентификатор!");
            } else {
                $redirect = "/messages";
                $content .= "
                    <script>
                        showActionForm(`{$this->getMessageContent($message)}`, `{$redirect}`);
                    </script>
                ";
                if (!in_array($message->getId(), $seen)) {
                    array_push($seen, $message->getId());
                    $notifications["seen"] = implode(",", $seen);
                    Brain::updateNotifications($notifications);
                }
            }
        }
        return $content;
    }
}

?>
