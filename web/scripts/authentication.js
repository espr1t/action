var styleValid = 'color: #53A93F;';
var classValid = 'fa fa-check-square';
var styleInvalid = 'color: #D84A38;';
var classInvalid = 'fa fa-minus-square';

var usernameRe = /^\w[\w.]{1,15}$/;
var nameRe = /^([A-Za-zА-Яа-я]|-){1,32}$/;
var placeRe = /^[A-Za-zА-Яа-я ]{1,32}$/;
var dateRe = /^\d\d\d\d-\d\d-\d\d$/;
var passwordRe = /^.{1,32}$/;
var emailRe = /^[A-Za-z0-9_.+*=$^-]+@[a-zA-Z0-9-]+\.[a-zA-Z0-9-.]+$/;


function saltHashPassword(password) {
    return md5(password + 'frontendsalt');
}

function saltHashLoginPassword() {
    document.forms['login']['password'].value = saltHashPassword(document.forms['login']['password'].value);
    return true;
}

function saltHashRegisterPasswords() {
    document.forms['register']['password1'].value = saltHashPassword(document.forms['register']['password1'].value);
    document.forms['register']['password2'].value = saltHashPassword(document.forms['register']['password2'].value);
    return true;
}

function setState(validationIcon, valid) {
    if (valid) {
        validationIcon.style = styleValid;
        validationIcon.className = classValid;
        return true;
    } else {
        validationIcon.style = styleInvalid;
        validationIcon.className = classInvalid;
        return false;
    }
}

function validateUsername() {
    // TODO: Check against already registered users.
    var username = document.forms['register']['username'].value;
    var validationIcon = document.getElementById('validationIconUsername');
    return setState(validationIcon, usernameRe.test(username));
 }

function validateName(name) {
    var name = document.forms['register']['name'].value;
    var validationIcon = document.getElementById('validationIconName');
    return setState(validationIcon, nameRe.test(name));
 }

function validateSurname() {
    var surname = document.forms['register']['surname'].value;
    var validationIcon = document.getElementById('validationIconSurname');
    return setState(validationIcon, nameRe.test(surname));
}

function validatePassword1() {
    var password1 = document.forms['register']['password1'].value;
    var validationIcon = document.getElementById('validationIconPassword1');
    return setState(validationIcon, passwordRe.test(password1));
}

function validatePassword2() {
    var password1 = document.forms['register']['password1'].value;
    var password2 = document.forms['register']['password2'].value;
    var validationIcon = document.getElementById('validationIconPassword2');
    return setState(validationIcon, password1 == password2);
}

function validateEmail() {
    var email = document.forms['register']['email'].value;
    var validationIcon = document.getElementById('validationIconEmail');
    return setState(validationIcon, email == '' || emailRe.test(email));
}

function validateBirthdate() {
    var birthdate = document.forms['register']['birthdate'].value;
    var validationIcon = document.getElementById('validationIconBirthdate');
    var valid = true;
    if (birthdate && birthdate != '') {
        valid = dateRe.test(birthdate);
        if (valid) {
            year = parseInt(birthdate.substring(0, 4));
            month = parseInt(birthdate.substring(5, 7));
            day = parseInt(birthdate.substring(8, 10));
            // Rough date check.
            if (year < 1900 || year > 2100 || month < 1 || month > 12 || day < 1 || day > 31) {
                valid = false;
            } else {
                // Precise date check.
                var monthDays = [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
                if (year % 4 == 0 && (year % 100 != 0 || year % 400 == 0)) {
                    monthDays[1]++;
                }
                if (day > monthDays[month - 1]) {
                    valid = false;
                }
            }
        }
    }
    return setState(validationIcon, valid);
}

function validateTown() {
    var town = document.forms['register']['town'].value;
    var validationIcon = document.getElementById('validationIconTown');
    return setState(validationIcon, town == '' || placeRe.test(town));
}

function validateCountry() {
    var country = document.forms['register']['country'].value;
    var validationIcon = document.getElementById('validationIconCountry');
    return setState(validationIcon, country == '' || placeRe.test(country));
}

function validateCaptcha() {
    var captcha = document.forms['register']['captcha'].value;
    var expected = document.forms['register']['expected'].value;
    var validationIcon = document.getElementById('validationIconCaptcha');
    return setState(validationIcon, md5(captcha) == expected);
}

function validateRegistration() {
    return validateUsername() &&
           validateName() &&
           validateSurname() &&
           validatePassword1() &&
           validatePassword2() &&
           validateEmail() &&
           validateBirthdate() &&
           validateTown() &&
           validateCountry() &&
           validateCaptcha();
}

