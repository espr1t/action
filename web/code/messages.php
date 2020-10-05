<?php
require_once('db/brain.php');
require_once('entities/message.php');
require_once('common.php');
require_once('page.php');

class MessagesPage extends Page {
    public function getTitle() {
        return 'O(N)::Messages';
    }

    public static function getMessageList($messages, $seen=array()) {
        $baseUrl = explode('/messages', getCurrentUrl())[0] . '/messages/';
        $messageList = '';
        for ($i = 0; $i < count($messages); $i++) {
            $message = $messages[$i];
            $preview = $message['content'];
            if (strlen($preview) > 250) {
                $preview = substr($preview, 0, 247) . '...';
            }
            $date = explode(' ', $message['sent'])[0];
            $time = explode(' ', $message['sent'])[1];
            $seenIcon = ($seen == null || in_array($message['id'], $seen)) ? '' :
                            '<span style="color: #D84A38;"><i class="fas fa-bell"></i></span>';
            $messageBox = '
                <div class="box boxlink">
                    <div class="message-seen">' . $seenIcon . '</div>
                    <div class="message-title">' . $message['title'] . '</div>
                    <div class="message-content">' . $preview . '</div>
                    <div class="message-footer">Получено на ' . $date . ' в ' . $time . ' часа от <strong>' . $message['authorName'] . '</strong></div>
                </div>
            ';
            $messageList .= '<a href="' . $baseUrl . $message['key'] . '" class="decorated">' . $messageBox . '</a>';
        }
        return $messageList;
    }

    private function getMessageContent($message) {
        $date = explode(' ', $message->sent)[0];
        $time = explode(' ', $message->sent)[1];
        $content = '
            <div class="message-title">' . $message->title . '</div>
            <div class="message-content">' . $message->content . '</div>
            <div class="message-footer">Получено на ' . $date . ' в ' . $time . ' часа от ' . getUserLink($message->authorName) . '</div>
        ';
        return $content;
    }

    public function getContent() {
        $content = inBox('
            <h1>Съобщения</h1>
        ');

        $messages = array();
        foreach ($this->user->getMessages() as $messageId) {
            $message = Brain::getMessage($messageId);
            array_push($messages, $message);
        }
        $notifications = Brain::getNotifications($this->user->id);
        $seen = parseIntArray($notifications['seen']);
        $messages = array_reverse($messages);
        $content .= $this->getMessageList($messages, $seen);
        if (isset($_GET['messageKey'])) {
            $message = Message::get($_GET['messageKey']);
            if ($message == null) {
                redirect('/messages', 'ERROR', 'Не съществува съобщение с този идентификатор!');
            } else {
                $redirect = '/messages';
                $content .= '
                    <script>
                        showActionForm(`' . $this->getMessageContent($message) . '`, `' . $redirect . '`);
                    </script>
                ';
                if (!in_array($message->id, $seen)) {
                    array_push($seen, $message->id);
                    $notifications['seen'] = implode(',', $seen);
                    Brain::updateNotifications($notifications);
                }
            }
        }
        return $content;
    }
}

?>
