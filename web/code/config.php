<?php

// Logging configuration
$ERROR_LOG_PATH = "{$_SERVER["DOCUMENT_ROOT"]}/error_log.txt";
ini_set("error_log", $ERROR_LOG_PATH);
ini_set("log_errors", true);

// Set timezone
date_default_timezone_set("Europe/Sofia");

// Settings
$MAINTENANCE_MODE = True;

// Database
$DB_SERVER = "localhost";
$DB_PORT = 3306;
$DB_USERNAME = "action";
$DB_PASSWORD = "password";
$DB_DATABASE = "action";

$ADMIN_EMAIL = "admin.email@example.com";

// Google re-CAPTCHA secret key
$RE_CAPTCHA_PROD_SITE_KEY = "f4k3pr0ds173k3y";
$RE_CAPTCHA_PROD_SECRET_KEY = "f4k3pr0ds3cr37k3y";
$RE_CAPTCHA_TEST_SITE_KEY = "f4k3t357s173k3y";
$RE_CAPTCHA_TEST_SECRET_KEY = "f4k3t357s3cr37k3y";

// Session and cookie
$COOKIE_NAME = "action_informatika_bg";

// Grader
// Use actual IP instead of localhost/ for this to work on dev
$GRADER_URL = "127.0.0.1:5000";

// Will not work with random UTF-8 characters since the utf8_encode() algorithm
// in PHP and Python is apparently different. Will work with Latin letters, digits and most symbols.
$GRADER_USERNAME = "username";
$GRADER_PASSWORD = "password";

// Maximum number of submits sent to the grader which are waiting to be tested
// This prevents the front-end from spamming the grader with hundreds of submits at once
// (happens when a rejudge is invoked on a problem with lots of submits)
$GRADER_MAX_WAITING_SUBMITS = 20;

$GRADER_ENDPOINT_AVAILABLE = "/available";
$GRADER_ENDPOINT_RECENT = "/recent";
$GRADER_ENDPOINT_EVALUATE = "/evaluate";
$GRADER_ENDPOINT_PRINT_PDF = "/print";
$GRADER_ENDPOINT_GET_REPLAY = "/replay";

// Grader-exposed system endpoints
$WEB_ENDPOINT_ROOT = PHP_OS == "WINNT" ? "http://{$_SERVER['HTTP_HOST']}" :
                                         "https://{$_SERVER['HTTP_HOST']}";

// User access
$ADMIN_USER_ACCESS = 100;
$DEFAULT_USER_ACCESS = 10;

$ACCESS_ADMIN_PAGES = 100;
$ACCESS_REGRADE_SUBMITS = 90;
$ACCESS_HIDDEN_PROBLEMS = 80;
$ACCESS_SEE_SUBMITS = 70;
$ACCESS_EDIT_PROBLEM = 60;
$ACCESS_PUBLISH_NEWS = 50;
$ACCESS_SEND_MESSAGE = 40;
$ACCESS_SEE_REPLAYS = 20;
$ACCESS_REPORT_PROBLEM = 2;
$ACCESS_SUBMIT_SOLUTION = 1;
$ACCESS_DOWNLOAD_AS_PDF = 1;

// System paths
// Please note that DOCUMENT_ROOT is local path (e.g., /var/www/)
$PATH_DATA = "{$_SERVER['DOCUMENT_ROOT']}/data";
$PATH_AVATARS = "/images/avatars";

// Submission status
// Individual tests are marked with a non-negative number indicating the score for the test
$STATUS_WAITING = "W";
$STATUS_PREPARING = "P";
$STATUS_COMPILING = "C";
$STATUS_TESTING = "T";
$STATUS_INTERNAL_ERROR = "IE";
$STATUS_COMPILATION_ERROR = "CE";
$STATUS_WRONG_ANSWER = "WA";
$STATUS_TIME_LIMIT = "TL";
$STATUS_MEMORY_LIMIT = "ML";
$STATUS_RUNTIME_ERROR = "RE";
$STATUS_ACCEPTED = "AC";

$STATUS_DISPLAY_NAME = array(
    $STATUS_WAITING => "Waiting",
    $STATUS_PREPARING => "Preparing",
    $STATUS_COMPILING => "Compiling",
    $STATUS_TESTING => "Testing",
    $STATUS_INTERNAL_ERROR => "Internal Error",
    $STATUS_COMPILATION_ERROR => "Compilation Error",
    $STATUS_WRONG_ANSWER => "Wrong Answer",
    $STATUS_TIME_LIMIT => "Time Limit",
    $STATUS_MEMORY_LIMIT => "Memory Limit",
    $STATUS_RUNTIME_ERROR => "Runtime Error",
    $STATUS_ACCEPTED => "Accepted"
);

// Languages
$LANGUAGE_CPP = "cpp";
$LANGUAGE_JAVA = "java";
$LANGUAGE_PYTHON = "python";

$SUPPORTED_LANGUAGES = [
    $LANGUAGE_CPP => "C++",
    $LANGUAGE_JAVA => "Java",
    $LANGUAGE_PYTHON => "Python"
];

// Problems
$PROBLEM_TYPE_IOI = "ioi";
$PROBLEM_TYPE_ACM = "acm";
$PROBLEM_TYPE_GAME = "game";
$PROBLEM_TYPE_RELATIVE = "relative";
$PROBLEM_TYPE_INTERACTIVE = "interactive";

$PROBLEM_TYPES = [
    $PROBLEM_TYPE_IOI => "IOI",
    $PROBLEM_TYPE_ACM => "ACM",
    $PROBLEM_TYPE_GAME => "Game",
    $PROBLEM_TYPE_RELATIVE => "Relative",
    $PROBLEM_TYPE_INTERACTIVE => "Interactive"
];

$PROBLEM_DIFFICULTY_TRIVIAL = "trivial";
$PROBLEM_DIFFICULTY_EASY = "easy";
$PROBLEM_DIFFICULTY_MEDIUM = "medium";
$PROBLEM_DIFFICULTY_HARD = "hard";
$PROBLEM_DIFFICULTY_BRUTAL = "brutal";

$PROBLEM_DIFFICULTIES = [
    $PROBLEM_DIFFICULTY_TRIVIAL => "Trivial",
    $PROBLEM_DIFFICULTY_EASY => "Easy",
    $PROBLEM_DIFFICULTY_MEDIUM => "Medium",
    $PROBLEM_DIFFICULTY_HARD => "Hard",
    $PROBLEM_DIFFICULTY_BRUTAL => "Brutal"
];

$PROBLEM_TAGS = [
    "datastruct" => "Data Structures",
    "greedy" => "Greedy",
    "graph" => "Graphs",
    "flow" => "Flows",
    "sorting" => "Sorting",
    "search" => "Search",
    "strings" => "Strings",
    "geometry" => "Geometry",
    "math" => "Math",
    "dp" => "Dynamic Programming",
    "ad-hoc" => "Ad-Hoc",
    "np" => "NP-Complete",
    "divconq" => "Divide & Conquer",
    "game" => "Game Theory",
    "implement" => "Implementation"
];

// Spam protection
$SPAM_INTERVAL = 86400; // Seconds in 24 hours
$SPAM_EMAIL_LIMIT = 20; // Per 24 hours
$SPAM_EMAIL_ID = 0;
$SPAM_SUBMIT_LIMIT = 100; // Per 24 hours
$SPAM_SUBMIT_ID = 1;

$PASSWORD_SALT = "informatika.bg";
$CAPTCHA_CITIES = array(
    "Sofia", "Plovdiv", "Pleven", "Varna", "Burgas", "Shumen",
    "Yambol", "Gabrovo", "Haskovo", "Vidin", "Vratsa", "Sliven"
);

// Password Reset
$RESET_PASSWORD_TIMEOUT = 7 * 24 * 60 * 60; // One week in seconds.

// Logging
$LOG_LOGINS = "signin.log";
$LOG_LOGOUTS = "signin.log";
$LOG_REGISTERS = "user.log";
$LOG_PASS_RESETS = "user.log";
$LOG_PROFILE_UPDATES = "user.log";
$LOG_SUBMITS = "submit.log";
$LOG_ACHIEVEMENTS = "achievement.log";
$LOG_PAGE_VIEWS = "page_view.log";
$LOG_PROFILE_VIEWS = "profile_view.log";
$LOG_GRADER = "grader.log";

// Miscellaneous
$NOTIFICATION_DISPLAY_TIME = 3000; // Milliseconds

?>
