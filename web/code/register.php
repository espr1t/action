<?php
require_once('config.php');
require_once('common.php');
require_once('page.php');

class RegisterPage extends Page {
    public function getTitle() {
        return 'O(N)::Register';
    }

    public function getExtraScripts() {
        return array(
            '/scripts/authentication.js',
            '/scripts/md5.min.js',
            'https://www.google.com/recaptcha/api.js'
        );
    }

    public function onLoad() {
        return 'document.forms[\'register\'][\'name\'].focus();';
    }

    public function getContent() {
        if (isset($_POST['username'])) {
            $error = $this->registerUser();
            return $this->getRegisterForm() . showNotification('ERROR', $error);
        }
        return $this->getRegisterForm();
    }

    private function getRegisterForm() {
        $index = rand(0, count($GLOBALS['CAPTCHA_CITIES']) - 1);
        $expected = md5($GLOBALS['CAPTCHA_CITIES'][$index]);
        $captcha = str_shuffle($GLOBALS['CAPTCHA_CITIES'][$index]);
        while ($captcha === $GLOBALS['CAPTCHA_CITIES'][$index]) {
            $captcha = str_shuffle($GLOBALS['CAPTCHA_CITIES'][$index]);
        }

        $recaptchaKey = isProduction() ? $GLOBALS['RE_CAPTCHA_PROD_SITE_KEY'] : $GLOBALS['RE_CAPTCHA_TEST_SITE_KEY'];

        $content = '
            <div class="register centered">
                <div class="box">
                    <h2>Регистрация на Потребител</h2>
                    <form class="register" name="register" action="register" method="post" accept-charset="utf-8"
                            onsubmit="return validateRegistration(\'register\') && saltHashTwoPasswords(\'register\')">
                        <table class="register">
                            <tr>
                                <td class="left"><b>Име:</b></td>
                                <td class="right">
                                    <input type="text" name="name" placeholder="First Name" class="text" minlength=1 maxlength=32 required
                                        title="Names must be between 1 and 32 characters long and can contain only Cyrillic or Lattin letters and dashes."
                                        onkeyup="validateName(\'register\')">
                                    <i class="fa fa-minus-square" style="color: #D84A38;" id="validationIconName"></i>
                                </td>
                            </tr>
                            <tr>
                                <td class="left"><b>Фамилия:</b></td>
                                <td class="right">
                                    <input type="text" name="surname" placeholder="Last Name" class="text" minlength=1 maxlength=32 required
                                        title="Names must be between 1 and 32 characters long and can contain only Cyrillic or Lattin letters and dashes."
                                        onkeyup="validateSurname(\'register\')">
                                    <i class="fa fa-minus-square" style="color: #D84A38;" id="validationIconSurname"></i>
                                </td>
                            </tr>
                            <tr>
                                <td class="left"><b>Потребителско Име:</b></td>
                                <td class="right">
                                    <input type="text" name="username" placeholder="Username" class="text" minlength=2 maxlength=16 required
                                        title="Username must be between 2 and 16 characters long and can contain only Lattin letters, digits, dots, and underscores. It cannot start with a dot."
                                        onkeyup="validateUsername(\'register\')">
                                    <i class="fa fa-minus-square" style="color: #D84A38;" id="validationIconUsername"></i>
                                </td>
                            </tr>
                            <tr>
                                <td class="left"><b>Парола:</b></td>
                                <td class="right">
                                    <input type="password" name="password1" placeholder="Password" class="text" minlength=1 maxlength=33 required
                                        title="Password must be between 1 and 32 characters long."
                                        onkeyup="validatePassword1(\'register\')">
                                    <i class="fa fa-minus-square" style="color: #D84A38;" id="validationIconPassword1"></i>
                                </td>
                            </tr>
                            <tr>
                                <td class="left"><b>Повторно Парола:</b></td>
                                <td class="right">
                                    <input type="password" name="password2" placeholder="Repeat Password" class="text" minlength=1 maxlength=32 required
                                        title="Password must be between 1 and 32 characters long."
                                        onkeyup="validatePassword2(\'register\')">
                                    <i class="fa fa-minus-square" style="color: #D84A38;" id="validationIconPassword2"></i>
                                </td>
                            </tr>
                            <tr><td>&nbsp;</td></tr>
                            <tr>
                                <td class="left">Поща:</td>
                                <td class="right">
                                    <input type="email" name="email" placeholder="example@mail.com" class="text" minlength=0 maxlength=32
                                        title="E-mail must look like a valid e-mail address."
                                        onkeyup="validateEmail(\'register\')">
                                    <i class="fa fa-check-square" style="color: #53A93F;" id="validationIconEmail"></i>
                                </td>
                            </tr>
                            <tr>
                                <td class="left">Дата на Раждане:</td>
                                <td class="right">
                                    <input type="text" name="birthdate" placeholder="YYYY-MM-DD" class="text" minlength=0 maxlength=32
                                        title="The date of birth should follow the ISO format: YYYY-MM-DD."
                                        onkeyup="validateBirthdate(\'register\')">
                                    <i class="fa fa-check-square" style="color: #53A93F;" id="validationIconBirthdate"></i>
                                </td>
                            </tr>
                            <tr>
                                <td class="left">Град:</td>
                                <td class="right">
                                    <input type="text" name="town" placeholder="Town" class="text" minlength=0 maxlength=32
                                        title="Town must be between 1 and 32 characters long and can contain only Cyrillic or Latin letters and spaces."
                                        onkeyup="validateTown(\'register\')">
                                    <i class="fa fa-check-square" style="color: #53A93F;" id="validationIconTown"></i>
                                </td>
                            </tr>
                            <tr>
                                <td class="left">Държава:</td>
                                <td class="right">
                                    <input type="text" name="country" placeholder="Country" class="text" minlength=0 maxlength=32
                                        title="Country must be between 1 and 32 characters long and can contain only Cyrillic or Latin letters and spaces."
                                        onkeyup="validateCountry(\'register\')">
                                    <i class="fa fa-check-square" style="color: #53A93F;" id="validationIconCountry"></i>
                                </td>
                            </tr>
                            <tr>
                                <td class="left">Пол:</td>
                                <td class="right">
                                    <input class="radio" type="radio" name="gender" value="male"> Мъж<br>
                                    <input class="radio" type="radio" name="gender" value="female"> Жена
                                </td>
                            </tr>
                            <tr><td>&nbsp;</td></tr>
                            <tr>
                                <td class="left" title="Например за \'cveLho\' трябва да въведете \'Lovech\'."><b>Разшифровай града "' . $captcha . '":</b></td>
                                <td class="right">
                                    <input type="text" name="captcha" class="text" required
                                         title="For example the correct unscrambling of \'cveLho\' is \'Lovech\'."
                                         onkeyup="validateCaptcha(\'register\')">
                                    <i class="fa fa-minus-square" style="color: #D84A38;" id="validationIconCaptcha"></i>
                                    <input type="hidden" name="expected" value="' . $expected . '">
                                </td>
                            </tr>
                        </table>
                        <br>
                        <div style="text-align: center;">
                            <div class="g-recaptcha" style="display: inline-block" data-sitekey="' . $recaptchaKey . '"></div>
                        </div>
                        <input name="submit" type="submit" class="button button-color-blue" value="Регистрация">
                    </form>
                </div>
            </div>
        ';
        return $content;
    }

    private function registerUser() {
        // Check re-CAPTCHA
        if (!validateReCaptcha()) {
            return 'Не преминахте re-CAPTCHA валидацията!';
        }

        // Check captcha question
        if (!isset($_POST['captcha']) || !isset($_POST['expected'])) {
            return 'Въведената captcha е невалидна!';
        }
        if (md5($_POST['captcha']) != $_POST['expected']) {
            return 'Въведената captcha е невалидна!';
        }
        if (!in_array($_POST['captcha'], $GLOBALS['CAPTCHA_CITIES'])) {
            return 'Въведената captcha е невалидна!';
        }
        unset($_POST['captcha']);
        unset($_POST['expected']);

        // Check username
        if (!isset($_POST['username']) || !validateUsername($_POST['username'])) {
            return 'Въведеното потребителско име е празно или невалидно!';
        }
        $username = $_POST['username']; unset($_POST['username']);
        if (User::get($username) != null) {
            return 'Въведеното потребителското име вече е заето!';
        }

        // Check first and last names
        if (!isset($_POST['name']) || !validateName($_POST['name'])) {
            return 'Въведеното име не изпълнява изискванията на сайта!';
        }
        $name = $_POST['name']; unset($_POST['name']);

        if (!isset($_POST['surname']) || !validateName($_POST['surname'])) {
            return 'Въведената фамилия не изпълнява изискванията на сайта!';
        }
        $surname = $_POST['surname']; unset($_POST['surname']);

        // Check password
        if (!isset($_POST['password1']) || !isset($_POST['password2'])) {
            return 'Въведената парола е празна!';
        }
        $password1 = $_POST['password1']; unset($_POST['password1']);
        $password2 = $_POST['password2']; unset($_POST['password2']);

        if (strcmp($password1, $password2) != 0) {
            return 'Въведените пароли не съвпадат!';
        }
        $password = saltHashPassword($password1);

        // Get optional information
        $email = '';
        if (isset($_POST['email'])) {
            if (validateEmail($_POST['email'])) {
                $email = $_POST['email'];
            } else {
                return 'Въведеният e-mail адрес не изпълнява изискванията на сайта!';
            }
            unset($_POST['email']);
        }

        $birthdate = '';
        if (isset($_POST['birthdate'])) {
            if (validateDate($_POST['birthdate'])) {
                $birthdate = $_POST['birthdate'];
            } else {
                return 'Въведената дата на раждане не изпълнява изискванията на сайта!';
            }
            unset($_POST['birthdate']);
        }

        $town = '';
        if (isset($_POST['town'])) {
            if (validatePlace($_POST['town'])) {
                $town = $_POST['town'];
            } else {
                return 'Въведеният град не изпълнява изискванията на сайта!';
            }
            unset($_POST['town']);
        }

        $country = '';
        if (isset($_POST['country'])) {
            if (validatePlace($_POST['country'])) {
                $country = $_POST['country'];
            } else {
                return 'Въведената държава не изпълнява изискванията на сайта!';
            }
            unset($_POST['country']);
        }

        $gender = '';
        if (isset($_POST['gender'])) {
            if (validateGender($_POST['gender'])) {
                $gender = $_POST['gender'];
            } else {
                return 'Въведеният пол не е валиден!';
            }
            unset($_POST['gender']);
        }

        // Actually create the user
        $user = User::createUser($username, $name, $surname, $password, $email, $birthdate, $town, $country, $gender);
        if ($user != null) {
            $_SESSION['userId'] = $user->id;

            // Set cookie (avoid logging in again until cookie expires)
            $loginKey = str_shuffle(md5(microtime()));
            $expireTime = time() + 365 * 86400; // 365 days
            setcookie($GLOBALS['COOKIE_NAME'], $loginKey, $expireTime);

            // Redirect to home page with a success message
            redirect('/home', 'INFO', 'Регистрирахте се успешно в системата.');
        } else {
            return 'Грешка при записването на новия потребител.';
        }
    }

}

?>