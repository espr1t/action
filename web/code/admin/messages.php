<?php
require_once(__DIR__ . '/../common.php');
require_once(__DIR__ . '/../page.php');
require_once(__DIR__ . '/../messages.php');
require_once(__DIR__ . '/../entities/message.php');

class AdminMessagesPage extends Page {
    public function getTitle() {
        return 'O(N)::Messages';
    }

    public function getExtraScripts() {
        return array('/scripts/admin.js', '/scripts/searchbox.js');
    }

    private function getEditMessageForm($message) {
        // Header and Footer
        $headerText = $message->id == -1 ? 'Изпращане на съобщение' : 'Промяна на съобщение';
        $buttonText = $message->id == -1 ? 'Изпрати' : 'Запази';

        $content = '
            <h2>' . $headerText . '</h2>
            <div style="height: 0.5rem;"></div>
            <div style="display: none;">
                <div id="messageId">' . $message->id . '</div>
                <div id="messageKey">' . $message->key . '</div>
                <div id="messageAuthorId">' . $message->authorId . '</div>
            </div>
            <div class="message-form-section">
                <div class="message-form-tag">От:</div>
                <div class="message-form-author" id="messageAuthorName">' . $message->authorName . '</div>
            </div>
            <div class="message-form-section">
                <div class="message-form-tag">До:</div>
                <div class="message-form-recipients" id="messageRecipients"></div>
                <div class="message-form-recipients-add" onclick="showSearchBox([\'users\'], addMessageRecipient, true);">
                    <i class="far fa-plus"></i>
                </div>
            </div>
            <div>
                <input type="text" class="message-form-title" id="messageTitle" value="' . $message->title . '">
            </div>
            <div>
                <input type="text" class="message-form-sent" id="messageSent" value="' . $message->sent . ' UTC" readonly>
            </div>
            <textarea name="content" class="message-form-content" id="messageContent" title="Content">' . $message->content . '</textarea>
            <div class="input-wrapper">
                <input type="submit" value="' . $buttonText . '" onclick="submitEditMessageForm();" class="button button-color-red">
            </div>
        ';
        return $content;
    }

    public function getContent() {
        $content = inBox('
            <h1>Админ::Съобщения</h1>

            <div class="centered" style="margin-top: 1rem;">
                <input type="submit" value="Ново съобщение" onclick="redirect(\'messages/new\');" class="button button-color-blue button-large">
            </div>
        ');

        $messages = Brain::getAllMessages();
        $content .= MessagesPage::getMessageList($messages, null);

        // Specific message is open
        if (isset($_GET['messageKey'])) {
            if ($_GET['messageKey'] == 'new') {
                $message = new Message();
            } else {
                $message = Message::get($_GET['messageKey']);
            }
            if ($message == null) {
                redirect('/admin/messages', 'ERROR', 'Не съществува съобщение с този идентификатор!');
            } else {
                $addRecipients = '';
                for ($i = 0; $i < count($message->userIds); $i++) {
                    $addRecipients .= '
                        addMessageRecipient({"id": ' . $message->userIds[$i] . ', "username": "' . $message->userNames[$i] . '"});';
                }
                $redirect = '/admin/messages';
                $content .= '
                    <script>
                        showEditMessageForm(`' . $this->getEditMessageForm($message) . '`, `' . $redirect . '`); ' . $addRecipients . '
                    </script>
                ';
            }
        }

        return $content;
    }
}

?>