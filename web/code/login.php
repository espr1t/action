<?php
require_once('page.php');

class LoginPage extends Page {
    public function getTitle() {
        return 'Do(n)e :: Login';
    }
    
    public function getContent() {
        $content = '
            <div class="box centered login">
                <form class="login" name="login" action="index_submit" method="post" accept-charset="utf-8">
                    Username: <input name="username" type="text" placeholder="Username" required><br>
                    Password: <input name="password" type="password" placeholder="Password" required><br>
                    <input name="submit" type="submit" value="Authenticate"> 
                </form>
            </div>' . newLine();
        return $content;
    }
    
}

?>