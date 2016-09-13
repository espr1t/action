<?php
require_once('../logic/problem.php');
require_once('../common.php');
require_once('../page.php');

class AdminProblemsPage extends Page {
    public function getTitle() {
        return 'O(N)::Admin';
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
                        <div class="problem-info">
                            <div class="right">
                                Добавена от: <strong>' . $problemInfo['addedBy'] . '</strong><br>
                            </div>
                        </div>
                    </div>
                </a>
            ';
        }
        return $problems;
    }

    private function getEditProblemForm($problemId) {
        $brain = new Brain();
        if ($problemId == 'new') {
            $problem = new Problem();
            $statementPath = sprintf("%s/%s", $GLOBALS['PATH_PROBLEMS'], $GLOBALS['PROBLEM_STATEMENT_FILENAME']);
            $statementHTML = file_get_contents($statementPath);
        } else {
            $problem = Problem::get($problemId);
            $statementPath = sprintf("%s/%s/%s", $GLOBALS['PATH_PROBLEMS'], $problem->folder, $GLOBALS['PROBLEM_STATEMENT_FILENAME']);
            $statementHTML = file_get_contents($statementPath);
        }
        $tests = $brain->getProblemTests($problem->id);

        $initTestsRows = '';
        for ($i = 0; $i < count($tests); $i = $i + 1) {
            $initTestsRows .= '
                <tr>
                    <td>' . $tests[$i]['inpFile'] . '<div class="edit-problem-test-hash">' . $tests[$i]['inpHash'] . '</div></td>
                    <td>' . $tests[$i]['solFile'] . '<div class="edit-problem-test-hash">' . $tests[$i]['solHash'] . '</div></td>
                    <td contenteditable="true">' . $tests[$i]['score'] . '</td>
                    <td><i class="fa fa-check green"></i></td>
                </tr>
            ';
        }


        $content = '
            <div class="left">
                <h2><span class="blue">' . $problem->name . '</span> :: Промяна</h2>
            </div>
            <div class="edit-problem-section-title">
                Настройки
            </div>
            <div class="edit-problem-section">
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
                    <table style="width: 100%;">
                        <tr>
                            <td><label class="checkbox-label"><input type="checkbox" name="animal" value="implement"> Implementation</label></td>
                            <td><label class="checkbox-label"><input type="checkbox" name="animal" value="search"> Search</label></td>
                            <td><label class="checkbox-label"><input type="checkbox" name="animal" value="dp"> DP</label></td>
                        </tr>
                        <tr>
                            <td><label class="checkbox-label"><input type="checkbox" name="animal" value="graph"> Graphs</label></td>
                            <td><label class="checkbox-label"><input type="checkbox" name="animal" value="math"> Math</label></td>
                            <td><label class="checkbox-label"><input type="checkbox" name="animal" value="geometry"> Geometry</label></td>
                        </tr>
                        <tr>
                            <td><label class="checkbox-label"><input type="checkbox" name="animal" value="ad-hoc"> Ad-hoc</label></td>
                            <td><label class="checkbox-label"><input type="checkbox" name="animal" value="flow"> Flow</label></td>
                            <td><label class="checkbox-label"><input type="checkbox" name="animal" value="divconq"> Divide & Conquer</label></td>
                        </tr>
                        <tr>
                            <td><label class="checkbox-label"><input type="checkbox" name="animal" value="bsearch"> Binary Search</label></td>
                            <td><label class="checkbox-label"><input type="checkbox" name="animal" value="hashing"> Hashing</label></td>
                            <td><label class="checkbox-label"><input type="checkbox" name="animal" value="strings"> Strings</label></td>
                        </tr>
                        <tr>
                            <td><label class="checkbox-label"><input type="checkbox" name="animal" value="sorting"> Sorting</label></td>
                            <td><label class="checkbox-label"><input type="checkbox" name="animal" value="greedy"> Greedy</label></td>
                            <td><label class="checkbox-label"><input type="checkbox" name="animal" value="sg"> Game Theory</label></td>
                        </tr>
                        <tr>
                            <td><label class="checkbox-label"><input type="checkbox" name="animal" value="mitm"> Meet in the Middle</label></td>
                            <td><label class="checkbox-label"><input type="checkbox" name="animal" value="datastruct"> Data Structures</label></td>
                            <td><label class="checkbox-label"><input type="checkbox" name="animal" value="stl"> NP</label></td>
                        </tr>
                    </table>
                </fieldset>
                </div>
            </div>

            <div class="edit-problem-section-title">
                Условие
            </div>
            <div class="right" onclick="toggleStatementHTML();"><a class="fa fa-code"></a></div>
            <div>
                <div contenteditable id="statement">
                ' . $statementHTML . '
                </div>
            </div>

            <div class="edit-problem-section-title">
                Тестове
            </div>
            <div class="edit-problem-section">
                <div class="center" style="padding: 0px 8px 4px 8px;">
                    <table class="default" id="testList">
                        <thead>
                            <tr>
                                <th>Входен файл</th><th>Изходен файл</th><th>Точки</th><th>Статус</th>
                            </tr>
                        </thead>
                        <tbody>
                        ' . $initTestsRows . '
                        </tbody>
                    </table>
                </div>
                <div class="center">
                    <label class="custom-file-upload">
                        <input type="file" id="testSelector" onchange="updateTests();" style="display:none;" multiple>
                        <i class="fa fa-plus-circle fa-2x green"></i>
                    </label>
                </div>
            </div>
            <div class="edit-problem-section-title">
                Допълнителни
            </div>
            <div class="edit-problem-section">
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

            <div class="center" style="margin-top: 12px;">
                <input type="submit" value="Запази" onclick="submitEditProblemForm();" class="button button-color-red button-large">
            </div>
        ';

        return $content;
    }
    
    public function getContent() {
        // Default page listing all problems
        $content = inBox('
            <h1>Админ::Задачи</h1>

            <div class="problem-submit">
                <input type="submit" value="Нова задача" onclick="window.location.href=\'problems/new\';" class="button button-color-blue button-large">
            </div>
        ');
        $content .= $this->getProblemList();

        // Specific problem is open
        if (isset($_GET['problem'])) {
            $redirect = '/admin/problems';
            $content .= '
                <script>
                    showEditProblemForm(`' . $this->getEditProblemForm($_GET['problem']) . '`, `' . $redirect . '`);
                </script>
            ';
        }

        return $content;
    }
}

?>