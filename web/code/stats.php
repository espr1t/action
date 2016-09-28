<?php
require_once('db/brain.php');
require_once('config.php');
require_once('common.php');
require_once('page.php');

class StatsPage extends Page {
    public function getTitle() {
        return 'O(N)::Stats';
    }
    
    public function getContent() {
        $brain = new Brain();

        // Problem statistics
        $difficulty = array();
        $numProblems = $brain->getCount('Problems');
        foreach ($GLOBALS['PROBLEM_DIFFICULTIES'] as $diff) {
            $difficulty[$diff] = $brain->getCountWhere('Problems', 'difficulty', $diff);
        }

        // Submission statistics
        $language = array();
        $numSubmissions = $brain->getCount('Submits');
        foreach ($GLOBALS['SUPPORTED_LANGUAGES'] as $lang) {
            $language[$lang] = $brain->getCountWhere('Submits', 'language', $lang);
        }

        // User statistics
        $genders = array();
        $numUsers = $brain->getCount('Users');
        $genders['male'] = $brain->getCountWhere('Users', 'gender', 'male');
        $genders['female'] = $brain->getCountWhere('Users', 'gender', 'female');
        $genders['unknown'] = $brain->getCountWhere('Users', 'gender', '');

        $content = inBox('
            <h1>Статистики</h1>
            Произволни статистики за системата и потребителите.
        ') . inBox('
            <h2>Задачи и решения</h2>

            <b>Брой задачи:</b> ' . $numProblems . '<br>
            <ul>
                <li>Trivial: ' . $difficulty['trivial'] . '</li>
                <li>Easy: ' . $difficulty['easy'] . '</li>
                <li>Medium: ' . $difficulty['medium'] . '</li>
                <li>Hard: ' . $difficulty['hard'] . '</li>
                <li>Brutal: ' . $difficulty['brutal'] . '</li>
            </ul>
            (да се направи на pie chart)

            <br><br>

            <b>Bar chart с таговете на задачите</b>

            <br><br>

            <b>Брой предадени решения:</b> ' . $numSubmissions . '
            <ul>
                <li>C++: ' . sprintf('%.2f', $language['C++'] * 100.0 / $numSubmissions) . '%</li>
                <li>Java: ' . sprintf('%.2f', $language['Java'] * 100.0 / $numSubmissions) . '%</li>
                <li>Python: ' . sprintf('%.2f', $language['Python'] * 100.0 / $numSubmissions) . '%</li>
            </ul>
            (да се направи на pie chart)

            <br><br>

            <b>Графика по час на деня</b><br>
        ') . inBox('
            <h2>Потребители</h2>

            <b>Брой потребители:</b> ' . $numUsers . '<br>
            <ul>
                <li>Mъже: ' . $genders['male'] . '</li>
                <li>Жени: ' . $genders['female'] . '</li>
                <li>Не са казали: ' . $genders['unknown'] . '</li>
            </ul>
            (да се направи на pie chart)

            <br><br>

            <b>Хистограма по възраст</b><br>
            <b>Графика брой потребители във времето</b><br>
            <b>Графика на брой активни потребители по ден в годината</b><br>
            <b>Графика на брой активни потребители по час в денонощието</b><br>
            <b>Word Cloud с постиженията на юзърите<b></br>
        ');
        return $content;
    }
    
}

?>