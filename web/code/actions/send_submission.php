<?php
require_once(__DIR__ . '/../config.php');
require_once(__DIR__ . '/../common.php');
require_once(__DIR__ . '/../entities/problem.php');
require_once(__DIR__ . '/../entities/submit.php');

global $user;

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
$submit = Submit::create($user, $problemId, $language, $source, $full);

// Add the submit to the database and queue it for grading.
if (!$submit->add()) {
    printAjaxResponse(array(
        'status' => 'ERROR',
        'message' => 'Възникна проблем при изпращането на решението.'
    ));
}

// Otherwise print success and return the submit ID
printAjaxResponse(array(
    'status' => 'OK',
    'message' => 'Решението беше изпратено успешно.',
    'id' => $submit->id
));

?>