<?php
require_once(__DIR__ . '/../config.php');
require_once(__DIR__ . '/../common.php');
require_once(__DIR__ . '/../entities/problem.php');
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

// Transform data from strings to proper types
$problemId = intval($_POST['problemId']);
$language = $_POST['language'];
$source = $_POST['source'];
$full = boolval($_POST['full']);

// Check if submission is allowed (may be too soon after latest submit)
$problem = Problem::get($problemId);
$remainPartial = 0;
$remainFull = 0;
getWaitingTimes($user, $problem, $remainPartial, $remainFull);

$remainingTime = $full ? $remainFull : $remainPartial;
// Too soon (for games) - have a few minutes between submits
if (($full && $remainFull > 0) || (!$full && $remainPartial > 0)) {
    printAjaxResponse(array(
        'status' => 'ERROR',
        'message' => 'Остават още ' . $remainingTime . ' секунди преди да можете да предадете.'
    ));
}
// Too soon (for regular problems) - have at least 5 seconds between submits
if ($problem->waitFull == 0 && $remainFull >= -5) {
    // Fail silently as most likely the user got a response for his first submit.
    printAjaxResponse(array(
        'status' => 'NONE',
        'message' => 'Действието е пропуснато умишлено.'
    ));
}

// User has rights to submit and has not exceeded the limit for the day
$submit = Submit::newSubmit($user, $problemId, $language, $source, $full, false /* hidden */);

// In case the solution cannot be written to the database,
// return an error so the user knows something is wrong
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