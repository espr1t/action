<?php
require_once('page.php');

class LogoutPage extends Page {
    public function getTitle() {
        return 'Do(n)e :: Logout';
    }
    
    public function getContent() {
        $this->user->logOut();
        $content = '
            <div class="box centered login">
                You have successfully logged out!
            </div>' . newLine();
        return $content;
    }
    
}

?>