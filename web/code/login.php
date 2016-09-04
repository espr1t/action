<?php
require_once('logic/user.php');
require_once('logic/config.php');
require_once('common.php');
require_once('page.php');

class LoginPage extends Page {
    public function getTitle() {
        return 'O(N)::Login';
    }
    
    public function getExtraScripts() {
        return array('/scripts/authentication.js', '/scripts/md5.min.js');
    }

    public function onLoad() {
        return 'document.forms[\'login\'][\'username\'].focus();';
    }

    public function getContent() {
        $error = '';

        // Check if user just entered valid credentials
        if (isset($_POST['username']) && isset($_POST['password'])) {
            $user = User::get($_POST['username']);
            if ($user == null) {
                $error = 'Не съществува акаунт с това потребителско име!';
            } else {
                $salthashed = saltHashPassword($_POST['password']);
                if ($user->password != $salthashed) {
                    $error = 'Въведената парола е невалидна!';
                }
            }

            // Authorized user, update login key and redirect to home page
            if ($error == '') {
                // Set session (use until browser close)
                $_SESSION['userId'] = $user->id;

                // Set cookie (avoid logging in again until cookie expires)
                $loginKey = str_shuffle(md5(microtime()));
                $expireTime = time() + 365 * 86400; // 365 days
                setcookie($GLOBALS['COOKIE_NAME'], $loginKey, $expireTime);

                header('Location: /home?action=success');
                exit();
            }
        }

        // Not authorised user, show login form (and possibly error message)
        return $this->getLoginForm() . ($error == '' ? '' : showMessage('ERROR', $error));
    }

    private function getLoginForm() {
        return '
                <div class="authenticate centered">
                    <div class="box login">
                        <h2>Authenticate</h2>
                        <form class="login" name="login" action="login" onsubmit="return saltHashLoginPassword()" method="post" accept-charset="utf-8">
                            <i class="fa fa-user fa-fw"></i><input class="text" name="username" type="text" placeholder="Username" required><br>
                            <i class="fa fa-key fa-fw"></i><input class="text" name="password" type="password" placeholder="Password" required><br>
                            <input type="submit" class="button button-color-blue" value="Login">
                        </form>
                    </div>
                    <div class="register-link right smaller"><a href="register">Create Account</a></div>
                </div>
        ';
    }

}

?>