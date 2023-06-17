<?php
require_once("entities/user.php");
require_once("config.php");
require_once("common.php");
require_once("page.php");

class LoginPage extends Page {
    public function getTitle(): string {
        return "O(N)::Login";
    }

    public function getExtraScripts(): array {
        return array("/scripts/authentication.js", "/scripts/md5.min.js");
    }

    public function onLoad(): string {
        return "document.forms['login']['username'].focus();";
    }

    public function getContent(): string {
        // User is already logged in
        if ($this->user->getId() != -1) {
            redirect("/home");
        }

        $error = "";

        // Check if user just entered valid credentials
        if (isset($_POST["username"]) && isset($_POST["password"])) {
            $saltedPassword = saltHashPassword($_POST["password"]);

            $user = User::getByUsername($_POST["username"]);
            if ($user == null) {
                $error = "Не съществува акаунт с това потребителско име!";
            } else {
                $creds = Brain::getCreds($user->getId());
                if ($creds["password"] != $saltedPassword) {
                    $error = "Въведената парола е невалидна!";
                }
            }

            // Authorized user, update login key and redirect to home page
            if ($error == "") {
                // Set session (use until browser close)
                $GLOBALS["user"] = $user;
                $_SESSION["userId"] = $user->getId();

                // Set cookie (avoid logging in again until cookie expires)
                if ($creds["loginKey"] == "") {
                    $creds["loginKey"] = str_shuffle(md5(microtime()));
                    Brain::updateCreds($creds);
                }
                # Sign the login key with the user's IP so it cannot be used on another computer even if stolen
                # Note that this wouldn't work for two computers on the same subnet (behind a router)
                $signedLoginKey = $creds["loginKey"] . ":" . hash_hmac("md5", $creds["loginKey"], getUserIP());
                $expireTime = time() + 365 * 86400; // 365 days
                setcookie($GLOBALS["COOKIE_NAME"], $signedLoginKey, $expireTime);

                // Record the login to the logs
                $logMessage = "User {$user->getUsername()} has logged in.";
                write_log($GLOBALS["LOG_LOGINS"], $logMessage);
                $user->updateLoginCount();

                // Redirect to home page with a success message
                redirect("/home", "INFO", "Влязохте успешно в системата.");
            }
        }

        // Not authorised user, show login form (and possibly error message)
        return $this->getLoginForm() . ($error == "" ? "" : showNotification("ERROR", $error));
    }

    private function getLoginForm(): string {
        return "
                <div class='authenticate centered'>
                    <div class='box login'>
                        <h2>Вход</h2>
                        <form class='login' name='login' action='login' onsubmit='return saltHashOnePassword(`login`)' method='post' accept-charset='utf-8'>
                            <div style='position: relative; margin-left: -1rem;'>
                                <i class='fa fa-user fa-fw'></i><input class='text' name='username' type='text' placeholder='Username' required><br>
                                <i class='fa fa-key fa-fw'></i><input class='text' name='password' type='password' placeholder='Password' minlength=1 maxlength=32 required><br>
                            </div>
                            <input type='submit' class='button button-color-blue' value='Вход'>
                            <div class='center' style='font-size: 0.75rem; margin-top: 0.15rem;'><a href='register'>Регистрация</a> | <a href='reset'>Възстановяване</a></div>
                         </form>
                    </div>
                </div>
        ";
    }

}

?>