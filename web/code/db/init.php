<?php
require_once(__DIR__ . '/db.php');

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
    "loginKey": "189342e2ed9d23bb9a02ecbf8ed06762",
    "name": "Александър Георгиев",
    "email": "froozenone@yahoo.com",
    "town": "София",
    "country": "България",
    "gender": "male",
    "birthdate": "1987-04-12",
    "avatar": "espr1t_square.jpg",
    "actions": 1337,
    "totalTime": 42666, // Seconds
    "lastSeen": "2016-05-23T21:29:13"
}
*/
output('');
output('Creating table Users...');

if ($db->tableExists('Users')) {
    output('  >> already exists.');
} else {
    $result = $db->query("
        CREATE TABLE `Users`(
            id INT NOT NULL AUTO_INCREMENT,
            access INT NOT NULL,
            registered DATE NOT NULL,
            username VARCHAR(32) NOT NULL,
            password VARCHAR(32) NOT NULL,
            loginKey VARCHAR(32) NOT NULL,
            name VARCHAR(64) NOT NULL,
            email VARCHAR(64) NOT NULL,
            town VARCHAR(32) NOT NULL,
            country VARCHAR(32) NOT NULL,
            gender VARCHAR(8) NOT NULL,
            birthdate DATE NOT NULL,
            avatar VARCHAR(255) NOT NULL,
            actions INT NOT NULL,
            totalTime INT NOT NULL,
            lastSeen DATETIME NOT NULL,
            PRIMARY KEY (id)
        ) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci;
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
    "timeLimit": 0.2,
    "memoryLimit": 16,
    "type": "standard",
    "difficulty": "trivial",
    "logo": "http://action.informatika.bg/images/games/snakes.png",
    "description": "...",
    "statement": "...",
    "origin": "informatika.bg training",
    "checker": "",
    "tester": "",
    "floats": "0",
    "tags": ["implementation"],
    "waitPartial": 5,
    "waitFull": 180,
    "addedBy": "espr1t",
    "visible": "0"
}
*/
output('');
output('Creating table Problems...');

if ($db->tableExists('Problems')) {
    output('  >> already exists.');
} else {
    $result = $db->query("
        CREATE TABLE `Problems`(
            id INT NOT NULL AUTO_INCREMENT,
            name VARCHAR(32) NOT NULL,
            author VARCHAR(64) NOT NULL,
            folder VARCHAR(64) NOT NULL,
            timeLimit FLOAT NOT NULL,
            memoryLimit FLOAT NOT NULL,
            type ENUM('ioi', 'acm', 'relative', 'game') NOT NULL,
            difficulty ENUM('trivial', 'easy', 'medium', 'hard', 'brutal') NOT NULL,
            logo TEXT NOT NULL,
            description TEXT NOT NULL,
            statement TEXT NOT NULL,
            origin VARCHAR(128) NOT NULL,
            checker VARCHAR(32) NOT NULL,
            tester VARCHAR(32) NOT NULL,
            floats BOOLEAN NOT NULL DEFAULT FALSE,
            tags TINYTEXT NOT NULL,
            waitPartial INT NOT NULL,
            waitFull INT NOT NULL,
            addedBy VARCHAR(32) NOT NULL,
            visible BOOLEAN NOT NULL DEFAULT FALSE,
            PRIMARY KEY (id)
        ) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci;
    ");
    output('  >> ' . ($result !== false ? 'succeeded' : 'failed') . '!');
}

/*
Table::Tests
============
{
    "id": 42,
    "problem": 1,
    "position": 3,
    "inpFile": "InputOutput.in",
    "inpHash": "189342e2ed9d23bb9a02ecbf8ed06762",
    "solFile": "InputOutput.sol",
    "solHash": "550237b8fbcdf3741bb1127d0fc7f6bf",
    "score": 100
}
*/
output('');
output('Creating table Tests...');

if ($db->tableExists('Tests')) {
    output('  >> already exists.');
} else {
    $result = $db->query("
        CREATE TABLE `Tests`(
            id INT NOT NULL AUTO_INCREMENT,
            problem INT NOT NULL,
            position INT NOT NULL,
            inpFile VARCHAR(32) NOT NULL,
            inpHash VARCHAR(32) NOT NULL,
            solFile VARCHAR(32) NOT NULL,
            solHash VARCHAR(32) NOT NULL,
            score FLOAT NOT NULL,
            PRIMARY KEY (id)
        ) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci;
    ");
    output('  >> ' . ($result !== false ? 'succeeded' : 'failed') . '!');
}

/*
Table::Matches
============
{
    "id": 42,
    "problemId": 1,
    "test": 2,
    "userOne": 7,
    "userTwo": 3,
    "submitOne": 61,
    "submitTwo": 55,
    "scoreOne": 7.2,
    "scoreTwo": 9.3,
    "message": "Player One's solution timed out.",
    "log": "RUUDLLRLLRRD(5,3)...RL"
}
*/
output('');
output('Creating table Matches...');

if ($db->tableExists('Matches')) {
    output('  >> already exists.');
} else {
    $result = $db->query("
        CREATE TABLE `Matches`(
            id INT NOT NULL AUTO_INCREMENT,
            problemId INT NOT NULL,
            test INT NOT NULL,
            userOne INT NOT NULL,
            userTwo INT NOT NULL,
            submitOne INT NOT NULL,
            submitTwo INT NOT NULL,
            scoreOne FLOAT NOT NULL,
            scoreTwo FLOAT NOT NULL,
            message TINYTEXT NOT NULL,
            log TEXT NOT NULL,
            PRIMARY KEY (problemId, test, userOne, userTwo),
            KEY `id` (`id`)
        ) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci;
    ");
    output('  >> ' . ($result !== false ? 'succeeded' : 'failed') . '!');
}

/*
Table::Solutions
==============
{
    "problemId": 1,
    "name": "InputOutputSlow.cpp",
    "submitId": 42,
    "source": "#include..."
    "language": "C++",
}
*/
output('');
output('Creating table Solutions...');

if ($db->tableExists('Solutions')) {
    output('  >> already exists.');
} else {
    $result = $db->query("
        CREATE TABLE `Solutions`(
            problemId INT NOT NULL,
            name VARCHAR(32) NOT NULL,
            submitId INT NOT NULL,
            source TEXT NOT NULL,
            language ENUM('C++', 'Java', 'Python') NOT NULL,
            PRIMARY KEY (problemId, name)
        ) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci;
    ");
    output('  >> ' . ($result !== false ? 'succeeded' : 'failed') . '!');
}

/*
Table::Submits
==============
{
    "id": 1,
    "submitted": "2016-08-30 00:24:11",
    "graded": 1484868592.086328,
    "userId": 1,
    "userName": "espr1t",
    "problemId": 1,
    "problemName": "Input/Output",
    "language": "Java",
    "results": "1,1,TL,0.42,WA",
    "exec_time": "0,0.11,0.08,0.13,0.20",
    "exec_memory": "2.90234375,2.83203125,2.9140625,2.90234375,2.94140",
    "status": "T",
    "message": "Undefined variable 'foo'",
    "full": true,
    "hidden": false,
    "ip": "2001:db8:0:0:0:ff00:42:8329"
}
*/
output('');
output('Creating table Submits...');

if ($db->tableExists('Submits')) {
    output('  >> already exists.');
} else {
    $result = $db->query("
        CREATE TABLE `Submits`(
            id INT NOT NULL AUTO_INCREMENT,
            submitted DATETIME NOT NULL,
            graded DOUBLE NOT NULL,
            userId INT NOT NULL,
            userName VARCHAR(32) NOT NULL,
            problemId INT NOT NULL,
            problemName VARCHAR(32) NOT NULL,
            language ENUM('C++', 'Java', 'Python') NOT NULL,
            results TEXT NOT NULL,
            exec_time TEXT NOT NULL,
            exec_memory TEXT NOT NULL,
            status VARCHAR(2) NOT NULL,
            message TEXT NOT NULL,
            full BOOLEAN NOT NULL DEFAULT FALSE,
            hidden BOOLEAN NOT NULL DEFAULT FALSE,
            ip VARCHAR(40) NOT NULL,
            PRIMARY KEY (id)
        ) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci;
    ");
    output('  >> ' . ($result !== false ? 'succeeded' : 'failed') . '!');
}

/*
Table::Sources
==============
{
    "submitId": 172,
    "userId": 1,
    "problemId": 13,
    "language": C++,
    "source": "#include <cstdio>..."
}
*/
output('');
output('Creating table Sources...');

if ($db->tableExists('Sources')) {
    output('  >> already exists.');
} else {
    $result = $db->query("
        CREATE TABLE `Sources`(
            submitId INT NOT NULL,
            userId INT NOT NULL,
            problemId INT NOT NULL,
            language ENUM('C++', 'Java', 'Python') NOT NULL,
            source TEXT NOT NULL,
            PRIMARY KEY (submitId)
        ) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci;
    ");
    output('  >> ' . ($result !== false ? 'succeeded' : 'failed') . '!');
}

/*
Table::Pending
===========
{
    "id": 1,
    "submitId": 421337,
    "userId": 1,
    "userName": "espr1t",
    "problemId": 1,
    "problemName": "Input/Output",
    "time": "2016-08-30 00:24:11",
    "progress": 0.66,
    "status": "W"
}
*/
output('');
output('Creating table Pending...');

if ($db->tableExists('Pending')) {
    output('  >> already exists.');
} else {
    $result = $db->query("
        CREATE TABLE `Pending`(
            id INT NOT NULL AUTO_INCREMENT,
            submitId INT NOT NULL,
            userId INT NOT NULL,
            userName VARCHAR(32) NOT NULL,
            problemId INT NOT NULL,
            problemName VARCHAR(32) NOT NULL,
            time DATETIME NOT NULL,
            progress FLOAT NOT NULL,
            status VARCHAR(2) NOT NULL,
            PRIMARY KEY (id)
        ) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci;
    ");
    output('  >> ' . ($result !== false ? 'succeeded' : 'failed') . '!');
}

/*
Table::Latest
===========
{
    "id": 1,
    "submitId": 421337,
    "userId": 1,
    "userName": "espr1t",
    "problemId": 1,
    "problemName": "Input/Output",
    "time": "2016-08-29 23:54:33",
    "progress": 1.0,
    "status": "AC"
}
*/
output('');
output('Creating table Latest...');

if ($db->tableExists('Latest')) {
    output('  >> already exists.');
} else {
    $result = $db->query("
        CREATE TABLE `Latest`(
            id INT NOT NULL AUTO_INCREMENT,
            submitId INT NOT NULL,
            userId INT NOT NULL,
            userName VARCHAR(32) NOT NULL,
            problemId INT NOT NULL,
            problemName VARCHAR(32) NOT NULL,
            time DATETIME NOT NULL,
            progress FLOAT NOT NULL,
            status VARCHAR(2) NOT NULL,
            PRIMARY KEY (id)
        ) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci;
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
    "content": "Some text",
    "icon": "arrow-circle-up",
    "type": "Improvement"
}
*/
output('');
output('Creating table News...');

if ($db->tableExists('News')) {
    output('  >> already exists.');
} else {
    $result = $db->query("
        CREATE TABLE `News`(
            id INT NOT NULL AUTO_INCREMENT,
            date DATE NOT NULL,
            title TEXT NOT NULL,
            content TEXT NOT NULL,
            icon TEXT NOT NULL,
            type TEXT NOT NULL,
            PRIMARY KEY (id)
        ) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci;
    ");
    output('  >> ' . ($result !== false ? 'succeeded' : 'failed') . '!');
}

/*
Table::Reports
===========
{
    "id": 1,
    "page": "http://action.informatika.bg/queue",
    "content": "Reports are currently not saved in the DB!",
    "user": 42,
    "date": 2016-08-19
}
*/
output('');
output('Creating table Reports...');

if ($db->tableExists('Reports')) {
    output('  >> already exists.');
} else {
    $result = $db->query("
        CREATE TABLE `Reports`(
            id INT NOT NULL AUTO_INCREMENT,
            user INT NOT NULL,
            date DATE NOT NULL,
            page TEXT NOT NULL,
            content TEXT NOT NULL,
            PRIMARY KEY (id)
        ) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci;
    ");
    output('  >> ' . ($result !== false ? 'succeeded' : 'failed') . '!');
}

/*
Table::Achievements
===================
{
    "id": 1,
    "user": 1,
    "achievement": "RGSTRD",
    "date": 2016-07-23
}
*/
output('');
output('Creating table Achievements...');

if ($db->tableExists('Achievements')) {
    output('  >> already exists.');
} else {
    $result = $db->query("
        CREATE TABLE `Achievements`(
            id INT NOT NULL AUTO_INCREMENT,
            user INT NOT NULL,
            achievement VARCHAR(8) NOT NULL,
            date DATE NOT NULL,
            seen BOOLEAN NOT NULL DEFAULT FALSE,
            PRIMARY KEY (id), UNIQUE(user, achievement)
        ) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci;
    ");
    output('  >> ' . ($result !== false ? 'succeeded' : 'failed') . '!');
}

/*
Table::Spam
===================
{
    "type": 0,
    "user": 2,
    "time": 1472937844
}
*/

output('');
output('Creating table Spam...');

if ($db->tableExists('Spam')) {
    output('  >> already exists.');
} else {
    $result = $db->query("
        CREATE TABLE `Spam`(
            id INT NOT NULL AUTO_INCREMENT,
            type INT NOT NULL,
            user INT NOT NULL,
            time INT NOT NULL,
            PRIMARY KEY (id)
        ) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci;
    ");
    output('  >> ' . ($result !== false ? 'succeeded' : 'failed') . '!');
}

/*
Table::Training
==============
{
    "key": "IMPL",
    "order": 1,
    "problems": "1,2,3,5,8,13,21,34,55,89,144,233",
    "threshold": 6
}
*/
output('');
output('Creating table Training...');

if ($db->tableExists('Training')) {
    output('  >> already exists.');
} else {
    $result = $db->query("
        CREATE TABLE `Training`(
            `id` INT NOT NULL,
            `key` VARCHAR(4) NOT NULL,
            `problems` TEXT NOT NULL,
            `threshold` INT NOT NULL,
            PRIMARY KEY (`id`, `key`)
        ) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci;
    ");
    output('  >> ' . ($result !== false ? 'succeeded' : 'failed') . '!');
}

?>