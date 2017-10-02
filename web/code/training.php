<?php
require_once('config.php');
require_once('common.php');
require_once('page.php');
require_once('problems.php');
require_once('db/brain.php');

class TrainingPage extends Page {
    public function getTitle() {
        return 'O(N)::Training';
    }

    private function initIMPL($position) {
        $brain = new Brain();
        $key = 'IMPL';
        $link = 'implementation';
        $title = 'Implementation';
        $summary = '
            Секцията покрива вход и изход от програма, работа с масиви и символни низове.
            Упражняват се лесни задачи, които изискват директна имплементация на описана процедура.
        ';
        $expanded = '
            В тази секция ще се научите как да четете и пишете стрингове, цели числа, числа с плаваща запетая, като
            част от входа и изхода на програма. Ще упражните да манипулирате масиви и символни низове ползвайки
            основните конструкции в програмните езици (if/else, for, while), а както и функции.
            <br><br>
            Подходящи теми, които можете да прочетете, са
            <a href="http://www.informatika.bg/lectures/io" target="_blank">Вход и изход от програма</a>,
            <a href="http://www.informatika.bg/lectures/variables-structs-arrays" target="_blank">Променливи, масиви,
            и структури</a>, и
            <a href="http://www.informatika.bg/lectures/search-and-iteration" target="_blank">Търсене и итерация</a>.
            <br><br>
            За тренировка сме ви подготвили лесни задачи, в повечето от които е казано директно какво се иска от вас да
            имплементирате - тоест просто трябва да превърнете описаната процедура в код.
        ';
        $problems = '2,157,134,155,181,184,15,43,194,199,214,164';
        $brain->addTopic($position, $key, $link, $title, $summary, $expanded, $problems);
    }

    private function initCCAS($position) {
        $brain = new Brain();
        $key = 'CCAS';
        $title = 'Corner Cases';
        $link = 'corner-cases';
        $summary = '
            Секцията покрива задачи с относително лесно решение, в които обаче има някаква уловка,
            частни случаи, или нещо, което лесно може да се обърка при имплементацията.
        ';
        $expanded = '
            В тази секция ще разгледаме за какво да внимаваме, когато имплементираме дадена задача. Ще наблегнем на
            задачи с относително лесно решение, в които обаче има някакъв частен случай или уловка, който(ято) лесно
            може да бъде пропуснат(а). Такива задачи могат да струват много "скъпо" на състезателите в състезания,
            където за да се мине задачата се изисква правилно справяне с всички тестове (като например
            <a href="http://codeforces.com/" target="_blank">CodeForces</a>,
            <a href="https://www.topcoder.com/tc" target="_blank">TopCoder</a>, и
            <a href="https://icpc.baylor.edu/" target="_blank">ACM ICPC</a>).
            <br><br>
            Подходяща тема, която би ви помогнала да пишете по-чист код и така да избягвате потенциални проблеми е тази за
            <a href="http://www.informatika.bg/lectures/coding-conventions" target="_blank">Конвенции за стил на кода</a>.
            <br><br>
            Упражняват се лесни задачи, в които има частни случаи или нещо, което лесно може да се обърка при
            имплементацията. В темите по-нататък ще срещнете и други, по-сложни такива задачи.
        ';
        $problems = '119,195,144,171,191,95';
        $brain->addTopic($position, $key, $link, $title, $summary, $expanded, $problems);
    }

    private function initRECU($position) {
        $brain = new Brain();
        $key = 'RECU';
        $link = 'recursion-and-backtrack';
        $title = 'Recursion & Backtrack';
        $summary = '
            Секцията покрива рекурсия и търсене с връщане, както и различни оптимизации, които могат да се приложат при
            тях (ред на извикване на под-задачите, рязане на рекурсията, и други).
        ';
        $expanded = '
            В тази секция ще разгледаме как да решаваме задачи с изчерпване, ползвайки рекурсия и търсене с връщане
            (backtrack). За някои от задачите ще ви се наложи да измислите оптимизации, за да бъде достатъчно бързо
            решението ви - например в какъв ред да извиквате под-задачите, кои клони на рекурсията нямат смисъл да
            бъдат обходени и могат да бъдат "изрязани" (наричано на Английски "pruning"), както и други.
            <br><br>
            Подходяща тема, която можете да прочетете е
            <a href="http://www.informatika.bg/lectures/recursion" target="_blank">Рекурсия и търсене с връщане</a>.
            <br><br>
            Упражняват се лесни до средно-трудни задачи, в които трябва да се имплементира рекурсивно решение.
        ';
        $problems = '66,179,63,90,145,102';
        $brain->addTopic($position, $key, $link, $title, $summary, $expanded, $problems);
    }

    private function initBRUT($position) {
        $brain = new Brain();
        $key = 'BRUT';
        $link = 'bruteforce';
        $title = 'Bruteforce';
        $summary = '
            Секцията покрива задачи, които се решават с "груба сила" - обикновено пълно изчерпване или поне изчерпване
            на част от задачата за опростяване на остатъка от нея, както и генериране на малките отговори за стигане до
            наблюдение.
        ';
        $expanded = '
            В тази секция ще разгледаме задачи, които или се решават изцяло с пълно изчерпване (но не backtrack), или
            или се ползва изчерпване на някаква част от задачата за да се опрости остатъка от нея. В много задачи не е
            нужно да се ползва изчерпване - съществува ефективен алгоритъм, който ги решава - но той е или труден или
            изисква разглеждането на много частни случаи. Понякога, ако ограниченията го позволяват, можем вместо това
            да обходим всички възможности за дадено нещо в задачата, като така или вече ни трябва значително по-прост
            алгоритъм за останалата част, или броя частни случаи намалява многократно.
            <br><br>
            Допълнително, в задачи (в които обикновено отговорът е едно единствено число), за които нямаме идея как да
            подходим, можем да напишем bruteforce решение за малките стойности, и да се опитаме да намерим някаква
            зависимост в отговорите: може да се окаже някаква редица или проста формула.
            <br><br>
            Последно, на състезания, в които нямаме feedback за изпратените от нас решения преди края (например
            <a href="http://codeforces.com/" target="_blank">CodeForces</a>,
            <a href="https://www.topcoder.com/tc" target="_blank">TopCoder</a>,
            <a href="https://icpc.baylor.edu/" target="_blank">ACM ICPC</a>,
            а също и някои задачи по ученически състезания), понякога е полезно да напишем bruteforce решение с което да
            тестваме "умното" ни такова. Обикновено така се откриват бъгове, като плюсът е, че имаме и тест, с който да
            ги дебъгваме (знаем верния отговор за него от брутфорс решението).
        ';
        $problems = '166,200,229,153,220,203,4,175';
        $brain->addTopic($position, $key, $link, $title, $summary, $expanded, $problems);
    }

    private function initSORT($position) {
        $brain = new Brain();
        $key = 'SORT';
        $link = 'sorting';
        $title = 'Sorting';
        $summary = '
            Секцията покрива темата за сортиране - "бавни" (O(N<sup>2</sup>)) и "бързи" (O(N * log(N)) сортирания, както
            и сортирания, които не са базирани на сравнения (например counting sort).
        ';
        $expanded = '
            В тази секция ще разгледаме една от популярните теми в програмирането - а именно какво е сортиране и
            няколко стандартни алгоритми, които можем да ползваме. Ще разгледаме както "лесни" алгоритми със сложност
            O(N<sup>2</sup>), като например Bubble Sort, Selection Sort, Insertion Sort, така и ефективни
            такива (Quick Sort, Merge Sort, Heap Sort) със сложност O(N*log(N)). Ще видим и начини, по които можем да
            сортираме и за по-бързо (линейно) време, ползвайки Counting Sort.
            <br><br>
            Подходящи теми, които можете да прочетете, са тези за
            <a href="http://www.informatika.bg/lectures/sorting" target="_blank">Сортиране</a>,
            <a href="http://www.informatika.bg/lectures/fast-sorting-algorithms" target="_blank">Бързи сортирания</a>,
            а донякъде полезна тук би ви била и <a href="http://www.informatika.bg/lectures/STL" target="_blank">STL</a>.
            За да разберете как разделяме алгоритмите на "бавни" и "бързи" е много полезна и темата за
            <a href="http://www.informatika.bg/lectures/complexity" target="_blank">Сложност на алгоритми</a>.
            <br><br>
            Макар и да има лесно за ползване ефективно сортиране в STL, част от задачите в секцията са такива, че да
            трябва сами да имплементирате логиката на някои от сортиращите алгоритми.
        ';
        $problems = '123,127,215,132,217,221,45,182,128';
        $brain->addTopic($position, $key, $link, $title, $summary, $expanded, $problems);
    }

    private function initGRDY($position) {
        $brain = new Brain();
        $key = 'GRDY';
        $link = 'greedy';
        $title = 'Greedy';
        $summary = '
            Секцията покрива задачи, които могат да бъдат решени ползвайки алчна стратегия. Тук няма много теория, а
            по-скоро практика за да почнете да "надушвате" кога може да бъде подходено по този начин.
        ';
        $expanded = '
            В тази секция ще разгледаме как алчни стратегии понякога могат да бъдат приложени в състезателни задачи.
            Ще видим няколко популярни задачи, чието решение е базирано на greedy, и няколко по-оригинални, в които
            то е ключът към решението. За този тип задачи няма много теория, която може да ви помогне - основното е
            да почнете да познавате кога алчна стратегия може да бъде приложена и кога - не. За съжаление в много
            задачи само ще <em>изглежда</em>, че тя е възможна, но реално ще има случаи, в които няма да работи
            правилно. Повечето задачи, в които това е така, се решават с Динамично Оптимиране, което ще разгледаме
            малко по-късно.
            <br><br>
            Подходяща тема, която можете да разгледате, е тази за
            <a href="http://www.informatika.bg/lectures/greedy" target="_blank">Алчни стратегии</a>.
            <br><br>
            Една от известните задачи, базирани на алчна стратегия, вече разгледахме в секцията за сортиране - това
            беше задачата <a href="/problems/182">MaxNumber</a>.
            Друга доста "стандартна" е <a href="/problems/138">Codes</a>, която с дадените ограничения може да бъде
            решена и с bruteforce, но съществува много по-ефективно алчно решение за нея. Донякъде известна задача от
            интервюта е <a href="/problems/55">Codes</a>. Останалите варират по сложност на алчното наблюдение, което
            трябва да направите - в някои то е много очевидно, докато в други съвсем не е!
        ';
        $problems = '143,165,206,170,72,58,178,190,230,138,81,120,218,223,67,208,55';
        $brain->addTopic($position, $key, $link, $title, $summary, $expanded, $problems);
    }

    private function initMATH($position) {
        $brain = new Brain();
        $key = 'MATH';
        $link = 'math';
        $title = 'Math';
        $summary = '
            Секцията покрива относително прости математически похвати, които се срещат често в състезателни задачи.
            Голяма част от тях се учат в училище и са на ниво 5-6 клас. Покрита е и малко по-сложна математика, която
            е нужна за алгоритми, които ще разгледаме по-нататък.
        ';
        $expanded = '
            В тази секция ще разгледаме относително проста теория по математика, като например какво е просто число,
            как ефективно да проверяваме дали число е просто, как бързо да намираме простите числа в интервал (решето
            на Ератостен). Накратко са покриват неща като елементарна комбинаторика, какво са факториел и пермутация,
            най-малко общо кратно и най-голям общ делител. Ако не сте запознати, добре е да видите как работят двоичните
            числа и как да ги манипулирате чрез побитови операции. Тук е добре да разгледате как работи модулната
            аритметика, тъй като тя ще ви трябва за много задачи по-нататък.
            <br>
            Малко по-сложна теория включва умножение на матрици, бързо вдигане на степен (на числа и на матрици),
            деление по модул, функция на Ойлер и други. В секция по-нататък ще разгледаме и малко по-сложна математика,
            като например комбинаторика и вероятности.
            <br><br>
            Подходящи теми, които можете да прочетете, са
            <a href="http://www.informatika.bg/lectures/primes" target="_blank">Прости числа и факторизация</a>,
            <a href="http://www.informatika.bg/lectures/bitwise-operations" target="_blank">Побитови операции</a>,
            <a href="http://www.informatika.bg/lectures/modular-arithmetic" target="_blank">Модулна аритметика</a>,
            <a href="http://www.informatika.bg/lectures/fast-exponentiation" target="_blank">Бързо степенуване</a>,
            <a href="http://www.informatika.bg/lectures/gcd-and-lcm"
            target="_blank">Най-голям общ делител и най-малко общо кратно</a>,
            <a href="http://www.informatika.bg/lectures/long-numbers" target="_blank">Дълги числа</a>,
            <br><br>
            Повечето задачи в секцията се решават с елементарна математика. Изключение правят
            <a href="/problems/92">Euleonora Pt.1</a> и <a href="/problems/93">Euleonora Pt.2</a>, които изискват да
            знаете какво е <a href="https://en.wikipedia.org/wiki/Euler%27s_totient_function" target="_blank">функция
            на Ойлер</a>. Една от задачите (<a href="/problems/4">8-Bit</a>) вече разгледахме в секцията за bruteforce,
            като тук можете да пробвате да напишете значително по-ефективното (но малко по-сложно) решение, базирано на
            комбинаторика.
        ';
        $problems = '225,24,228,187,201,140,156,192,92,93,4,172';
        $brain->addTopic($position, $key, $link, $title, $summary, $expanded, $problems);
    }

    private function initDS01($position) {
        $brain = new Brain();
        $key = 'DS01';
        $link = 'simple-data-structures';
        $title = 'Simple Data Structures';
        $summary = '
            Секцията покрива най-основните структури данни: префиксен масив, динамичен масив, опашка, стек, свързан
            списък, и приоритетна опашка. Задачите тук изискват да се приложи умно една от тези стуктури за се постигне
            достатъчно ефективно решение.
        ';
        $expanded = '
            В тази секция ще разгледаме какво е структура данни и най-основните от тях: префиксен масив, динамичен
            масив (също наричан "vector"), стек, опашка, свързан списък, и приоритетна опашка. В повечето случаи няма
            да се налага да си ги пишете сами (тъй като са имплементирани в стандартните библиотеки на повечето езици).
            Все пак е хубаво да знаете как те са имплементирани и работят, както и какви са сложностите на операциите
            им, от една страна да не ги ползвате като "черна кутия", а от друга - защото в някои задачи се ползва само
            част от идеята им. Префиксният масив е значително по-рядко срещан в практическото програмиране, в следствие
            на което не е включен в стандартната библиотека. За сметка на това, както ще видим, има доста състезателни
            задачи, в които се ползва. Повечето от тези структури ще ползваме като част от алгоритми, които ще
            разгледаме по-нататък (например опашка при търсене в ширина и приоритетна опашка в алгиритъма на Дейкстра).
            <br><br>
            Подходящи теми, които можете да видите, са
            <a href="http://www.informatika.bg/lectures/data-structures" target="_blank">Структури данни</a>,
            <a href="http://www.informatika.bg/lectures/prefix-array" target="_blank">Префиксен масив</a>,
            <a href="http://www.informatika.bg/lectures/vector" target="_blank">Динамичен масив</a>,
            <a href="http://www.informatika.bg/lectures/list" target="_blank">Списък</a>,
            <a href="http://www.informatika.bg/lectures/queue" target="_blank">Опашка</a>,
            <a href="http://www.informatika.bg/lectures/stack" target="_blank">Стек</a>,
            <a href="http://www.informatika.bg/lectures/priority-queue" target="_blank">Приоритетна опашка</a>.
            Разбира се, за да не се налага да ги пишете всеки път, ще ви е полезно и да знаете как да ги ползвате
            от <a href="http://www.informatika.bg/lectures/STL" target="_blank">стандартната библиотека</a>.
            <br><br>
            Задачите в секцията са такива, че да не можете да ползвате наготово вградените в STL имплементации.
            В повечето се ползва само идея от някоя от структурите, или операция, която стандартно не се поддържа.
        ';
        $problems = '125,129,20,40,124,107';
        $brain->addTopic($position, $key, $link, $title, $summary, $expanded, $problems);
    }

    /*
    private function initXXXX($position) {
        $brain = new Brain();
        $key = 'XXXX';
        $link = 'xxxxxxxxx';
        $title = 'XXXXXXXXX';
        $summary = '
        ';
        $expanded = '
        ';
        $problems = 'XX,YYY,ZZ';
        $brain->addTopic($position, $key, $link, $title, $summary, $expanded, $problems);
    }
    */

    private function initTraining() {
        // Group D
        $this->initIMPL(1);
        $this->initCCAS(2);
        $this->initRECU(3);
        $this->initBRUT(4);
        $this->initSORT(5);

        // Group C
        $this->initGRDY(6);
        $this->initMATH(7);
        $this->initDS01(8);

        /*
        $brain->addTopic(9, 'GRPH', '114,122,115,147,151,158,177,180,12,27,99,100,104,83,87,78,121,133', 15);
        $brain->addTopic(10, 'BSTS', '117,130,141,168,226,118,28,30,60,76,185,209,22', 12);
        $brain->addTopic(11, 'DPDP', '116,142,167,174,50,33,61,85,196,198,39,212,213,224', 12);
        $brain->addTopic(12, 'BUCK', '105,68,150,162', 4);
        */

        /*
        // Group C
        $content .= $this->getTopicBoxGrdy();
        $content .= $this->getTopicBoxMath();
        $content .= $this->getTopicBoxDS01();
        $content .= $this->getTopicBoxGrph();
        $content .= $this->getTopicBoxBsts();
        $content .= $this->getTopicBoxDpdp();
        $content .= $this->getTopicBoxBuck();
        */

    }

    private function getTopicBoxGrph() {
        $key = 'GRPH';
        $title = 'Simple Graphs';
        $text = '
            Секцията покрива графи и базови алгоритми в графи: търсене в дълбочина и ширина, Дийкстра, минимално покриващо дърво,
            непресичащи се множества, топологично сортиране, и разширяване на графа.
        ';
        return $this->getTopicBox($key, $title, $text);
    }

    private function getTopicBoxBsts() {
        $key = 'BSTS';
        $title = 'Binary & Ternary Search';
        $text = '
            Секцията покрива най-популярната форма на "Разделяй и Владей": двоично търсене, а както и неговата модификация троично търсене,
            което решава малко по-сложен проблем. Най-често задачите в тази тема ще комбинират двоично или троично търсене с някакъв друг алгоритъм.
        ';
        return $this->getTopicBox($key, $title, $text);
    }

    private function getTopicBoxDpdp() {
        $key = 'DPDP';
        $title = 'Dynamic Programming';
        $text = '
            Секцията покрива една от най-фундаменталните теми в състезателното програмиране - а именно, динамично оптимиране. Тук са включени само
            стандартни динамични (едномерни, двумерни, и многомерни), без различни специфични разновидности, които ще разгледаме по-нататък.
        ';
        return $this->getTopicBox($key, $title, $text);
    }

    private function getTopicBoxBuck() {
        $key = 'BUCK';
        $title = 'Bucketing';
        $text = '
            Секцията покрива задачи, които се решават чрез разделяне на данните в определен брой купчини (наричани "buckets").
            Най-чистата версия (Counting Sort) вече трябва да сте срещали в секцията за сортиране.
        ';
        return $this->getTopicBox($key, $title, $text);
    }

    private function getSection($key) {
        $brain = new Brain();
        $submits = $brain->getAllSubmits('AC');
        $topic = $brain->getTopic($key);
        $problemIds = explode(',', $topic['problems']);

        $sectionInfo = inBox('<h1>' . $topic['title'] . '</h1>' . $topic['expanded']);
        $sectionProblems = '';
        foreach ($problemIds as $problemId) {
            $problemInfo = $brain->getProblem($problemId);
            $problemSubmits = $brain->getProblemSubmits($problemId, $GLOBALS['STATUS_ACCEPTED']);
            $sectionProblems .= ProblemsPage::getProblemBox($problemInfo, $problemSubmits);
        }
        return $sectionInfo . $sectionProblems;
    }

    private function getTopicStats($key) {
        $brain = new Brain();
        $topic = $brain->getTopic($key);
        $problems = explode(',', $topic['problems']);
        $solved = $brain->getSolved($this->user->id);
        $accepted = array_filter($solved, function($el) use ($problems) {return in_array($el, $problems);});
        $parentId = 'topic-stats-' . $key;
        return '<script>circularProgress(\'' . $parentId . '\', ' . count($accepted) . ', ' . count($problems) . ');</script>';
    }

    private function getTopicBox($topic) {
        $box = inBox('
            <div class="training-list-entry">
                <div class="training-list-text">
                    <h2>' . $topic['title'] . '</h2>
                    ' . $topic['summary'] . '
                </div>
                <div class="training-list-progress" id="topic-stats-' . $topic['key'] . '">
                    ' . $this->getTopicStats($topic['key']) . '
                </div>
            </div>
        ');
        return '<a href="/training/' . $topic['link'] . '" class="decorated">' . $box . '</a>';
    }

    private function getTopics() {
        $brain = new Brain();
        $topics = $brain->getTrainingTopics();

        $content = '';
        foreach ($topics as $topic) {
            $content .= $this->getTopicBox($topic);
        }
        return $content;
    }

    private function getMainInfo() {
        return inBox('
            <h1>Подготовка</h1>
            Тук можете да навлезете в света на състезателната информатика като учите и тренирате върху задачи, групирани
            по теми, в нарастваща сложност. Темите са така подредени, че да изискват само материал, който е покрит в
            по-предни. Разбира се, очаква се да можете да владеете основи на програмирането (на C++, Java, или Python),
            което включва как да стартирате програма, вход и изход, типове данни, променливи, масиви, условни оператори,
            и цикли.
            <br><br>
            В случай, че сте състезател или подготвяте състезатели, ориентировъчно темите са подходящи за следните групи:
            <ul>
                <li>
                    <a href="/training/implementation">Implementation</a>,
                    <a href="/training/corner-cases">Corner Cases</a>,
                    <a href="/training/recursion-and-backtrack">Recursion & Backtrack</a>,
                    <a href="/training/bruteforce">Bruteforce</a>, и
                    <a href="/training/sorting">Sorting</a>
                    са подходящи за ученици от D група и нагоре.
                </li>
                <li>
                    <a href="/training/greedy">Greedy</a>,
                    <a href="/training/math">Math</a>,
                    <a href="/training/simple-data-structures">Simple Data Structures</a>,
                    <a href="/training/graphs">Simple Graphs</a>,
                    <a href="/training/binary-and-ternary-search">Binary/Ternary Search</a>,
                    <a href="/training/dynamic-programming">Dynamic Programming</a>,
                    <a href="/training/bucketing">Bucketing</a>
                    са подходящи за ученици от C група и нагоре.
                </li>
                <li>
                    <a href="/training/bitmask-dp">Bitmask DP</a>,
                    <a href="/training/sliding-window">Sliding Window</a>,
                    <a href="/training/iterative-dp">Iterative DP</a>,
                    <a href="/training/game-theory">Game Theory</a>,
                    <a href="/training/advanced-data-structures">Advanced Data Structures</a>,
                    <a href="/training/strings">Strings</a>,
                    <a href="/training/geometry">Geometry</a>, и
                    <a href="/training/advanced-graphs">Medium Graphs</a>
                    са подходящи за ученици от B група и нагоре.
                </li>
                <li>
                    <a href="/training/meet-in-the-middle">Meet-in-the-Middle</a>,
                    <a href="/training/probability">Probability</a>,
                    <a href="/training/inner-cycle-optimization">Inner Cycle Optimization</a>,
                    <a href="/training/sweep-line">Sweep Line</a>,
                    <a href="/training/advanced-dp">Advanced DP</a>, и
                    <a href="/training/advanced-graphs">Advanced Graphs</a>
                    са подходящи за ученици от А група.
                </li>
                <li>
                    <a href="/training/various">Various</a> задачите са предимно Ad-hoc или такива, съчетаващи няколко
                    различни теми. Сложността им е доста варираща.
                </li>
            </ul>
        ');
    }

    public function getContent() {
        $this->initTraining();

        $content = '';
        if (!isset($_GET['section'])) {
            $content .= $this->getMainInfo();
            $content .= $this->getTopics();
        } else {
            $content .= $this->getSection($_GET['section']);
        }
        return $content;
    }
    
}

?>