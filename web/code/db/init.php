<?php
require_once('db.php');

$db = new DB();

function output($message) {
    echo $message . '<br>';
}

/*
Table::Users
============
{
    "id": 1,
    "access": 100,
    "registered": "2016-05-23",
    "username": "espr1t",
    "password": "588e412333f6ab16379c62e1ba0c4d5a",
    "name": "Александър Георгиев",
    "email": "froozenone@yahoo.com",
    "town": "София",
    "country": "България",
    "gender": "male",
    "birthdate": "1987-04-12",
    "avatar": "espr1t_square.jpg"
}
*/
output('');
output('Creating table Users...');

if ($db->checkTable('Users')) {
    output('  >> already exists.');
} else {
    $result = $db->query("
        CREATE TABLE `Users`(
            `id` INT NOT NULL AUTO_INCREMENT,
            `access` INT NOT NULL,
            `registered` DATE NOT NULL,
            `username` VARCHAR(32) NOT NULL,
            `password` VARCHAR(32) NOT NULL,
            `name` VARCHAR(64) NOT NULL,
            `email` VARCHAR(64) NOT NULL,
            `town` VARCHAR(32) NOT NULL,
            `country` VARCHAR(32) NOT NULL,
            `gender` VARCHAR(8) NOT NULL,
            `birthdate` DATE NOT NULL,
            `avatar` VARCHAR(255) NOT NULL,
            PRIMARY KEY (`id`)
        );
    ");
    output('  >> ' . ($result !== false ? 'succeeded' : 'failed') . '!');
}

/*
Table::Problems
===============
{
    "id": 1,
    "name": "Input/Output",
    "author": "Александър Георгиев",
    "folder": "input_output",
    "time_limit": 0.2,
    "memory_limit": 16,
    "type": "standard",
    "difficulty": "trivial",
    "origin": "informatika.bg training",
    "checker": "",
    "executor": "",
    "tags": ["implementation"]
}
*/
output('');
output('Creating table Problems...');

if ($db->query("SELECT 1 FROM `Problems` LIMIT 1;") == true) {
    output('  >> already exists.');
} else {
    $result = $db->query("
        CREATE TABLE `Problems`(
            `id` INT NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(32) NOT NULL,
            `author` VARCHAR(64) NOT NULL,
            `folder` VARCHAR(32) NOT NULL,
            `time_limit` FLOAT NOT NULL,
            `memory_limit` FLOAT NOT NULL,
            `type` ENUM('standard', 'relative', 'game', '') NOT NULL,
            `difficulty` ENUM(
                'trivial',
                'easy',
                'medium',
                'hard',
                'brutal'
            ) NOT NULL,
            `origin` VARCHAR(128) NOT NULL,
            `checker` VARCHAR(32) NOT NULL,
            `executor` VARCHAR(32) NOT NULL,
            `tags` SET(
                'implement',
                'search',
                'dp',
                'graph',
                'math',
                'geometry',
                'ad-hoc',
                'flow',
                'divconq',
                'binsearch',
                'hashing',
                'strings',
                'sorting',
                'greedy',
                'sg',
                'mitm',
                'datastruct',
                'stl',
                'np'
            ) NOT NULL,
            PRIMARY KEY (`id`)
        );
    ");
    output('  >> ' . ($result !== false ? 'succeeded' : 'failed') . '!');
}

/*
Table::Tests
============
{
    "id": 1,
    "problem": 1,
    "position": 3,
    "inp_file": "InputOutput.in",
    "inp_hash": "189342e2ed9d23bb9a02ecbf8ed06762",
    "sol_file": "InputOutput.sol",
    "sol_hash": "550237b8fbcdf3741bb1127d0fc7f6bf",
    "group": 3,
    "score": 100
}
*/
output('');
output('Creating table Tests...');

if ($db->query("SELECT 1 FROM `Tests` LIMIT 1;") == true) {
    output('  >> already exists.');
} else {
    $result = $db->query("
        CREATE TABLE `Tests`(
            `id` INT NOT NULL AUTO_INCREMENT,
            `problem` INT NOT NULL,
            `position` INT NOT NULL,
            `inp_file` VARCHAR(32) NOT NULL,
            `inp_hash` VARCHAR(32) NOT NULL,
            `sol_file` VARCHAR(32) NOT NULL,
            `sol_hash` VARCHAR(32) NOT NULL,
            `group` INT NOT NULL,
            `score` INT NOT NULL,
            PRIMARY KEY (`id`)
        );
    ");
    output('  >> ' . ($result !== false ? 'succeeded' : 'failed') . '!');
}

/*
Table::Submits
==============
{
    "id": 1,
    "problem": 1,
    "user": 1,
    "time": "2016-08-30 00:24:11",
    "language": java,
    "results": "1,1,-3,0.42,-6",
    "status": -1,
    "message": "Undefined variable 'foo'"
}
*/
output('');
output('Creating table Submits...');

if ($db->query("SELECT 1 FROM `Submits` LIMIT 1;") == true) {
    output('  >> already exists.');
} else {
    $result = $db->query("
        CREATE TABLE `Submits`(
            `id` INT NOT NULL AUTO_INCREMENT,
            `problem` INT NOT NULL,
            `user` INT NOT NULL,
            `time` DATETIME NOT NULL,
            `source` TEXT NOT NULL,
            `language` ENUM('cpp', 'java', 'python') NOT NULL,
            `results` TEXT NOT NULL,
            `status` INT NOT NULL,
            `message` TEXT NOT NULL,
            PRIMARY KEY (`id`)
        );
    ");
    output('  >> ' . ($result !== false ? 'succeeded' : 'failed') . '!');
}

/*
Table::Pending
===========
{
    "submit": 421337,
    "user_name": "espr1t",
    "problem_name": "Input/Output",
    "time": "2016-08-30 00:24:11",
    "progress": 0.66,
    "status": -3
}
*/
output('');
output('Creating table Pending...');

if ($db->query("SELECT 1 FROM `Pending` LIMIT 1;") == true) {
    output('  >> already exists.');
} else {
    $result = $db->query("
        CREATE TABLE `Pending`(
            `submit` INT NOT NULL,
            `user_id` INT NOT NULL,
            `user_name` VARCHAR(32) NOT NULL,
            `problem_id` INT NOT NULL,
            `problem_name` VARCHAR(32) NOT NULL,
            `time` DATETIME NOT NULL,
            `progress` FLOAT NOT NULL,
            `status` INT NOT NULL
        );
    ");
    output('  >> ' . ($result !== false ? 'succeeded' : 'failed') . '!');
}

/*
Table::Latest
===========
{
    "submit": 421337,
    "user_name": "espr1t",
    "problem_name": "Input/Output",
    "time": "2016-08-29 23:54:33",
    "progress": 1.0,
    "status": -9
}
*/
output('');
output('Creating table Latest...');

if ($db->query("SELECT 1 FROM `Latest` LIMIT 1;") == true) {
    output('  >> already exists.');
} else {
    $result = $db->query("
        CREATE TABLE `Latest`(
            `submit` INT NOT NULL,
            `user_id` INT NOT NULL,
            `user_name` VARCHAR(32) NOT NULL,
            `problem_id` INT NOT NULL,
            `problem_name` VARCHAR(32) NOT NULL,
            `time` DATETIME NOT NULL,
            `progress` FLOAT NOT NULL,
            `status` INT NOT NULL
        );
    ");
    output('  >> ' . ($result !== false ? 'succeeded' : 'failed') . '!');
}

/*
Table::News
===========
{
    "id": 1,
    "date": 2016-08-19,
    "title": "Работата по Арената е започната!",
    "text": "Some text"
}
*/
output('');
output('Creating table News...');

if ($db->query("SELECT 1 FROM `News` LIMIT 1;") == true) {
    output('  >> already exists.');
} else {
    $result = $db->query("
        CREATE TABLE `News`(
            `id` INT NOT NULL AUTO_INCREMENT,
            `date` DATE NOT NULL,
            `title` TEXT NOT NULL,
            `content` TEXT NOT NULL,
            PRIMARY KEY (`id`)
        );
    ");
    output('  >> ' . ($result !== false ? 'succeeded' : 'failed') . '!');
}

/*
Table::Achievements
===================
{
    "id": 1,
    "user": 1,
    "achievement": 1,
    "date": 2016-07-23
}
*/

output('');
output('Creating table Achievements...');

if ($db->query("SELECT 1 FROM `Achievements` LIMIT 1;") == true) {
    output('  >> already exists.');
} else {
    $result = $db->query("
        CREATE TABLE `Achievements`(
            `id` INT NOT NULL AUTO_INCREMENT,
            `user` INT NOT NULL,
            `achievement` INT NOT NULL,
            `date` DATE NOT NULL,
            PRIMARY KEY (`id`)
        );
    ");
    output('  >> ' . ($result !== false ? 'succeeded' : 'failed') . '!');
}

?>