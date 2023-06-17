<?php
require_once("config.php");
require_once("common.php");
require_once("page.php");

class ResetPage extends Page {
    public function getTitle(): string {
        return "O(N)::Reset Password";
    }

    public function getExtraScripts(): array {
        return array(
            "/scripts/authentication.js",
            "/scripts/md5.min.js",
            "https://www.google.com/recaptcha/api.js"
        );
    }

    public function onLoad(): string {
        return "
            if (document.forms['reset']['password1']) {
                document.forms['reset']['password1'].focus();
            } else {
                document.forms['reset']['username'].focus();
            }
        ";
    }

    private function sendResetEmail(): string {
        // Check re-CAPTCHA
        if (!validateReCaptcha()) {
            return "Не преминахте re-CAPTCHA валидацията!";
        }

        // Check captcha question
        if (!isset($_POST["captcha"]) || !isset($_POST["expected"]) ||
            md5($_POST["captcha"]) != $_POST["expected"] ||
            !in_array($_POST["captcha"], $GLOBALS["CAPTCHA_CITIES"])) {
            return "Въведената captcha е невалидна!";
        }

        // Check username
        if (!isset($_POST["username"]) || !validateUsername($_POST["username"])) {
            return "Въведеното потребителско име е празно или невалидно!";
        }
        $user = User::getByUsername($_POST["username"]);
        if (!$user) {
            return "Не съществува потребител с потребителско име {$_POST['username']}!";
        }

        // The user has not set an e-mail.
        if ($user->getEmail() == "") {
            return "NO_EMAIL";
        }

        // The provided e-mail does not match the one of the user.
        if ($user->getEmail() != $_POST["email"]) {
            return "Потребителят е регистриран с друг e-mail.";
        }

        // There is currently another reset link active.
        $creds = Brain::getCreds($user->getId());
        $timeNow = date("Y-m-d H:i:s");
        if ($creds["resetTime"] != "0000-00-00 00:00:00") {
            $timeDiff = strtotime($timeNow) - strtotime($creds["resetTime"]);
            if ($timeDiff < $GLOBALS["RESET_PASSWORD_TIMEOUT"]) {
                return "На потребителя вече е изпратен e-mail за промяна на паролата.";
            }
        }

        // Generate a reset code
        $creds["resetTime"] = $timeNow;
        $creds["resetKey"] = str_shuffle(md5(microtime()));
        Brain::updateCreds($creds);

        // Send an e-mail to the user with instructions and activation link
        $resetLink = "https://action.informatika.bg/reset/{$creds['resetKey']}";

        $address = $user->getEmail();
        $subject = "Password reset for user {$user->getUsername()}";
        $content = "
            <html>
                <body>
                    <larger>Здравейте, <strong>{$user->getUsername()}</strong>!</larger>
                    <br><br>

                    Поискана е смяна на паролата за Вашия потребител на сайта
                    <a href=\"https://action.informatika.bg\" target=\"_blank\">action.informatika.bg</a>.
                    <br><br>

                    За да смените паролата си, моля отидете на следния адрес и въведете новата си парола:
                    <a href=\"{$resetLink}\" target=\"_blank\">{$resetLink}</a>
                    <br><br>

                    В случай, че не сте ползвали функцията \"Забравена парола\", Ви молим да игнорирате
                    този e-mail и се извиняваме за безпокойствието.
                    <br><br>

                    Поздрави,<br>
                    Екипът на informatika.bg
                </body>
            </html>
        ";

        if (!sendEmail($address, $subject, $content)) {
            return "Възникна проблем при изпращането на e-mail-а.";
        }
        return "";
    }

    private function resetPassword(): bool {
        $creds = Brain::getCredsByResetKey($_POST["key"]);
        if (!$creds) {
            return false;
        }
        if (strcmp($_POST["password1"], $_POST["password2"]) != 0) {
            return false;
        }

        $creds["password"] = saltHashPassword($_POST["password1"]);
        $creds["resetKey"] = "";
        $creds["resetTime"] = "0000-00-00 00:00:00";
        $creds["lastReset"] = date("Y-m-d H:i:s");
        if (Brain::updateCreds($creds)) {
            // Record the updated password in the logs
            $logMessage = "User {$creds['username']} has updated his/her password.";
            write_log($GLOBALS["LOG_PASS_RESETS"], $logMessage);
            return true;
        }
        return false;
    }

    private function emailSentPage(): string {
        return inBox("
            <div style='display: table-cell; vertical-align: middle; width: 5rem; height: 4rem;'>
                <i class='fas fa-envelope' style='color: #0099FF; font-size: 4rem;'></i>
            </div>
            <div style='display: table-cell; vertical-align: middle; height: 4rem;'>
                <div>
                    На e-mail-а на потребителя беше изпратен линк за смяна на паролата.<br>
                    Моля, следвайте указанията в него за да завършите процедурата.
                </div>
            </div>
        ");
    }

    private function noEmailPage(): string {
        return inBox("
            <div style='display: table-cell; vertical-align: middle; width: 5rem; height: 4rem;'>
                <i class='fas fa-exclamation-square' style='color: #D84A38; font-size: 4rem;'></i>
            </div>
            <div style='display: table-cell; vertical-align: middle; height: 4rem;'>
                <div>
                    Зададеният потребител не е въвел e-mail адрес по време на регистрацията,<br>
                    в следствие на което паролата не може да бъде променена автоматично.<br>
                    Моля, обърнете се към администратор.
                </div>
            </div>
        ");
    }

    private function getResetForm(string $username): string {
        return "
            <div class='reset centered'>
                <div class='box'>
                    <h2>Смяна на Парола</h2>
                    Моля въведете новата парола, която желаете.
                    <br><br>
                    <form class='reset' name='reset' action='/reset' method='post' accept-charset='utf-8'
                            onsubmit='return validatePasswordResetLast(`reset`) && saltHashTwoPasswords(`reset`)'>
                        <table class='register'>
                            <tr>
                                <td class='left'><b>Потребител:</b></td>
                                <td class='right'>
                                    <input type='text' name='placeholder' value='{$username}' class='text' required readonly disabled>
                                    <input type='hidden' name='username' value='{$username}' class='text' required readonly>
                                    <i class='fa fa-check-square' style='color: #53A93F;' id='validationIconUsername'></i>
                                </td>
                            </tr>

                            <tr>
                                <td class='left'><b>Парола:</b></td>
                                <td class='right'>
                                    <input type='password' name='password1' placeholder='Password' class='text' minlength=1 maxlength=33 required
                                        title='Password must be between 1 and 32 characters long.'
                                        onkeyup='validatePassword1(`reset`)'>
                                    <i class='fa fa-minus-square' style='color: #D84A38;' id='validationIconPassword1'></i>
                                </td>
                            </tr>

                            <tr>
                                <td class='left'><b>Повторно Парола:</b></td>
                                <td class='right'>
                                    <input type='password' name='password2' placeholder='Repeat Password' class='text' minlength=1 maxlength=32 required
                                        title='Password must be between 1 and 32 characters long.'
                                        onkeyup='validatePassword2(`reset`)'>
                                    <i class='fa fa-minus-square' style='color: #D84A38;' id='validationIconPassword2'></i>
                                </td>
                            </tr>

                            <input type='hidden' name='key' value='{$_GET['key']}'>
                        </table>
                        <input name='submit' type='submit' class='button button-color-blue' value='Изпрати'>
                    </form>
                </div>
            </div>
        ";
    }

    public function getContent(): string {
        // Nothing entered so far, go to the main page
        if (!isset($_POST["username"]) && !isset($_GET["key"])) {
            return $this->getSendEmailForm();
        }

        // Entered the username and CAPTCHAs and clicked "Send"
        if (isset($_POST["username"]) && !isset($_POST["password1"])) {
            $error = $this->sendResetEmail();
            if ($error == "NO_EMAIL") { // The user has not provided a valid e-mail address
                return $this->noEmailPage() . showNotification("ERROR", "Потребителят няма въведен e-mail адрес!");
            }
            if ($error != '') { // Some other error happened
                return $this->getSendEmailForm() . showNotification("ERROR", $error);
            }
            // Everything ran smoothly - show a page that the e-mail was sent to the user's e-mail address
            return $this->emailSentPage() . showNotification("INFO", "Действието беше извършено успешно!");
        }

        // Clicked on the link in the e-mail, make him or her enter the new passwords
        if (isset($_GET["key"])) {
            $creds = Brain::getCredsByResetKey($_GET["key"]);
            if (!$creds) {
                return $this->getSendEmailForm() . showNotification("ERROR", "Използваният ключ е невалиден.");
            }
            $timeDiff = strtotime(date("Y-m-d H:i:s")) - strtotime($creds["resetTime"]);
            if ($timeDiff > $GLOBALS["RESET_PASSWORD_TIMEOUT"]) {
                return $this->getSendEmailForm() . showNotification("ERROR", "Изпратеният ключ е изтекъл.");
            }
            return $this->getResetForm($creds["username"]);
        }

        // Entered the new passwords
        if (isset($_POST["username"]) && isset($_POST["password1"]) && isset($_POST["password2"]) && isset($_POST["key"])) {
            if ($this->resetPassword()) {
                // Redirect to home page with a success message
                redirect("/login", "INFO", "Променихте паролата си успешно!");
            } else {
                // Show the same form and an error message
                return $this->getSendEmailForm() . showNotification("ERROR", "Възникна проблем при промяната на паролата.");
            }
        }

        error_log("ERROR: Shouldn't reach this code (problem is in password reset logic).");
        return $this->getSendEmailForm() . showNotification("ERROR", "Това не трябва да се случва. Моля, свържете се с администратор.");
    }

    private function getSendEmailForm(): string {
        $index = rand(0, count($GLOBALS["CAPTCHA_CITIES"]) - 1);
        $token = $GLOBALS["CAPTCHA_CITIES"][$index];
        $expected = md5($token);
        $captcha = str_shuffle($token);
        while ($captcha === $token) {
            $captcha = str_shuffle($captcha);
        }

        $recaptchaKey = isProduction() ? $GLOBALS["RE_CAPTCHA_PROD_SITE_KEY"] : $GLOBALS["RE_CAPTCHA_TEST_SITE_KEY"];

        return "
            <div class='reset centered'>
                <div class='box'>
                    <h2>Смяна на Парола</h2>
                    На въведения по време на регистрация e-mail ще бъде изпратен линк<br>
                    за смяна на паролата. В случай, че не сте въвели e-mail, се свържете с администратор.
                    <br><br>
                    <form class='reset' name='reset' action='/reset' onsubmit='return validatePasswordReset(`reset`)' method='post' accept-charset='utf-8'>
                        <table class='register'>
                            <tr>
                                <td class='left'><b>Потребител:</b></td>
                                <td class='right'>
                                    <input type='text' name='username' placeholder='Username' class='text' minlength=2 maxlength=16 required
                                        title='Must be a valid username of a registered user.'
                                        onkeyup='checkUsername(`reset`); updateEmailSuggestion(`reset`);'>
                                    <i class='fa fa-minus-square' style='color: #D84A38;' id='validationIconUsername'></i>
                                </td>
                            </tr>

                            <tr>
                                <td class='left'><b>E-mail на потребителя:</b></td>
                                <td class='right'>
                                    <input type='email' name='email' placeholder='example@mail.com' class='text' minlength=1 maxlength=32
                                        title='E-mail must look like a valid e-mail address.'
                                        onkeyup='validateEmail(`reset`, false)' id='emailInputField'>
                                    <i class='fa fa-minus-square' style='color: #D84A38;' id='validationIconEmail'></i>
                                </td>
                            </tr>

                            <tr>
                                <td class='left' title='Например за \"cveLho\" трябва да въведете \"Lovech\".'><b>Разшифровайте града \"{$captcha}\":</b></td>
                                <td class='right'>
                                    <input type='text' name='captcha' class='text' required
                                         title='Например за \"cveLho\" трябва да въведете \"Lovech\".'
                                         onkeyup='validateCaptcha(`reset`)'>
                                    <i class='fa fa-minus-square' style='color: #D84A38;' id='validationIconCaptcha'></i>
                                    <input type='hidden' name='expected' value='{$expected}'>
                                </td>
                            </tr>
                        </table>
                        <br>
                        <div style='text-align: center;'>
                            <div class='g-recaptcha' style='display: inline-block' data-sitekey='{$recaptchaKey}'></div>
                        </div>
                        <input name='submit' type='submit' class='button button-color-blue' value='Изпрати'>
                    </form>
                </div>
            </div>
        ";
    }

}

?>