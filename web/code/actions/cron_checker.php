<?php
require_once(__DIR__ . '/../entities/grader.php');
require_once(__DIR__ . '/../common.php');
require_once(__DIR__ . '/../config.php');

$GRADER_CRON_CHECK_FILE = 'cron_checker.txt';

$okay = false;
$grader = new Grader();
for ($try = 0; $try < 3; $try++) {
    if ($grader->available()) {
        $okay = true;
        break;
    }
}

$current = trim(file_get_contents($GRADER_CRON_CHECK_FILE));
if ($okay) {
    if ($current != 'OK' && $current != 'WARNING') {
        // Send mail that it is okay now
        $atTime = getLocalTime();
        sendEmail(
            $GLOBALS['ADMIN_EMAIL'],                            // Address
            'Grader Back to Normal',                            // Subject
            'Grader became accessible again at ' . $atTime      // Message
        );
    }
    // Record that it is OK
    file_put_contents($GRADER_CRON_CHECK_FILE, 'OK');
} else {
    if ($current == 'OK') {
        // Record a warning
        file_put_contents($GRADER_CRON_CHECK_FILE, 'WARNING');
    } else {
        if ($current == 'WARNING') {
            $atTime = getLocalTime();
            // Record the time when it became unavailable
            file_put_contents($GRADER_CRON_CHECK_FILE, $atTime);
            // Send mail that the grader is unavailable
            sendEmail(
                $GLOBALS['ADMIN_EMAIL'],                        // Address
                'Grader Unavailable',                           // Subject
                'Grader became unavailable at ' . $atTime       // Message
            );
        } else {
            // Already sent a mail, so do nothing.
        }
    }
}
