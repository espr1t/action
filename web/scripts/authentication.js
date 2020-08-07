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

function saltHashOnePassword(formName) {
    document.forms[formName]['password'].value = saltHashPassword(document.forms[formName]['password'].value);
    return true;
}

function saltHashTwoPasswords(formName) {
    document.forms[formName]['password1'].value = saltHashPassword(document.forms[formName]['password1'].value);
    document.forms[formName]['password2'].value = saltHashPassword(document.forms[formName]['password2'].value);
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

function validateUsername(formName) {
    var username = document.forms[formName]['username'].value;
    var validationIcon = document.getElementById('validationIconUsername');
    return setState(validationIcon, usernameRe.test(username));
 }

var users = null;
function userExists(username) {
    // Check if the username passes the validation criteria
    if (!usernameRe.test(username)) {
        return false;
    }

    // Check if the username actually exists among the registered users
    if (users != null) {
        for (user of users) {
            if (user['username'].toLowerCase() == username.toLowerCase())
                return true;
        }
    } else {
        console.warn('Array users is not loaded.');
    }
    return false;
}

function checkUsername(formName) {
    var username = document.forms[formName]['username'].value;
    var validationIcon = document.getElementById('validationIconUsername');

    var callback = function(response) {
        response = parseActionResponse(response);
        users = response['users'];
        setState(validationIcon, userExists(username));
        updateEmailSuggestion(formName);
    }
    if (users == null) {
        ajaxCall('/actions/data/users', {}, callback);
    } else {
        setState(validationIcon, userExists(username));
    }
    return true;
 }

function updateEmailSuggestion(formName) {
    var username = document.forms[formName]['username'].value;
    var emailInputEl = document.getElementById('emailInputField');
    emailInputEl.placeholder = 'example@mail.com';
    if (users != null) {
        for (user of users) {
            if (user['username'].toLowerCase() == username.toLowerCase()) {
                if (user['email'] != '') {
                    emailInputEl.placeholder = user['email'];
                }
            }
        }
    }
}

function validateName(formName) {
    var name = document.forms[formName]['name'].value;
    var validationIcon = document.getElementById('validationIconName');
    return setState(validationIcon, nameRe.test(name));
 }

function validateSurname(formName) {
    var surname = document.forms[formName]['surname'].value;
    var validationIcon = document.getElementById('validationIconSurname');
    return setState(validationIcon, nameRe.test(surname));
}

function validatePassword1(formName) {
    var password1 = document.forms[formName]['password1'].value;
    var validationIcon = document.getElementById('validationIconPassword1');
    return setState(validationIcon, passwordRe.test(password1));
}

function validatePassword2(formName) {
    var password1 = document.forms[formName]['password1'].value;
    var password2 = document.forms[formName]['password2'].value;
    var validationIcon = document.getElementById('validationIconPassword2');
    return setState(validationIcon, password1 == password2);
}

function validateEmail(formName, allowEmpty=true) {
    var email = document.forms[formName]['email'].value;
    var validationIcon = document.getElementById('validationIconEmail');
    return setState(validationIcon, emailRe.test(email) || (allowEmpty && email == ''));
}

function validateBirthdate(formName) {
    var birthdate = document.forms[formName]['birthdate'].value;
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

function validateTown(formName) {
    var town = document.forms[formName]['town'].value;
    var validationIcon = document.getElementById('validationIconTown');
    return setState(validationIcon, town == '' || placeRe.test(town));
}

function validateCountry(formName) {
    var country = document.forms[formName]['country'].value;
    var validationIcon = document.getElementById('validationIconCountry');
    return setState(validationIcon, country == '' || placeRe.test(country));
}

function validateCaptcha(formName) {
    var captcha = document.forms[formName]['captcha'].value;
    var expected = document.forms[formName]['expected'].value;
    var validationIcon = document.getElementById('validationIconCaptcha');
    return setState(validationIcon, md5(captcha) == expected);
}

function validateRegistration(formName) {
    return validateUsername(formName) &&
           validateName(formName) &&
           validateSurname(formName) &&
           validatePassword1(formName) &&
           validatePassword2(formName) &&
           validateEmail(formName) &&
           validateBirthdate(formName) &&
           validateTown(formName) &&
           validateCountry(formName) &&
           validateCaptcha(formName);
}

function validatePasswordReset(formName) {
    return validateUsername(formName) &&
           validateEmail(formName, false) &&
           validateCaptcha(formName);
}

function validatePasswordResetLast(formName) {
    return validateUsername(formName) &&
           validatePassword1(formName) &&
           validatePassword2(formName);
}
