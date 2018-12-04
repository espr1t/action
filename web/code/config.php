<?php

// Logging configuration
$ERROR_LOG_PATH = $_SERVER['DOCUMENT_ROOT'] . '/error_log.txt';
ini_set('error_log', $ERROR_LOG_PATH);
ini_set('log_errors', true);

// Set timezone
date_default_timezone_set('Europe/Sofia');

// Database
$DB_SERVER = '192.168.1.123';
$DB_PORT = 3306;
$DB_USERNAME = 'action';
$DB_PASSWORD = 'password';
$DB_DATABASE = 'action';

// Google re-CAPTCHA secret key
$RE_CAPTCHA_PROD_SITE_KEY = 'f4k3pr0ds173k3y';
$RE_CAPTCHA_PROD_SECRET_KEY = 'f4k3pr0ds3cr37k3y';
$RE_CAPTCHA_TEST_SITE_KEY = 'f4k3t357s173k3y';
$RE_CAPTCHA_TEST_SECRET_KEY = 'f4k3t357s3cr37k3y';

// Session and cookie
$COOKIE_NAME = 'action_informatika_bg';

// Grader
// # Use 192.168.1.XXX/ instead of localhost/ for this to work on dev
$GRADER_URL = '192.168.1.111:5000';

// Will not work with random UTF-8 characters since the utf8_encode() algorithm
// in PHP and Python is apparently different. Will work with Latin letters, digits and most symbols.
$GRADER_USERNAME = 'username';
$GRADER_PASSWORD = 'password';

$ADMIN_EMAIL = 'admin.email@example.com';

$GRADER_ENDPOINT_AVAILABLE = '/available';
$GRADER_ENDPOINT_EVALUATE = '/evaluate';

// Grader-exposed system endpoints
$PROTOCOL = PHP_OS == 'WINNT' ? 'http' : 'https';
$WEB_ENDPOINT_UPDATE = sprintf('%s://%s/actions/update', $PROTOCOL, $_SERVER['HTTP_HOST']);
$WEB_ENDPOINT_TESTS = sprintf('%s://%s/data/problems/%%s/Tests/', $PROTOCOL, $_SERVER['HTTP_HOST']);
$WEB_ENDPOINT_CHECKER = sprintf('%s://%s/data/problems/%%s/Tests/Checker/', $PROTOCOL, $_SERVER['HTTP_HOST']);
$WEB_ENDPOINT_TESTER = sprintf('%s://%s/data/problems/%%s/Tests/Tester/', $PROTOCOL, $_SERVER['HTTP_HOST']);

// User access
$ADMIN_USER_ACCESS = 100;
$DEFAULT_USER_ACCESS = 10;

$ACCESS_ADMIN_PAGES = 100;
$ACCESS_REGRADE_SUBMITS = 90;
$ACCESS_HIDDEN_PROBLEMS = 80;
$ACCESS_SEE_SUBMITS = 70;
$ACCESS_EDIT_PROBLEM = 60;
$ACCESS_PUBLISH_NEWS = 50;
$ACCESS_SEE_REPLAYS = 20;
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
    'cpp' => 'C++',
    'java' => 'Java',
    'python' => 'Python'
];

// Problems
$PROBLEM_TYPES = [
    'ioi' => 'IOI',
    'acm' => 'ACM',
    'game' => 'Game',
    'relative' => 'Relative'
];

$PROBLEM_DIFFICULTIES = [
    'trivial' => 'Trivial',
    'easy' => 'Easy',
    'medium' => 'Medium',
    'hard' => 'Hard',
    'brutal' => 'Brutal'
];

$PROBLEM_TAGS = [
    'datastruct' => 'Data Structures',
    'greedy' => 'Greedy',
    'graph' => 'Graphs',
    'flow' => 'Flows',
    'sorting' => 'Sorting',
    'search' => 'Search',
    'strings' => 'Strings',
    'geometry' => 'Geometry',
    'math' => 'Math',
    'dp' => 'Dynamic Programming',
    'ad-hoc' => 'Ad-Hoc',
    'np' => 'NP-Complete',
    'divconq' => 'Divide & Conquer',
    'game' => 'Game Theory',
    'implement' => 'Implementation'
];

$PROBLEM_INFO_FILENAME = 'info.json';
$PROBLEM_STATEMENT_FILENAME = 'statement.html';
$PROBLEM_TESTS_FOLDER = 'Tests';
$PROBLEM_SOLUTIONS_FOLDER = 'Solutions';
$PROBLEM_CHECKER_FOLDER = $PROBLEM_TESTS_FOLDER . '/Checker';
$PROBLEM_TESTER_FOLDER = $PROBLEM_TESTS_FOLDER . '/Tester';

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
$CAPTCHA_CITIES = array('Sofia', 'Plovdiv', 'Pleven', 'Varna', 'Burgas', 'Shumen', 'Yambol', 'Gabrovo', 'Haskovo', 'Vidin', 'Vratsa', 'Sliven');

// Password Reset
$RESET_PASSWORD_TIMEOUT = 7 * 24 * 60 * 60; // One week in seconds.

?>
