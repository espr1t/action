<?php
require_once('../common.php');
require_once('../page.php');

class AdminNewsPage extends Page {
    public function getTitle() {
        return 'O(N)::Admin';
    }
    
    public function getContent() {
        return inBox('
            <h1>Admin::News</h1>

            <div class="problem-submit">
                <input type="submit" value="Публикувай новина" onclick="showNewsForm();" class="button button-color-blue button-large">
            </div>
        ');
    }
}

?>