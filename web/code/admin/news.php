<?php
require_once('../common.php');
require_once('../page.php');

class AdminNewsPage extends Page {
    public function getTitle() {
        return 'O(N)::Admin';
    }
    
    public function getContent() {
        $newsFormContent = '
            <h2>Публикуване на новина</h2>
            <div class="left" style="margin-bottom: 2px;">
                <input type="text" name="title" class="news-form-title" id="newsTitle" value="Заглавие">
            </div>
            <div class="right" style="margin-bottom: 4px;">
                <input type="text" name="date" class="news-form-date" id="newsDate" value="' . date('Y-m-d') . '">
            </div>
            <textarea name="content" class="news-form-content" id="newsContent"></textarea>
            <div class="input-wrapper">
                <input type="submit" value="Публикувай" onclick="submitNewsForm();" class="button button-color-red">
            </div>
        ';

        return inBox('
            <h1>Admin::News</h1>

            <script>
                function showForm() {
                    showNewsForm(`' . $newsFormContent . '`);
                }
            </script>
            <div class="problem-submit">
                <input type="submit" value="Публикувай новина" onclick="showForm();" class="button button-color-blue button-large">
            </div>
        ');
    }
}

?>