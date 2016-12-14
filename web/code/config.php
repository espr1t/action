<?php

// Logging configuration
$ERROR_LOG_PATH = $_SERVER['DOCUMENT_ROOT'] . '/error_log.txt';
ini_set('error_log', $ERROR_LOG_PATH);
ini_set('log_errors', true);

// Set timezone
date_default_timezone_set('Europe/Sofia');

// Database
$DB_SERVER = '192.168.1.144';
$DB_PORT = 3306;
$DB_USERNAME = 'action';
$DB_PASSWORD = 'password';
$DB_DATABASE = 'action';

// Session and cookie
$COOKIE_NAME = 'action.informatika.bg';

// Grader
$GRADER_URL = 'localhost:5000';

// Will not work with random UTF-8 characters since the utf8_encode() algorithm
// in PHP and Python is apparently different. Will work with Latin letters, digits and most symbols.
$GRADER_USERNAME = 'username';
$GRADER_PASSWORD = 'password';

$GRADER_ENDPOINT_AVAILABLE = '/available';
$GRADER_ENDPOINT_EVALUATE = '/evaluate';

// Grader-exposed system endpoints
$WEB_ENDPOINT_UPDATE = sprintf('http://%s/actions/update', $_SERVER['HTTP_HOST']);
$WEB_ENDPOINT_TESTS = sprintf('http://%s/data/problems/%%s/Tests/', $_SERVER['HTTP_HOST']);

// User access
$ADMIN_USER_ACCESS = 100;
$DEFAULT_USER_ACCESS = 10;

$ACCESS_ADMIN_PAGES = 100;
$ACCESS_EDIT_PROBLEM = 60;
$ACCESS_PUBLISH_NEWS = 50;
$ACCESS_REPORT_PROBLEM = 2;
$ACCESS_SUBMIT_SOLUTION = 1;

// System paths
$PATH_PROBLEMS = sprintf('%s/data/problems', $_SERVER['DOCUMENT_ROOT']);
$PATH_ACHIEVEMENTS = sprintf('%s/data/achievements', $_SERVER['DOCUMENT_ROOT']);
$PATH_AVATARS = sprintf('/images/avatars');

// Submission status
$STATUS_WAITING = 'W';
$STATUS_PREPARING = 'P';
$STATUS_COMPILING = 'C';
$STATUS_TESTING = 'T';
$STATUS_INTERNAL_ERROR = 'IE';
$STATUS_COMPILATION_ERROR = 'CE';
$STATUS_WRONG_ANSWER = 'WA';
$STATUS_TIME_LIMIT = 'TL';
$STATUS_MEMORY_LIMIT = 'ML';
$STATUS_RUNTIME_ERROR = 'RE';
$STATUS_ACCEPTED = 'AC'; // Individual tests are marked with a non-negative number indicating the score for the test

$STATUS_DISPLAY_NAME = array(
    $STATUS_WAITING => 'Waiting',
    $STATUS_PREPARING => 'Preparing',
    $STATUS_COMPILING => 'Compiling',
    $STATUS_TESTING => 'Testing',
    $STATUS_INTERNAL_ERROR => 'Internal Error',
    $STATUS_COMPILATION_ERROR => 'Compilation Error',
    $STATUS_WRONG_ANSWER => 'Wrong Answer',
    $STATUS_TIME_LIMIT => 'Time Limit',
    $STATUS_MEMORY_LIMIT => 'Memory Limit',
    $STATUS_RUNTIME_ERROR => 'Runtime Error',
    $STATUS_ACCEPTED => 'Accepted'
);

// Languages
$SUPPORTED_LANGUAGES = [
    'C++',
    'Java',
    'Python'
];

// Problems
$PROBLEM_TYPES = [
    'ioi',
    'acm',
    'game',
    'relative'
];

$PROBLEM_DIFFICULTIES = [
    'trivial',
    'easy',
    'medium',
    'hard',
    'brutal'
];

$PROBLEM_TAGS = [
    'implement',
    'search',
    'dp',
    'graph',
    'math',
    'geometry',
    'ad-hoc',
    'flow',
    'divconq',
    'bsearch',
    'hashing',
    'strings',
    'sorting',
    'greedy',
    'sg',
    'mitm',
    'datastruct',
    'np'
];

$PROBLEM_INFO_FILENAME = 'info.json';
$PROBLEM_STATEMENT_FILENAME = 'statement.html';
$PROBLEM_TESTS_FOLDER = 'Tests';
$PROBLEM_SOLUTIONS_FOLDER = 'Solutions';

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