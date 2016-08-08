<?php
require_once('common.php');
require_once('page.php');

class StatsPage extends Page {
    public function getTitle() {
        return 'O(N)::Stats';
    }
    
    public function getContent() {
        // TODO: Make this smarter as it may be too slow.
        $numUsers = count(scandir($GLOBALS['PATH_USERS'])) - 2;
        $numTasks = count(scandir($GLOBALS['PATH_PROBLEMS'])) - 2;
        // TODO: Actually count the number of solutions.
        $cpp = 0;
        $java = 0;
        $python = 0;

        $content = inBox('
            <h1>Статистики</h1>
            Произволни статистики за системата и потребителите.
        ') . inBox('
            <h2>Потребители</h2>

            <br>

            <b>Брой потребители:</b> ' . $numUsers . '<br>
            <ul>
                <li>Mъже: </li>
                <li>Жени: </li>
                <li>Графика по възраст</li>
                <li>Графика брой във времето</li>
                <li>Брой активни потребители по ден в годината</li>
                <li>Брой активни потребители по час в денонощието</li>
            </ul>
        ') . inBox('
            <h2>Задачи и решения</h2>

            <br>

            <b>Брой задачи:</b> ' . $numTasks . '<br>
            <ul>
                <li>Trivial: </li>
                <li>Easy: </li>
                <li>Medium: </li>
                <li>Hard: </li>
                <li>Brutal: </li>
            </ul>

            <br>

            <b>Брой предадени решения:</b> ' . ($cpp + $java + $python) . '
            <ul>
                <li>C++: ' . $cpp . '%</li>
                <li>Java: ' . $java . '%</li>
                <li>Python: ' . $python . '%</li>
                <li>Графика по час на деня</li>
            </ul>
        ');
        return $content;
    }
    
}

?>