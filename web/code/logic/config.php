<?php

// Set timezone
date_default_timezone_set('Europe/Sofia');

// Session and cookie
$COOKIE_NAME = 'action.informatika.bg';

// Grader
$GRADER_URL = 'localhost:5000';

// User access
$DEFAULT_USER_ACCESS = 10;

$ACCESS_ADMIN_PAGES = 100;
$ACCESS_PUBLISH_NEWS = 50;
$ACCESS_REPORT_PROBLEM = 2;
$ACCESS_SUBMIT_SOLUTION = 1;

// System paths
$PATH_PROBLEMS = $_SERVER['DOCUMENT_ROOT'] . '/data/problems';
$PATH_USERS = $_SERVER['DOCUMENT_ROOT'] . '/data/users';
$PATH_NEWS = $_SERVER['DOCUMENT_ROOT'] . '/data/news';
$PATH_SUBMISSIONS = $_SERVER['DOCUMENT_ROOT'] . '/data/submissions';
$PATH_LOGIC = $_SERVER['DOCUMENT_ROOT'] . '/code/logic';
$PATH_ACHIEVEMENTS = $_SERVER['DOCUMENT_ROOT'] . '/data/achievements';

// Submission status
$STATUS_WAITING = -1;
$STATUS_RUNNING = -2;
$STATUS_INTERNAL_ERROR = -3;
$STATUS_COMPILATION_ERROR = -4;
$STATUS_MEMORY_LIMIT = -5;
$STATUS_TIME_LIMIT = -6;
$STATUS_RUNTIME_ERROR = -7;
$STATUS_WRONG_ANSWER = -8;
$STATUS_ACCEPTED = -9; // Individual tests are marked with a non-negative number indicating the score for the test

$STATUS_DISPLAY_NAME = array(
    $STATUS_WAITING => 'Waiting',
    $STATUS_RUNNING => 'Running',
    $STATUS_INTERNAL_ERROR => 'Internal Error',
    $STATUS_COMPILATION_ERROR => 'Compilation Error',
    $STATUS_MEMORY_LIMIT => 'Memory Limit',
    $STATUS_TIME_LIMIT => 'Time Limit',
    $STATUS_RUNTIME_ERROR => 'Runtime Error',
    $STATUS_WRONG_ANSWER => 'Wrong Answer',
    $STATUS_ACCEPTED => 'Accepted'
);

// Problems
$PROBLEM_INFO_FILENAME = '/problem_info.json';
$PROBLEM_STATEMENT_FILENAME = '/problem_statement.html';

// Spam protection
$SPAM_INTERVAL = 86400; // Seconds in 24 hours
$SPAM_EMAIL_LIMIT = 20; // Per 24 hours
$SPAM_EMAIL_ID = 0;
$SPAM_SUBMIT_LIMIT = 100; // Per 24 hours
$SPAM_SUBMIT_ID = 1;

$LANGUAGE_EXTENSIONS = array(
    'C++' => 'cpp',
    'Java' => 'java',
    'Python' => 'py'
);

$PASSWORD_SALT = 'informatika.bg';

?>