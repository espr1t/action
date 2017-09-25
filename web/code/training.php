<?php
require_once('common.php');
require_once('page.php');
require_once('db/brain.php');

class TrainingPage extends Page {
    public function getTitle() {
        return 'O(N)::Training';
    }

    private function initTraining() {
        $brain = new Brain();
        $brain->addTopic(1, 'IMPL', '2,157,134,155,181,184,15,43,194,199,214,164,232', 10);
        $brain->addTopic(2, 'CORN', '119,195,144,171,191,95', 6);
        $brain->addTopic(3, 'RECU', '66,179,63,90,145,102', 5);
        $brain->addTopic(4, 'BRUT', '166,200,229,153,220,203,4,175', 6);
        $brain->addTopic(5, 'SORT', '123,127,215,132,217,221,45,182,128', 7);
    }

    private function getTopicStats($key) {
        $brain = new Brain();
        $topic = $brain->getTopic($key);
        $problems = explode(',', $topic['problems']);
        $solved = $brain->getSolved(/*$this->user->id*/ 42);
        $accepted = array_filter($solved, function($el) use ($problems) {return in_array($el, $problems);});
        $parentId = 'topic-stats-' . $key;
        return '<script>circularProgress(\'' . $parentId . '\', ' . count($accepted) . ', ' . count($problems) . ');</script>';
    }

    private function getTopicBox($key, $title, $text) {
        return inBox('
            <div class="training-list-entry">
                <div class="training-list-text">
                    <h2>' . $title . '</h2>
                    ' . $text . '
                </div>
                <div class="training-list-progress" id="topic-stats-' . $key . '">
                    ' . $this->getTopicStats($key) . '
                </div>
            </div>
        ');

    }

    private function getTopicBoxImpl() {
        $key = 'IMPL';
        $title = 'Implementation';
        $text = '
            Секцията покрива вход и изход от програма, работа с масиви и символни низове.
            Упражняват се лесни задачи, които изискват директна имплементация на описана процедура.
        ';
        /*
            Секцията покрива вход и изход от програма, работа с масиви и символни низове. Подходящи теми, които можете да прочетете, са
            <a href="http://www.informatika.bg/lectures/io" target="_blank">Вход и изход от програма</a>,
            <a href="http://www.informatika.bg/lectures/variables-structs-arrays" target="_blank">Променливи, масиви, и структури</a>,
            и <a href="http://www.informatika.bg/lectures/search-and-iteration" target="_blank">Търсене и итерация</a>.<br>
            Упражняват се лесни задачи, които изискват директна имплементация на описана процедура.
        */
        return $this->getTopicBox($key, $title, $text);
    }
    
    private function getTopicBoxCorn() {
        $key = 'CORN';
        $title = 'Corner Cases';
        $text = '
            Секцията покрива задачи с относително лесно решение, в които обаче има някаква уловка, частни случаи, или нещо,
            което лесно може да се обърка при имплементацията.
        ';
        /*
            Секцията покрива задачи с относително лесно решение, в които обаче има някаква уловка. Такива задачи могат да струват много "скъпо" на
            състезателите в състезания, където за да се мине задачата се изисква правилно справяне с всички тестове (например <a href="http://codeforces.com/"
            target="_blank">CodeForces</a>, <a href="https://www.topcoder.com/tc" target="_blank">TopCoder</a>, и <a href="https://icpc.baylor.edu/"
            target="_blank">ACM ICPC</a>). Подходяща тема, която би ви помогнала да пишете по-чист код и така да избягвате потенциални проблеми е тази за
            <a href="http://www.informatika.bg/lectures/coding-conventions" target="_blank">Конвенции за стил на кода</a>.<br>
            Упражняват се лесни задачи, в които има частни случаи или нещо, което лесно може да се обърка при имплементацията. В темите по-нататък
            ще срещнете и други, по-сложни такива задачи.
        */
        return $this->getTopicBox($key, $title, $text);
    }

    private function getTopicBoxRecu() {
        $key = 'RECU';
        $title = 'Recursion & Backtrack';
        $text = '
            Секцията покрива рекурсия и търсене с връщане (backtrack), както и различни оптимизации, които могат да се приложат при тях
            (ред на извикване на под-задачите, рязане на рекурсията (pruning), и други).
        ';
        /*
            Секцията покрива рекурсия и търсене с връщане (backtrack), както и различни оптимизации, които могат да се приложат при тях
            (ред на извикване на под-задачите, рязане на рекурсията (pruning), и други). Подходяща тема, която можете да прочетете е
            <a href="http://www.informatika.bg/lectures/recursion" target="_blank">Рекурсия и търсене с връщане</a>.<br>
            Упражняват се лесни до средно-трудни задачи, в които трябва да се имплементира решение, базирано на рекурсия.
        */
        return $this->getTopicBox($key, $title, $text);
    }

    private function getTopicBoxBrut() {
        $key = 'BRUT';
        $title = 'Bruteforce';
        $text = '
            Секцията покрива задачи, които се решават с "груба сила" - обикновено пълно изчерпване или поне изчерпване на част
            от задачата за опростяване на остатъка от нея, както и генериране на малките отговори за стигане до наблюдение.
        ';
        return $this->getTopicBox($key, $title, $text);
    }

    private function getTopicBoxSort() {
        $key = 'SORT';
        $title = 'Sorting';
        $text = '
            Секцията покрива темата за сортиране - "бавни" (O(N<sup>2</sup>) и "бързи" (O(N * log(N)) сортирания, както и сортирания,
            които не са базирани на сравнения (например counting sort).
        ';
        return $this->getTopicBox($key, $title, $text);
    }

    public function getContent() {
        $content = '';
        $content .= inBox('
            <h1>Подготовка</h1>
            Тук можете да навлезете в света на състезателната информатика като учите и тренирате върху задачи, групирани по теми, в нарастваща сложност.
            Темите са така подредени, че да изискват само материал, който е покрит в по-предни. Разбира се, очаква се да можете да владеете основи на
            програмирането (на C++, Java, или Python), което включва как да стартирате програма, вход и изход, типове данни, променливи, масиви, условни оператори, и цикли.
            <br><br>
            В случай, че сте състезател или подготвяте състезатели, ориентировъчно темите са подходящи за следните групи:
            <ul>
            <li><a href="/training/implementation/">Implementation</a>, <a href="/training/corner-cases">Corner Cases</a>, <a href="/training/recursion-and-backtrack">Recursion & Backtrack</a>,
            <a href="/training/bruteforce/">Bruteforce</a>, и <a href="/training/sorting">Sorting</a> са подходящи за ученици от D група и нагоре.</li>
            <li><a href="/training/greedy">Greedy</a>, <a href="/training/math">Math</a>, <a href="/training/simple-data-structures">Simple Data Structures</a>,
            <a href="/training/graphs">Simple Graphs</a>, <a href="/training/binary-and-ternary-search">Binary/Ternary Search</a>, <a href="/training/dynamic-programming">Dynamic Programming</a>,
            <a href="/training/bucketing">Bucketing</a> са подходящи за ученици от C група и нагоре.</li>
            <li><a href="/training/bitmask-dp">Bitmask DP</a>, <a href="/training/sliding-window">Sliding Window</a>, <a href="/training/iterative-dp">Iterative DP</a>,
            <a href="/training/game-theory">Game Theory</a>, <a href="/training/advanced-data-structures">Advanced Data Structures</a>, <a href="/training/strings">Strings</a>,
            <a href="/training/geometry">Geometry</a>, <a href="/training/advanced-graphs">Medium Graphs</a> са подходящи за ученици от B група и нагоре.</li>
            <li><a href="/training/meet-in-the-middle">Meet-in-the-Middle</a>, <a href="/training/probability">Probability</a>, <a href="/training/inner-cycle-optimization">Inner Cycle Optimization</a>,
            <a href="/training/sweep-line">Sweep Line</a>, <a href="/training/advanced-dp">Advanced DP</a>, <a href="/training/advanced-graphs">Advanced Graphs</a> са подходящи за ученици от А група.</li>
            <li><a href="/training/various">Various</a> задачите са предимно Ad-hoc или такива, съчетаващи няколко различни теми. Сложността им е доста варираща.</li>
            </ul>
        ');

        $this->initTraining();

        // Group D
        $content .= $this->getTopicBoxImpl();
        $content .= $this->getTopicBoxCorn();
        $content .= $this->getTopicBoxRecu();
        $content .= $this->getTopicBoxBrut();
        $content .= $this->getTopicBoxSort();

        return $content;
    }
    
}

?>