<?php
// Set timezone
date_default_timezone_set('Europe/Sofia');

// Session and cookie
$COOKIE_NAME = 'action.informatika.bg';

// Grader
$GRADER_URL = 'localhost:5000';

// User access
$DEFAULT_USER_ACCESS = 10;
$ACCESS_REPORT_PROBLEM = 2;
$ACCESS_SUBMIT_SOLUTION = 1;

// System paths
$PATH_PROBLEMS = $_SERVER['DOCUMENT_ROOT'] . '/data/problems';
$PATH_USERS = $_SERVER['DOCUMENT_ROOT'] . '/data/users';
$PATH_NEWS = $_SERVER['DOCUMENT_ROOT'] . '/data/news';
$PATH_SUBMISSIONS = $_SERVER['DOCUMENT_ROOT'] . '/data/submissions';
$PATH_LOGIC = $_SERVER['DOCUMENT_ROOT'] . '/code/logic';
$PATH_ACHIEVEMENTS = $_SERVER['DOCUMENT_ROOT'] . '/data/achievements';

?>