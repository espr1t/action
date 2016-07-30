<?php
require_once('common.php');
require_once('page.php');

class LoginPage extends Page {
    public function getTitle() {
        return 'O(N)::Login';
    }
    
    public function getExtraScripts() {
        return array('/scripts/authentication.js', '/scripts/md5.min.js');
    }

    public function init() {
        // Authorized user, update login key and redirect to home page
        $user = $this->authorizedUser();
        if ($user != null) {
            // Set session (use until browser close)
            $_SESSION['username'] = $user->getUsername();

            // Set cookie (avoid logging in again until cookie expires)
            $loginKey = str_shuffle(md5(microtime()));
            $expireTime = time() + 365 * 86400; // 365 days
            setcookie($GLOBALS['COOKIE_NAME'], $loginKey, $expireTime);

            header('Location: /home');
            exit();
        }
    }

    private function authorizedUser() {
        if (!isset($_POST['username'])) {
            return null;
        }
        if (!isset($_POST['password'])) {
            return null;
        }

        $user = User::getUser($_POST['username']);
        if ($user == null) {
            return null;
        }
        $salthashed = saltHashPassword($_POST['password']);
        if ($user->getPassword() != $salthashed) {
            // TODO: Error message "Invalid password!"
            return null;
        }
        return $user;
    }

    public function getContent() {
        // Not authorised user, show login form
        $content = '
                <div class="authenticate centered">
                    <div class="box login">
                        <h2>Authenticate</h2>
                        <div class="separator"></div>
                        <form class="login" name="login" action="login" onsubmit="return saltHashLoginPassword()" method="post" accept-charset="utf-8">
                            <i class="fa fa-user fa-fw"></i><input class="text" name="username" type="text" placeholder="Username" required><br>
                            <i class="fa fa-key fa-fw"></i><input class="text" name="password" type="password" placeholder="Password" required><br>
                            <input class="submit" name="submit" type="submit" value="Login">
                        </form>
                    </div>
                    <div class="register-link right smaller"><a href="register">Create Account</a></div>
                </div>
        ';
        return $content;
    }
    
}

?>