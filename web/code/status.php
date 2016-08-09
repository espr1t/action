<?php
require_once('common.php');
require_once('page.php');

class StatusPage extends Page {
    public function getTitle() {
        return 'O(N)::Status';
    }
    
    public function getContent() {
        $head = inBox('
            <h1>Статус</h1>
            Информация за системата и опашката от решения.
        ');

        $grader = inBox('
            <h2>Грейдър</h2>
            <b>Процесор:</b> Intel i7 @ 4.2GHz<br>
            <b>Рам:</b> Corsair 8GB @ 1066MHz<br>
            <b>Харддиск:</b> Corsair X 250GB SSD<br>

            <br>

            <h2>Компилация</h2>
            <b>C++ (GCC 4.9.4):</b> <pre>g++ -O2 -std=c++11 -o &lt;source&gt; &lt;source&gt;.cpp</pre><br>
            <b>Java (OpenJDK 8):</b> <pre>-Xmx=ML</pre><br>
            <b>Python (3.5):</b> <pre>python &lt;source&gt;</pre></br>
        ');

        $queueList = '';
        $queueList .= '<tr><td>1</td><td>ThinkCreative</td><td>Input/Output</td><td>01:54</td><td>0%</td><td>Waiting</td></tr>';
        $queueList .= '<tr><td>2</td><td>espr1t</td><td>A * B</td><td>01:53</td><td>42%</td><td>Running</td></tr>';
        $queueList .= '<tr><td>3</td><td>espr1t</td><td>A * B</td><td>01:52</td><td>100%</td><td>Compilation Error</td></tr>';
        $queueList .= '<tr><td>4</td><td>ThinkCreative</td><td>Input/Output</td><td>01:49</td><td>91%</td><td>Running</td></tr>';
        $queueList .= '<tr><td>5</td><td>espr1t</td><td>Input/Output</td><td>23:08</td><td>100%</td><td>Accepted</td></tr>';
        $queueList .= '<tr><td>6</td><td>ThinkCreative</td><td>A * B</td><td>20:38</td><td>100%</td><td>Wrong Answer</td></tr>';

        $queueTable = '
            <table class="ranking">
                <tr>
                    <th>#</th><th>Потребител</th><th>Задача</th><th>Час</th><th>Прогрес</th><th>Статус</th>
                </tr>
                ' . $queueList . '
            </table>
        ';

        $queue = inBox('
            <h2>Опашка</h2>
            ' . $queueTable . '
        ');

        $time = '<div class="right smaller italic" style="margin-top: -8px; padding-right: 4px;">Текущо време на системата: ' . date('H:i') . '</div>';

        return $head . $grader . $queue . $time;
    }
    
}

?>