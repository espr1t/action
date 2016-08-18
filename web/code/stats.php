<?php
require_once('logic/common.php');
require_once('page.php');

class StatsPage extends Page {
    public function getTitle() {
        return 'O(N)::Stats';
    }
    
    public function getContent() {
        // TODO: Make this entire action smarter as it may be too slow.

        // Calculate problem statistics
        $numProblems = 0;
        $language = array('C++' => 0, 'Java' => 0, 'Python' => 0);
        $difficulty = array('trivial' => 0, 'easy' => 0, 'medium' => 0, 'hard' => 0, 'brutal' => 0);

        foreach (scandir($GLOBALS['PATH_PROBLEMS']) as $dir) {
            if ($dir == '.' || $dir == '..') {
                continue;
            }
            $fileName = sprintf('%s/%s/%s', $GLOBALS['PATH_PROBLEMS'], $dir, $GLOBALS['PROBLEM_INFO_FILENAME']);
            $info = json_decode(file_get_contents($fileName), true);
            $difficulty[$info['difficulty']] = $difficulty[$info['difficulty']] + 1;
            $numProblems = $numProblems + 1;
        }

        $numSubmissions = 0;
        foreach (scandir($GLOBALS['PATH_SUBMISSIONS']) as $bucket) {
            if ($bucket == '.' || $bucket == '..') {
                continue;
            }
            foreach (scandir(sprintf('%s/%s', $GLOBALS['PATH_SUBMISSIONS'], $bucket)) as $file) {
                if (strpos($file, '.json') == false) {
                    continue;
                }
                $fileName = sprintf('%s/%s/%s', $GLOBALS['PATH_SUBMISSIONS'], $bucket, $file);
                $info = json_decode(file_get_contents($fileName), true);
                $language[$info['language']] = $language[$info['language']] + 1;
                $numSubmissions = $numSubmissions + 1;
            }
        }

        // Calculate user statistics
        $numUsers = 0;
        $users = array('male' => 0, 'female' => 0, 'unknown' => 0);
        foreach (scandir($GLOBALS['PATH_USERS']) as $entry) {
            if (!preg_match(User::$user_info_re, basename($entry))) {
                continue;
            }

            $fileName = sprintf("%s/%s", $GLOBALS['PATH_USERS'], $entry);
            $info = json_decode(file_get_contents($fileName), true);

            if ($info['username'] == 'anonymous') {
                continue;
            }
            if ($info['gender'] == '') {
                $users['unknown'] = $users['unknown'] + 1;
            } else {
                $users[$info['gender']] = $users[$info['gender']] + 1;
            }
            $numUsers = $numUsers + 1;
        }

        $content = inBox('
            <h1>Статистики</h1>
            Произволни статистики за системата и потребителите.
        ') . inBox('
            <h2>Задачи и решения</h2>

            <br>

            <b>Брой задачи:</b> ' . $numProblems . '<br>
            <ul>
                <li>Trivial: ' . $difficulty['trivial'] . '</li>
                <li>Easy: ' . $difficulty['easy'] . '</li>
                <li>Medium: ' . $difficulty['medium'] . '</li>
                <li>Hard: ' . $difficulty['hard'] . '</li>
                <li>Brutal: ' . $difficulty['brutal'] . '</li>
            </ul>

            <br>

            <b>Брой предадени решения:</b> ' . $numSubmissions . '
            <ul>
                <li>C++: ' . ($language['C++'] * 100.0 / $numSubmissions) . '%</li>
                <li>Java: ' . ($language['Java'] * 100.0 / $numSubmissions) . '%</li>
                <li>Python: ' . ($language['Python'] * 100.0 / $numSubmissions) . '%</li>
            </ul>

            <br>

            <b>Графика по час на деня</b><br>
        ') . inBox('
            <h2>Потребители</h2>

            <br>

            <b>Брой потребители:</b> ' . $numUsers . '<br>
            <ul>
                <li>Mъже: ' . $users['male'] . '</li>
                <li>Жени: ' . $users['female'] . '</li>
                <li>Не са казали: ' . $users['unknown'] . '</li>
            </ul>

            <br>

            <b>Графика по възраст</b><br>
            <b>Графика брой във времето</b><br>
            <b>Графика на брой активни потребители по ден в годината</b><br>
            <b>Графика на брой активни потребители по час в денонощието</b><br>
        ');
        return $content;
    }
    
}

?>