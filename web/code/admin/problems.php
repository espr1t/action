<?php
require_once(__DIR__ . '/../common.php');
require_once(__DIR__ . '/../page.php');
require_once(__DIR__ . '/../entities/problem.php');

class AdminProblemsPage extends Page {
    public function getTitle() {
        return 'O(N)::Admin - Problems';
    }

    public function getExtraScripts() {
        return array('/scripts/admin.js');
    }

    private function getProblemList() {
        $brain = new Brain();
        $problemsInfo = $brain->getAllProblems();

        $problems = '';
        foreach ($problemsInfo as $problemInfo) {
            $problems .= '
                <a href="/admin/problems/' . $problemInfo['id'] . '" class="decorated">
                    <div class="box narrow boxlink">
                        <div class="problem-name">' . $problemInfo['name'] . '</div>
                        <div class="problem-solutions" style="font-size: 0.875rem; width: 14rem;">
                            Добавена от: <strong>' . $problemInfo['addedBy'] . '</strong><br>
                        </div>
                    </div>
                </a>
            ';
        }
        return $problems;
    }

    private function getTagsTable($problem) {
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
        $table = '<table style="width: 100%;">';
        while ($tag = current($tags)) {
            $table .= '<tr>';
            for ($c = 0; $c < 3 && $tag = current($tags); $c = $c + 1) {
                $table .= '
                    <td>
                        <label class="checkbox-label">
                            <input type="checkbox" name="problemTags" value="' . key($tags) . '" ' .
                                (in_array(key($tags), $problem->tags) ? 'checked' : '') . '> ' . $tag . '
                        </label>
                    </td>';
                next($tags);
            }
            $table .= '</tr>
            ';
        }
        $table .= '</table>';
        return $table;
    }

    private function getEditProblemScript($problem) {
        $brain = new Brain();
        $tests = $brain->getProblemTests($problem->id);

        $editProblemScript = '<script>';
        for ($i = 0; $i < count($tests); $i = $i + 1) {
            $inpPath = sprintf("%s/%s/%s/%s",
                    $GLOBALS['PATH_PROBLEMS'], $problem->folder, $GLOBALS['PROBLEM_TESTS_FOLDER'], $tests[$i]['inpFile']);
            $solPath = sprintf("%s/%s/%s/%s",
                    $GLOBALS['PATH_PROBLEMS'], $problem->folder, $GLOBALS['PROBLEM_TESTS_FOLDER'], $tests[$i]['solFile']);

            # Since this is a link, make it only a relative path (do not include /home/user/...)
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
        $editProblemScript .= '</script>';
        return $editProblemScript;
    }

    private function getEditProblemForm($problem) {
        // Header and Footer
        $headerText = $problem->id == -1 ? 'Нова задача' : '<span class="blue">' . $problem->name . '</span> :: Промяна';
        $buttonText = $problem->id == -1 ? 'Създай' : 'Запази';

        // Tags
        $tagsTable = $this->getTagsTable($problem);

        $content = '
            <div class="left">
                <h2>' . $headerText . '</h2>
            </div>
            <div class="edit-problem-tab">
                <div onclick="changeTab(\'statementTab\');" class="edit-problem-tab-button underline" id="statementTab">Условие</div> |
                <div onclick="changeTab(\'optionsTab\');" class="edit-problem-tab-button" id="optionsTab">Настройки</div> |
                <div onclick="changeTab(\'testsTab\');" class="edit-problem-tab-button" id="testsTab">Тестове</div>
            </div>

            <div id="statementTabContent">
                <div class="edit-problem-section" style="margin-bottom: 4px;">
                    <div class="right" onclick="toggleStatementHTML();"><a>edit html</a>&nbsp;</div>
                </div>
                <div>
                    <div contenteditable id="statement">
                    ' . $problem->statement . '
                    </div>
                </div>
            </div>

            <div class="edit-problem-section" id="optionsTabContent" style="display: none;">
                <div class="edit-problem-section-field">
                    <b>Заглавие:</b>
                    <input type="text" class="edit-problem-text-field" id="problemName" value="' . $problem->name . '" size="' . (mb_strlen($problem->name, 'UTF-8') + 1) . '">
                </div>
                <div class="edit-problem-section-field">
                    <b>Папка:</b>
                    <input type="text" class="edit-problem-text-field" id="problemFolder" value="' . $problem->folder . '" size="' . (mb_strlen($problem->folder, 'UTF-8') + 1) . '">
                </div>
                <br>
                <div class="edit-problem-section-field">
                    <b>Автор:</b>
                    <input type="text" class="edit-problem-text-field" id="problemAuthor" value="' . $problem->author . '" size="' . (mb_strlen($problem->author, 'UTF-8') + 1) . '">
                </div>
                <div class="edit-problem-section-field">
                    <b>Източник:</b>
                    <input type="text" class="edit-problem-text-field" id="problemOrigin" value="' . $problem->origin . '" size="' . (mb_strlen($problem->origin, 'UTF-8') + 1) . '">
                </div>
                <br>
                <div class="edit-problem-section-field">
                    <b>Максимално време за тест (s):</b>
                    <input type="text" class="edit-problem-text-field" id="problemTL" value="' . $problem->timeLimit . '" size="3">
                </div>
                <div class="edit-problem-section-field">
                    <b>Максимална памет за тест (MB):</b>
                    <input type="text" class="edit-problem-text-field" id="problemML" value="' . $problem->memoryLimit . '" size="3">
                </div>
                <br>
                <div class="edit-problem-section-field">
                    <b>Тип:</b>
                        <select name="type" id="problemType">
                            <option value="ioi"' . ($problem->type == 'ioi' ? 'selected' : '') . '>IOI</option>
                            <option value="acm"' . ($problem->type == 'acm' ? 'selected' : '') . '>ACM</option>
                            <option value="relative"' . ($problem->type == 'relative' ? 'selected' : '') . '>Relative</option>
                            <option value="game"' . ($problem->type == 'game' ? 'selected' : '') . '>Game</option>
                        </select>
                </div>
                <br>
                <div class="edit-problem-section-field">
                    <b>Сложност:</b>
                        <select name="difficulty" id="problemDifficulty">
                            <option value="trivial"' . ($problem->difficulty == 'trivial' ? 'selected' : '') . '>Trivial</option>
                            <option value="easy"' . ($problem->difficulty == 'easy' ? 'selected' : '') . '>Easy</option>
                            <option value="medium"' . ($problem->difficulty == 'medium' ? 'selected' : '') . '>Medium</option>
                            <option value="hard"' . ($problem->difficulty == 'hard' ? 'selected' : '') . '>Hard</option>
                            <option value="brutal"' . ($problem->difficulty == 'brutal' ? 'selected' : '') . '>Brutal</option>
                        </select>
                </div>
                <br>
                <div class="edit-problem-section-field">
                <fieldset>
                    <legend><b>Тагове</b></legend>
                    ' . $tagsTable . '
                </fieldset>
                </div>
                <br>
                <div class="edit-problem-section-field">
                    <b>Чекер:</b>
                    <input type="file" id="checkerSelector" onchange="updateChecker();">
                </div>
                <br>
                <div class="edit-problem-section-field">
                    <b>Тестер:</b>
                    <input type="file" id="testerSelector" onchange="updateTester();">
                </div>
            </div>

            <div class="edit-problem-section" id="testsTabContent" style="display: none;">
                <div class="center" style="padding: 0px 8px 4px 8px;">
                    <table class="default" id="testList">
                        <thead>
                            <tr>
                                <th>Входен файл</th><th>Изходен файл</th><th>Точки</th><th>Статус</th><th style="min-width: 6px;"></th>
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
                </script>
            ';
            $content .= $this->getEditProblemScript($problem);
        }

        return $content;
    }
}

?>