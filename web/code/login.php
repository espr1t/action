<?php
require_once('page.php');

class LoginPage extends Page {
    public function getTitle() {
        return 'O(N)::Login';
    }
    
    public function getContent() {
        $content = '
            <div class="authenticate centered">
                <div class="box login">
                    <h2>Authenticate</h2>
                    <div class="separator"></div>
                    <form class="login" name="login" action="index_submit" method="post" accept-charset="utf-8">
                        <i class="fa fa-user fa-fw"></i><input class="text" name="username" type="text" placeholder="Username" required><br>
                        <i class="fa fa-key fa-fw"></i><input class="text" name="password" type="password" placeholder="Password" required><br>
                        <input class="submit" name="submit" type="submit" value="Login"> 
                    </form>
                </div>
                <div class="register-link right smaller"><a href="register">Create Account</a></div>
            </div>
            ' . newLine();
        return $content;
    }
    
}

?>