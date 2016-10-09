<?php
require_once(__DIR__ . '/../config.php');
require_once(__DIR__ . '/../entities/submit.php');
require_once(__DIR__ . '/../entities/widgets.php');

// User doesn't have access level needed for submitting a solution
if ($user->access < $GLOBALS['ACCESS_SUBMIT_SOLUTION']) {
    printAjaxResponse(array(
        'status' => 'ERROR',
        'message' => 'Нямате права да изпратите задача.'
    ));
}

// User has sent too many submissions on that day
if (!passSpamProtection($user, $GLOBALS['SPAM_SUBMIT_ID'], $GLOBALS['SPAM_SUBMIT_LIMIT'])) {
    printAjaxResponse(array(
        'status' => 'ERROR',
        'message' => 'Превишили сте лимита си за деня.'
    ));
}

// User has rights to submit and has not exceeded the limit for the day
$submit = Submit::newSubmit($user, $_POST['problemId'], $_POST['language'], $_POST['source']);

// In case the solution cannot be written to the database, return an error
// so the user knows something is wrong
if (!$submit->write()) {
    printAjaxResponse(array(
        'status' => 'ERROR',
        'message' => 'Възникна проблем при записването на решението.'
    ));
}

// In case the submission cannot be sent to the grader, return a warning, but record
// it for later evaluation
if (!$submit->send()) {
    printAjaxResponse(array(
        'status' => 'WARNING',
        'message' => 'Решението ще бъде тествано по-късно.'
    ));
}

// Otherwise print success and return the submit ID
printAjaxResponse(array(
    'status' => 'OK',
    'message' => 'Решението беше изпратено успешно.',
    'id' => $submit->id
));

?>