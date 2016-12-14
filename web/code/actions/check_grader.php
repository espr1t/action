<?php
require_once(__DIR__ . '/../entities/grader.php');

$grader = new Grader();
$timer = microtime(true);
if ($grader->available()) {
    printAjaxResponse(array(
        'status' => 'OK',
        'message' => sprintf('%.3lf', microtime(true) - $timer)
    ));
} else {
    printAjaxResponse(array(
        'status' => 'ERROR',
        'message' => 'Грейдърът не може да бъде достъпен.'
    ));
}
