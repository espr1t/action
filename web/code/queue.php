<?php
require_once('common.php');
require_once('page.php');

class QueuePage extends Page {
    public function getTitle() {
        return 'O(N)::Queue';
    }
    
    public function getContent() {
        $head = inBox('
            <h1>Опашка</h1>
            Информация за системата и опашката от решения.
        ');

        $time = '<div class="right smaller italic" style="padding-right: 4px;">Текущо време на системата: ' . date('H:i') . '</div>';

        $doneList = '';
        $doneList .= '<tr><td>1</td><td>ThinkCreative</td><td>Input/Output</td><td>20:38</td><td>100%</td><td>Wrong Answer</td></tr>';
        $doneList .= '<tr><td>2</td><td>espr1t</td><td>A * B</td><td>23:08</td><td>100%</td><td>Compilation Error</td></tr>';
        $doneList .= '<tr><td>3</td><td>espr1t</td><td>A * B</td><td>01:49</td><td>100%</td><td>Runtime Error</td></tr>';
        $doneList .= '<tr><td>4</td><td>ThinkCreative</td><td>Input/Output</td><td>01:52</td><td>100%</td><td>Accepted</td></tr>';
        $doneList .= '<tr><td>5</td><td>espr1t</td><td>Input/Output</td><td>01:53</td><td>100%</td><td>Wrong Answer</td></tr>';
        $doneList .= '<tr><td>6</td><td>ThinkCreative</td><td>A * B</td><td>01:54</td><td>100%</td><td>Accepted</td></tr>';

        $doneTable = '
            <table class="default">
                <tr>
                    <th style="width: 20px;">#</th>
                    <th style="width: 190px;">Потребител</th>
                    <th style="width: 190px;">Задача</th>
                    <th style="width: 70px;">Час</th>
                    <th style="width: 70px;">Прогрес</th>
                    <th>Статус</th>
                </tr>
                ' . $doneList . '
            </table>
        ';

        $done = inBox('
            <h2>Последно тествани</h2>
            ' . $doneTable . '
        ');

        $queueList = '';
        $queueList .= '<tr><td>1</td><td>ThinkCreative</td><td>Input/Output</td><td>01:55</td><td>91%</td><td>Running</td></tr>';
        $queueList .= '<tr><td>2</td><td>espr1t</td><td>A * B</td><td>01:56</td><td>42%</td><td>Running</td></tr>';
        $queueList .= '<tr><td>3</td><td>ThinkCreative</td><td>Input/Output</td><td>01:56</td><td>0%</td><td>Waiting</td></tr>';

        $queueTable = '
            <table class="default">
                <tr>
                    <th style="width: 20px;">#</th>
                    <th style="width: 190px;">Потребител</th>
                    <th style="width: 190px;">Задача</th>
                    <th style="width: 70px;">Час</th>
                    <th style="width: 70px;">Прогрес</th>
                    <th>Статус</th>
                </tr>
                ' . $queueList . '
            </table>
        ';

        $queue = inBox('
            <h2>Изчакващи тестване</h2>
            ' . $queueTable . '
        ');

        $grader = inBox('
            <h2>Грейдър</h2>
            <b>Процесор:</b> Intel Core i5 :: 4.2GHz<br>
            <b>Рам:</b> Kingston DDR4 8GB :: 2133MHz<br>
            <b>Харддиск:</b> Corsair Force SSD :: 240GB<br>

            <br>

            <h2>Компилация</h2>
            <b>C++ (GCC 4.9.4):</b> <pre>g++ -O2 -std=c++11 -o &lt;source&gt; &lt;source&gt;.cpp</pre><br>
            <b>Java (OpenJDK 8):</b> <pre>-Xmx=ML</pre><br>
            <b>Python (3.5):</b> <pre>python &lt;source&gt;</pre></br>
        ');

        return $head . $time . $done . $queue . $grader;
    }
    
}

?>