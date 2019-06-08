<?php
require_once(__DIR__ . '/../common.php');
require_once(__DIR__ . '/../page.php');
require_once(__DIR__ . '/../entities/problem.php');
require_once(__DIR__ . '/../entities/submit.php');

class AdminProblemsPage extends Page {
    public function getTitle() {
        return 'O(N)::Admin - Problems';
    }

    public function getExtraScripts() {
        return array('/scripts/admin.js');
    }

    private function getProblemList() {
        $brain = new Brain();
        $problemsInfo = array_merge($brain->getAllGames(), $brain->getAllProblems());

        $problems = '';
        foreach ($problemsInfo as $problemInfo) {
            $problems .= '
                <a href="/admin/problems/' . $problemInfo['id'] . '" class="decorated">
                    <div class="box narrow boxlink">
                        <div class="problem-name">' . $problemInfo['name'] . '</div>
                        <div class="problem-stats" style="font-size: 0.875rem;">
                            Добавена от: <strong>' . $problemInfo['addedBy'] . '</strong><br>
                        </div>
                    </div>
                </a>
            ';
        }
        return $problems;
    }

    private function getEditProblemScript($problem) {
        $brain = new Brain();
        $editProblemScript = '<script>';

        // Tests
        $tests = $brain->getProblemTests($problem->id);
        for ($i = 0; $i < count($tests); $i = $i + 1) {
            $inpPath = sprintf("%s/%s/%s/%s",
                    $GLOBALS['PATH_PROBLEMS'], $problem->folder, $GLOBALS['PROBLEM_TESTS_FOLDER'], $tests[$i]['inpFile']);
            $solPath = sprintf("%s/%s/%s/%s",
                    $GLOBALS['PATH_PROBLEMS'], $problem->folder, $GLOBALS['PROBLEM_TESTS_FOLDER'], $tests[$i]['solFile']);

            // Since this is a link, make it only a relative path (do not include /home/user/...)
            $inpPath = explode($_SERVER['DOCUMENT_ROOT'], $inpPath)[1];
            $solPath = explode($_SERVER['DOCUMENT_ROOT'], $solPath)[1];

            $editProblemScript .= '
                tests.push({
                    \'inpFile\': \'' . $tests[$i]['inpFile'] . '\',
                    \'inpHash\': \'' . $tests[$i]['inpHash'] . '\',
                    \'inpPath\': \'' . $inpPath . '\',
                    \'solFile\': \'' . $tests[$i]['solFile'] . '\',
                    \'solHash\': \'' . $tests[$i]['solHash'] . '\',
                    \'solPath\': \'' . $solPath . '\',
                    \'score\': ' . $tests[$i]['score'] . ',
                    \'position\': ' . $tests[$i]['position'] . '
                });
            ';
        }
        $editProblemScript .= 'updateTestTable();';

        // Solutions
        $solutions = $brain->getProblemSolutions($problem->id);

        for ($i = 0; $i < count($solutions); $i = $i + 1) {
            $submit = Submit::get(intval($solutions[$i]['submitId']));

            $sourcePath = sprintf("%s/%s/%s/%s",
                    $GLOBALS['PATH_PROBLEMS'], $problem->folder, $GLOBALS['PROBLEM_SOLUTIONS_FOLDER'], $solutions[$i]['name']);
            // Since this is a link, make it only a relative path (do not include /home/user/...)
            $sourcePath = explode($_SERVER['DOCUMENT_ROOT'], $sourcePath)[1];
            $editProblemScript .= '
                solutions.push({
                    \'name\': \'' . $solutions[$i]['name'] . '\',
                    \'submitId\': ' . $solutions[$i]['submitId'] . ',
                    \'path\': \'' . $sourcePath . '\',
                    \'time\': \'' . sprintf("%.2fs", max($submit->exec_time)) . '\',
                    \'memory\': \'' . sprintf("%.2f MiB", max($submit->exec_memory)) . '\',
                    \'score\': ' . $submit->calcScore() . ',
                    \'status\': \'' . $submit->calcStatus() . '\'
                });
            ';
        }
        $editProblemScript .= 'updateSolutionsTable();';

        $editProblemScript .= '</script>';
        return $editProblemScript;
    }

    private function getToggleOptionsSection($problem) {
        $visOn = $problem->visible ? 'inline' : 'none';
        $visOff = $problem->visible ? 'none' : 'inline';
        return '
            <div class="edit-problem-section-field">
            <fieldset>
                <legend><b>Активация</b></legend>
                <table style="width: 100%;">
                    <tbody>
                        <tr>
                            <td>
                                <div id="visibility-text-on" style="display: ' . $visOn . ';">Задачата ще бъде видима за потребителите.</div>
                                <div id="visibility-text-off" style="display: ' . $visOff . ';">Задачата няма да бъде видима за потребителите.</div>
                            </td>
                            <td>
                                <div class="right">
                                    <i id="visibility-toggle-on" class="fa fa-3x fa-toggle-on blue" style="cursor: pointer; display: ' . $visOn . ';" onclick="toggleVisibility();"></i>
                                    <i id="visibility-toggle-off" class="fa fa-3x fa-toggle-off gray" style="cursor: pointer; display: ' . $visOff . ';" onclick="toggleVisibility();"></i>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </fieldset>
            </div>
        ';
    }

    private function getInfoOptionsSection($problem) {
        return '
            <div class="edit-problem-section-field">
            <fieldset>
                <legend><b>Информация</b></legend>
                <table style="width: 100%;">
                    <tbody>
                        <tr>
                            <td>
                                <b>Заглавие:</b>
                                <input type="text" class="edit-problem-text-field" id="problemName" value="' . $problem->name . '" size="' . (mb_strlen($problem->name, 'UTF-8') + 1) . '">
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <b>Папка:</b>
                                <input type="text" class="edit-problem-text-field" id="problemFolder" value="' . $problem->folder . '" size="' . (mb_strlen($problem->folder, 'UTF-8') + 1) . '">
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <b>Автор:</b>
                                <input type="text" class="edit-problem-text-field" id="problemAuthor" value="' . $problem->author . '" size="' . (mb_strlen($problem->author, 'UTF-8') + 1) . '">
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <b>Източник:</b>
                                <input type="text" class="edit-problem-text-field" id="problemOrigin" value="' . $problem->origin . '" size="' . (mb_strlen($problem->origin, 'UTF-8') + 1) . '">
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <b>Сложност:</b>
                                <select name="difficulty" id="problemDifficulty">
                                    <option value="trivial"' . ($problem->difficulty == 'trivial' ? 'selected' : '') . '>Trivial</option>
                                    <option value="easy"' . ($problem->difficulty == 'easy' ? 'selected' : '') . '>Easy</option>
                                    <option value="medium"' . ($problem->difficulty == 'medium' ? 'selected' : '') . '>Medium</option>
                                    <option value="hard"' . ($problem->difficulty == 'hard' ? 'selected' : '') . '>Hard</option>
                                    <option value="brutal"' . ($problem->difficulty == 'brutal' ? 'selected' : '') . '>Brutal</option>
                                </select>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </fieldset>
            </div>
        ';
    }

    private function getControlOptionsSection($problem) {
        return '
            <div class="edit-problem-section-field">
            <fieldset>
                <legend><b>Настройки</b></legend>
                <table>
                    <tbody>
                        <tr>
                            <td>
                                <b>Максимално време за тест (s):</b>
                                <input type="text" class="edit-problem-text-field" id="problemTL" value="' . $problem->timeLimit . '" size="3">
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <b>Максимална памет за тест (MB):</b>
                                <input type="text" class="edit-problem-text-field" id="problemML" value="' . $problem->memoryLimit . '" size="3">
                            </td>
                        </tr>
                    </tbody>
                </table>
            </fieldset>
            </div>
        ';
    }

    private function getTagsOptionsSection($problem) {
        $tags = [
            'implement' => 'Implementation',
            'search' => 'Search',
            'dp' => 'Dynamic Programming',
            'graph' => 'Graphs',
            'math' => 'Math',
            'geometry' => 'Geometry',
            'ad-hoc' => 'Ad-hoc',
            'flow' => 'Flows',
            'divconq' => 'Divide & Conquer',
            'strings' => 'Strings',
            'sorting' => 'Sorting',
            'greedy' => 'Greedy',
            'game' => 'Game Theory',
            'datastruct' => 'Data Structures',
            'np' => 'NP-Complete'
        ];
        $tagsTable = '<table style="width: 100%;">';
        while ($tag = current($tags)) {
            $tagsTable .= '<tr>';
            for ($c = 0; $c < 3 && $tag = current($tags); $c = $c + 1) {
                $tagsTable .= '
                    <td>
                        <label class="checkbox-label">
                            <input type="checkbox" name="problemTags" value="' . key($tags) . '" ' .
                                (in_array(key($tags), $problem->tags) ? 'checked' : '') . '> ' . $tag . '
                        </label>
                    </td>';
                next($tags);
            }
            $tagsTable .= '</tr>
            ';
        }
        $tagsTable .= '</table>';
        return '
            <div class="edit-problem-section-field">
            <fieldset>
                <legend><b>Тагове</b></legend>
                ' . $tagsTable . '
            </fieldset>
            </div>
        ';
    }

    private function getTestingOptionsSection($problem) {
        // Checker and tester (if any)
        $checker = $problem->checker == '' ? 'N/A' : $problem->checker;
        $tester = $problem->tester == '' ? 'N/A' : $problem->tester;

        return '
            <div class="edit-problem-section-field">
            <fieldset>
                <legend><b>Тестване</b></legend>
                <table>
                    <tbody>
                        <tr>
                            <td style="width: 50%;">
                                <b>Тип:</b>
                                <select name="type" id="problemType">
                                    <option value="ioi"' . ($problem->type == 'ioi' ? 'selected' : '') . '>IOI</option>
                                    <option value="acm"' . ($problem->type == 'acm' ? 'selected' : '') . '>ACM</option>
                                    <option value="relative"' . ($problem->type == 'relative' ? 'selected' : '') . '>Relative</option>
                                    <option value="game"' . ($problem->type == 'game' ? 'selected' : '') . '>Game</option>
                                </select>
                            </td>
                            <td style="width: 30%;">
                                &nbsp;
                            </td>
                        </tr>

                        <tr>
                            <td>
                                <b>Чекер:</b>
                                <span id="checkerName">' . $checker . '</span>
                                <span style="position: relative; top: 1px;">
                                    <label class="custom-file-upload">
                                        <input type="file" id="checkerSelector" onchange="uploadChecker();" style="display:none;">
                                        <i class="fa fa-plus-circle green"></i>
                                    </label>
                                    ' . ($checker == 'N/A' ? '' : '<i class="fa fa-trash red" onclick="deleteChecker();"></i>') . '
                                </span>
                            </td>
                            <td style="width: 30%;">
                                <label class="checkbox-label">
                                    <input type="checkbox" id="floats" name="floats" value="floats" ' .
                                        ($problem->floats ? 'checked' : '') . '> ' . "Floating Point Comparison" . '
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <b>Тестер:</b>
                                <span id="testerName">' . $tester . '</span>
                                <span style="position: relative; top: 1px;">
                                    <label class="custom-file-upload">
                                        <input type="file" id="testerSelector" onchange="uploadTester();" style="display:none;">
                                        <i class="fa fa-plus-circle green"></i>
                                    </label>
                                    ' . ($tester == 'N/A' ? '' : '<i class="fa fa-trash red" onclick="deleteTester();"></i>') . '
                                </span>
                            </td>
                            <td>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </fieldset>
            </div>
        ';
    }

    private function getOptionsTab($problem) {
        return '
            <div class="edit-problem-section" id="optionsTabContent">
                ' . $this->getToggleOptionsSection($problem) . '
                <br>
                ' . $this->getInfoOptionsSection($problem) . '
                <br>
                ' . $this->getControlOptionsSection($problem) . '
                <br>
                ' . $this->getTagsOptionsSection($problem) . '
                <br>
                ' . $this->getTestingOptionsSection($problem) . '
            </div>
        ';
    }

    private function getStatementTab($problem) {
        return '
            <div id="statementTabContent" style="display: none;">
                <div class="edit-problem-section" style="margin-bottom: 4px;">
                    <div class="right" onclick="toggleStatementHTML();"><a>edit html</a>&nbsp;</div>
                </div>
                <div>
                    <div contenteditable id="editStatement">
                    ' . $problem->statement . '
                    </div>
                </div>
            </div>
        ';
    }

    private function getTestsTab($problem) {
        return '
            <div class="edit-problem-section" id="testsTabContent" style="display: none;">
                <div class="center" style="padding: 0px 8px 4px 8px;">
                    <table class="default" id="testList">
                        <thead>
                            <tr>
                                <th>Входен файл</th>
                                <th>Изходен файл</th>
                                <th>Точки</th>
                                <th>Статус</th>
                                <th><i class="fa fa-trash" onclick="deleteAllTests();" style="cursor: pointer;"></i></th>
                            </tr>
                        </thead>
                        <tbody>
                        </tbody>
                    </table>
                </div>
                <div class="center">
                    <label class="custom-file-upload">
                        <input type="file" id="testSelector" onchange="addTests();" style="display:none;" multiple>
                        <i class="fa fa-plus-circle fa-2x green"></i>
                    </label>
                </div>
            </div>
        ';
    }

    private function getSolutionsTab($problem) {
        return '
            <div class="edit-problem-section" id="solutionsTabContent" style="display: none;">
                <div class="center" style="padding: 0px 8px 4px 8px;">
                    <table class="default" id="solutionsList">
                        <thead>
                            <tr>
                                <th>Име</th>
                                <th>id</th>
                                <th>Време</th>
                                <th>Памет</th>
                                <th>Точки</th>
                                <th>Статус</th>
                                <th><i class="fa fa-sync-alt"></i></th>
                                <th><i class="fa fa-trash"></i></th>
                            </tr>
                        </thead>
                        <tbody>
                        </tbody>
                    </table>
                </div>
                <div class="center">
                    <label class="custom-file-upload">
                        <input type="file" id="solutionSelector" onchange="addSolutions();" style="display:none;" multiple>
                        <i class="fa fa-plus-circle fa-2x green"></i>
                    </label>
                </div>
            </div>
        ';
    }

    private function getEditProblemForm($problem) {
        // Header and Footer
        $headerText = $problem->id == -1 ? 'Нова задача' : '<span class="blue">' . $problem->name . '</span> :: Промяна';
        $buttonText = $problem->id == -1 ? 'Създай' : 'Запази';

        $content = '
            <div class="left">
                <h2>' . $headerText . '</h2>
            </div>
            <div class="edit-problem-tab">
                <a href="#options"><div onclick="changeTab(\'optionsTab\');" class="edit-problem-tab-button underline" id="optionsTab">Настройки</div></a> |
                <a href="#statement"><div onclick="changeTab(\'statementTab\');" class="edit-problem-tab-button" id="statementTab">Условие</div></a> |
                <a href="#tests"><div onclick="changeTab(\'testsTab\');" class="edit-problem-tab-button" id="testsTab">Тестове</div></a> |
                <a href="#solutions"><div onclick="changeTab(\'solutionsTab\');" class="edit-problem-tab-button" id="solutionsTab">Решения</div></a>
            </div>

            ' . $this->getOptionsTab($problem) . '
            ' . $this->getStatementTab($problem) . '
            ' . $this->getTestsTab($problem) . '
            ' . $this->getSolutionsTab($problem) . '

            <div class="center">
                <input type="submit" value="' . $buttonText . '" onclick="submitEditProblemForm();" class="button button-large button-color-red">
            </div>
        ';

        return $content;
    }

    public function getContent() {
        // Default page listing all problems
        $content = inBox('
            <h1>Админ::Задачи</h1>

            <div class="centered">
                <input type="submit" value="Нова задача" onclick="redirect(\'problems/new\');" class="button button-large button-color-blue">
            </div>
        ');
        $content .= $this->getProblemList();

        // Specific problem is open
        if (isset($_GET['problemId'])) {
            if ($_GET['problemId'] == 'new') {
                $problem = new Problem();
            } else {
                $problem = Problem::get($_GET['problemId']);
            }
            if ($problem == null) {
                $content .= showMessage('ERROR', 'Не съществува задача с този идентификатор!');
            }

            $redirect = '/admin/problems';
            $content .= '
                <script>
                    showEditProblemForm(`' . $this->getEditProblemForm($problem) . '`, `' . $redirect . '`);
                    var anchor = (document.URL.split(\'#\').length > 1) ? document.URL.split(\'#\')[1] : \'\';
                    if (anchor === \'options\') changeTab(\'optionsTab\');
                    if (anchor === \'statement\') changeTab(\'statementTab\');
                    if (anchor === \'tests\') changeTab(\'testsTab\');
                    if (anchor === \'solutions\') changeTab(\'solutionsTab\');
                </script>
            ';
            $content .= $this->getEditProblemScript($problem);
        }

        return $content;
    }
}

?>