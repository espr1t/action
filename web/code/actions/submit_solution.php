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

// If cannot write the submit info file or update the user/problem or the solution cannot be sent
// to the grader for judging (the grader machine is down or not accessible)
if (!$submit->write() || !$submit->send()) {
    printAjaxResponse(array(
        'status' => 'ERROR',
        'message' => 'Възникна проблем при изпращането на решението.'
    ));
}
// Otherwise print success and return the submit ID
else {
    printAjaxResponse(array(
        'status' => 'OK',
        'id' => $submit->id
    ));
}

?>