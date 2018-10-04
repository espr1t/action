<?php
require_once('entities/user.php');
require_once('config.php');
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
        // User is already logged in
        if ($GLOBALS['user']->id != -1) {
            header('Location: /home');
            exit();
        }

        $error = '';

        // Check if user just entered valid credentials
        if (isset($_POST['username']) && isset($_POST['password'])) {
            $saltedPassword = saltHashPassword($_POST['password']);

            $user = User::get($_POST['username']);
            if ($user == null) {
                $error = 'Не съществува акаунт с това потребителско име!';
            } else {
                $brain = new Brain();
                $creds = $brain->getCreds($user->id);
                if ($creds['password'] != $saltedPassword) {
                    $error = 'Въведената парола е невалидна!';
                }
            }

            // Authorized user, update login key and redirect to home page
            if ($error == '') {
                // Set session (use until browser close)
                $_SESSION['userId'] = $user->id;

                // Set cookie (avoid logging in again until cookie expires)
                if ($creds['loginKey'] == '') {
                    $creds['loginKey'] = str_shuffle(md5(microtime()));
                    $brain = new Brain();
                    $brain->updateCreds($creds);
                }
                # Sign the login key with the user's IP so it cannot be used on another computer even if stolen
                # Note that this wouldn't work for two computers on the same subnet (behind a router)
                $signedLoginKey = $creds['loginKey'] . ':' . hash_hmac('md5', $creds['loginKey'], $_SERVER['REMOTE_ADDR']);
                $expireTime = time() + 365 * 86400; // 365 days
                setcookie($GLOBALS['COOKIE_NAME'], $signedLoginKey, $expireTime);

                // Redirect to home page with a success message
                redirect('/home', 'INFO', 'Влязохте успешно в системата.');
            }
        }

        // Not authorised user, show login form (and possibly error message)
        return $this->getLoginForm() . ($error == '' ? '' : showMessage('ERROR', $error));
    }

    private function getLoginForm() {
        return '
                <div class="authenticate centered">
                    <div class="box login">
                        <h2>Вход</h2>
                        <form class="login" name="login" action="login" onsubmit="return saltHashOnePassword(\'login\')" method="post" accept-charset="utf-8">
                            <div style="display: relative; margin-left: -1rem;">
                                <i class="fa fa-user fa-fw"></i><input class="text" name="username" type="text" placeholder="Username" required><br>
                                <i class="fa fa-key fa-fw"></i><input class="text" name="password" type="password" placeholder="Password" minlength=1 maxlength=32 required><br>
                            </div>
                            <input type="submit" class="button button-color-blue" value="Вход">
                            <div class="center" style="font-size: 0.75rem; margin-top: 0.15rem;"><a href="register">Регистрация</a> | <a href="reset">Възстановяване</a></div>
                         </form>
                    </div>
                </div>
        ';
    }

}

?>