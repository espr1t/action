<?php
require_once(__DIR__ . "/../common.php");
require_once(__DIR__ . "/../page.php");
require_once(__DIR__ . "/../entities/problem.php");
require_once(__DIR__ . "/../entities/submit.php");
require_once(__DIR__ . "/../entities/solution.php");

class AdminProblemsPage extends Page {
    public function getTitle(): string {
        return "O(N)::Admin - Problems";
    }

    public function getExtraScripts(): array {
        return array("/scripts/admin.js");
    }

    private function getProblemsList(): string {
        $problems = array_merge(Problem::getAllGames(), Problem::getAllTasks());

        $problemsList = "";
        foreach ($problems as $problem) {
            $problemsList .= "
                <a href='/admin/problems/{$problem->getId()}' class='decorated'>
                    <div class='box narrow boxlink'>
                        <div class='problem-name'>{$problem->getName()}</div>
                        <div class='problem-stats' style='font-size: 0.875rem;'>
                            Добавена от: <strong>{$problem->getAddedBy()}</strong><br>
                        </div>
                    </div>
                </a>
            ";
        }
        return $problemsList;
    }

    private function getEditProblemScript(Problem $problem): string {
        $editProblemScript = "";

        // Tests
        $tests = Test::getProblemTests($problem->getId());
        foreach ($tests as $test) {
            $inpPath = "{$problem->getTestsPath()}/{$test->getInpFile()}";
            $solPath = "{$problem->getTestsPath()}/{$test->getSolFile()}";

            // Since this is a link, make it only a relative path (do not include /home/user/...)
            $inpPath = explode($_SERVER["DOCUMENT_ROOT"], $inpPath)[1];
            $solPath = explode($_SERVER["DOCUMENT_ROOT"], $solPath)[1];

            $editProblemScript .= "
                tests.push({
                    'inpFile': '{$test->getInpFile()}',
                    'inpHash': '{$test->getInpHash()}',
                    'inpPath': '{$inpPath}',
                    'solFile': '{$test->getSolFile()}',
                    'solHash': '{$test->getSolHash()}',
                    'solPath': '{$solPath}',
                    'score': {$test->getScore()},
                    'position': {$test->getPosition()}
                });
            ";
        }
        $editProblemScript .= "updateTestTable();";

        // Solutions
        $solutions = Solution::getProblemSolutions($problem->getId());
        foreach ($solutions as $solution) {
            $submit = Submit::get(intval($solution->getSubmitId()));

            // Get the path to the source, so we can store it on the host as well
            $sourcePath = "{$problem->getSolutionsPath()}/{$solution->getName()}";
            // Since this is a link, make it only a relative path (do not include /home/user/...)
            $sourcePath = explode($_SERVER["DOCUMENT_ROOT"], $sourcePath)[1];

            $maxExecTime = sprintf("%.2fs", max($submit->getExecTime()));
            $maxExecMemory = sprintf("%.2f MiB", max($submit->getExecMemory()));
            $editProblemScript .= "
                solutions.push({
                    'name': '{$solution->getName()}',
                    'submitId': {$solution->getSubmitId()},
                    'path': '{$sourcePath}',
                    'time': '{$maxExecTime}',
                    'memory': '{$maxExecMemory}',
                    'score': {$submit->calcScore()},
                    'status': '{$submit->calcStatus()}'
                });
            ";
        }
        $editProblemScript .= "updateSolutionsTable();";

        return "<script>{$editProblemScript}</script>";
    }

    private function getToggleOptionsSection(Problem $problem): string {
        $visOn = $problem->getVisible() ? "inline" : "none";
        $visOff = $problem->getVisible() ? "none" : "inline";
        return "
            <div class='edit-problem-section-field'>
            <fieldset>
                <legend><b>Активация</b></legend>
                <table style='width: 100%;'>
                    <tbody>
                        <tr>
                            <td>
                                <div id='visibility-text-on' style='display: {$visOn};'>Задачата ще бъде видима за потребителите.</div>
                                <div id='visibility-text-off' style='display: {$visOff};'>Задачата няма да бъде видима за потребителите.</div>
                            </td>
                            <td>
                                <div class='right'>
                                    <i id='visibility-toggle-on' class='fa fa-3x fa-toggle-on blue' style='cursor: pointer; display: {$visOn};' onclick='toggleVisibility();'></i>
                                    <i id='visibility-toggle-off' class='fa fa-3x fa-toggle-off gray' style='cursor: pointer; display: {$visOff};' onclick='toggleVisibility();'></i>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </fieldset>
            </div>
        ";
    }

    private function getInfoOptionsSection(Problem $problem): string {
        return "
            <div class='edit-problem-section-field'>
            <fieldset>
                <legend><b>Информация</b></legend>
                <table style='width: 100%;'>
                    <tbody>
                        <tr>
                            <td>
                                <b>Заглавие:</b>
                                <input type='text' class='edit-problem-text-field' id='problemName' value='{$problem->getName()}' size='" . (mb_strlen($problem->getName(), "UTF-8") + 1) . "'>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <b>Папка:</b>
                                <input type='text' class='edit-problem-text-field' id='problemFolder' value='{$problem->getFolder()}' size='" . (mb_strlen($problem->getFolder(), "UTF-8") + 1) . "'>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <b>Автор:</b>
                                <input type='text' class='edit-problem-text-field' id='problemAuthor' value='{$problem->getAuthor()}' size='" . (mb_strlen($problem->getAuthor(), "UTF-8") + 1) . "'>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <b>Източник:</b>
                                <input type='text' class='edit-problem-text-field' id='problemOrigin' value='{$problem->getOrigin()}' size='" . (mb_strlen($problem->getOrigin(), "UTF-8") + 1) . "'>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <b>Сложност:</b>
                                <select name='difficulty' id='problemDifficulty'>
                                    <option value='trivial' " . ($problem->getDifficulty() == "trivial" ? "selected" : "") . ">Trivial</option>
                                    <option value='easy' " . ($problem->getDifficulty() == "easy" ? "selected" : "") . ">Easy</option>
                                    <option value='medium' " . ($problem->getDifficulty() == "medium" ? "selected" : "") . ">Medium</option>
                                    <option value='hard' " . ($problem->getDifficulty() == "hard" ? "selected" : "") . ">Hard</option>
                                    <option value='brutal' " . ($problem->getDifficulty() == "brutal" ? "selected" : "") . ">Brutal</option>
                                </select>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </fieldset>
            </div>
        ";
    }

    private function getControlOptionsSection(Problem $problem): string {
        return "
            <div class='edit-problem-section-field'>
            <fieldset>
                <legend><b>Настройки</b></legend>
                <table>
                    <tbody>
                        <tr>
                            <td>
                                <b>Максимално време за тест (s):</b>
                                <input type='text' class='edit-problem-text-field' id='problemTL' value='{$problem->getTimeLimit()}' size='3'>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <b>Максимална памет за тест (MB):</b>
                                <input type='text' class='edit-problem-text-field' id='problemML' value='{$problem->getMemoryLimit()}' size='3'>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </fieldset>
            </div>
        ";
    }

    private function getTagsOptionsSection(Problem $problem): string {
        $tags = $GLOBALS["PROBLEM_TAGS"];
        $tagsTableContent = "";
        while (current($tags)) {
            $tagsTableContent .= "<tr>";
            for ($i = 0; $i < 3 && $tag = current($tags); $i++) {
                $hasTag = $problem->getTags() != null && in_array(key($tags), $problem->getTags());
                $tagsTableContent .= "
                    <td>
                        <label class='checkbox-label'>
                            <input type='checkbox' name='problemTags' value='" . key($tags) . "' " .
                                ($hasTag ? "checked" : "") . "> {$tag}
                        </label>
                    </td>";
                next($tags);
            }
            $tagsTableContent .= "</tr>";
        }
        return "
            <div class='edit-problem-section-field'>
            <fieldset>
                <legend><b>Тагове</b></legend>
                <table style='width: 100%;'>
                    {$tagsTableContent}
                </table>
            </fieldset>
            </div>
        ";
    }

    private function getTestingOptionsSection(Problem $problem): string {
        // Checker and tester (if any)
        $checker = !$problem->getChecker() ? "N/A" : $problem->getChecker();
        $tester = !$problem->getTester() ? "N/A" : $problem->getTester();

        return "
            <div class='edit-problem-section-field'>
            <fieldset>
                <legend><b>Тестване</b></legend>
                <table>
                    <tbody>
                        <tr>
                            <td style='width: 50%;'>
                                <b>Тип:</b>
                                <select name='type' id='problemType'>
                                    <option value='ioi' " . ($problem->getType() == "ioi" ? "selected" : "") . ">IOI</option>
                                    <option value='acm' " . ($problem->getType() == "acm" ? "selected" : "") . ">ACM</option>
                                    <option value='relative' " . ($problem->getType() == "relative" ? "selected" : "") . ">Relative</option>
                                    <option value='game' " . ($problem->getType() == "game" ? "selected" : "") . ">Game</option>
                                    <option value='interactive' " . ($problem->getType() == "interactive" ? "selected" : "") . ">Interactive</option>
                                </select>
                            </td>
                            <td style='width: 30%;'>
                                &nbsp;
                            </td>
                        </tr>

                        <tr>
                            <td>
                                <b>Чекер:</b>
                                <span id='checkerName'>{$checker}</span>
                                <span style='position: relative; top: 1px;'>
                                    <label class='custom-file-upload'>
                                        <input type='file' id='checkerSelector' onchange='uploadChecker();' style='display: none;'>
                                        <i class='fa fa-plus-circle green'></i>
                                    </label>
                                    " . ($checker == "N/A" ? "" : "<i class='fa fa-trash red' onclick='deleteChecker();'></i>") . "
                                </span>
                            </td>
                            <td style='width: 30%;'>
                                <label class='checkbox-label'>
                                    <input type='checkbox' id='floats' name='floats' value='floats' " .
                                        ($problem->getFloats() ? "checked" : "") . "> Floating Point Comparison
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <b>Тестер:</b>
                                <span id='testerName'>{$tester}</span>
                                <span style='position: relative; top: 1px;'>
                                    <label class='custom-file-upload'>
                                        <input type='file' id='testerSelector' onchange='uploadTester();' style='display: none;'>
                                        <i class='fa fa-plus-circle green'></i>
                                    </label>
                                    " . ($tester == "N/A" ? "" : "<i class='fa fa-trash red' onclick='deleteTester();'></i>") . "
                                </span>
                            </td>
                            <td>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </fieldset>
            </div>
        ";
    }

    private function getOptionsTab(Problem $problem): string {
        return "
            <div class='edit-problem-section' id='optionsTabContent'>
                {$this->getToggleOptionsSection($problem)}
                <br>
                {$this->getInfoOptionsSection($problem)}
                <br>
                {$this->getControlOptionsSection($problem)}
                <br>
                {$this->getTagsOptionsSection($problem)}
                <br>
                {$this->getTestingOptionsSection($problem)}
            </div>
        ";
    }

    private function getStatementTab(Problem $problem): string {
        return "
            <div id='statementTabContent' style='display: none;'>
                <div class='edit-problem-section' style='margin-bottom: 0.25rem;'>
                    <div class='right' onclick='toggleStatementHTML();'><a>edit html</a>&nbsp;</div>
                </div>
                <div>
                    <div contenteditable id='editStatement'>
                    {$problem->getStatement()}
                    </div>
                </div>
            </div>
        ";
    }

    private function getTestsTab(): string {
        return "
            <div class='edit-problem-section' id='testsTabContent' style='display: none;'>
                <div class='center' style='padding: 0rem 0.5rem 0.25rem 0.5rem;'>
                    <table class='default' id='testList'>
                        <thead>
                            <tr>
                                <th>Входен файл</th>
                                <th>Изходен файл</th>
                                <th>Точки</th>
                                <th>Статус</th>
                                <th><i class='fa fa-trash' onclick='deleteAllTests();' style='cursor: pointer;'></i></th>
                            </tr>
                        </thead>
                        <tbody>
                        </tbody>
                    </table>
                </div>
                <div class='center'>
                    <label class='custom-file-upload'>
                        <input type='file' id='testSelector' onchange='addTests();' style='display: none;' multiple>
                        <i class='fa fa-plus-circle fa-2x green'></i>
                    </label>
                </div>
            </div>
        ";
    }

    private function getSolutionsTab(): string {
        return "
            <div class='edit-problem-section' id='solutionsTabContent' style='display: none;'>
                <div class='center' style='padding: 0rem 0.5rem 0.25rem 0.5rem;'>
                    <table class='default' id='solutionsList'>
                        <thead>
                            <tr>
                                <th>Име</th>
                                <th>id</th>
                                <th>Време</th>
                                <th>Памет</th>
                                <th>Точки</th>
                                <th>Статус</th>
                                <th><i class='fa fa-sync-alt'></i></th>
                                <th><i class='fa fa-trash'></i></th>
                            </tr>
                        </thead>
                        <tbody>
                        </tbody>
                    </table>
                </div>
                <div class='center'>
                    <label class='custom-file-upload'>
                        <input type='file' id='solutionSelector' onchange='addSolutions();' style='display: none;' multiple>
                        <i class='fa fa-plus-circle fa-2x green'></i>
                    </label>
                </div>
            </div>
        ";
    }

    private function getEditProblemForm(Problem $problem): string {
        // Header and Footer
        $headerText = $problem->getId() == -1 ? "Нова задача" : "<span class='blue'>{$problem->getName()}</span> :: Промяна";
        $buttonText = $problem->getId() == -1 ? "Създай" : "Запази";

        return "
            <div class='left'>
                <h2>{$headerText}</h2>
            </div>
            <div class='edit-problem-tab'>
                <a href='#options'><div onclick='changeTab(\"optionsTab\");' class='edit-problem-tab-button underline' id='optionsTab'>Настройки</div></a> |
                <a href='#statement'><div onclick='changeTab(\"statementTab\");' class='edit-problem-tab-button' id='statementTab'>Условие</div></a> |
                <a href='#tests'><div onclick='changeTab(\"testsTab\");' class='edit-problem-tab-button' id='testsTab'>Тестове</div></a> |
                <a href='#solutions'><div onclick='changeTab(\"solutionsTab\");' class='edit-problem-tab-button' id='solutionsTab'>Решения</div></a>
            </div>

            {$this->getOptionsTab($problem)}
            {$this->getStatementTab($problem)}
            {$this->getTestsTab()}
            {$this->getSolutionsTab()}

            <div class='center'>
                <input type='submit' value='{$buttonText}' onclick='submitEditProblemForm();' class='button button-large button-color-red'>
            </div>
        ";
    }

    public function getContent(): string {
        // Default page listing all problems
        $content = inBox("
            <h1>Админ::Задачи</h1>

            <div class='centered'>
                <input type='submit' value='Нова задача' onclick='redirect(`problems/new`);' class='button button-large button-color-blue'>
            </div>
        ");
        $content .= $this->getProblemsList();

        // Specific problem is open
        if (isset($_GET["problemId"])) {
            $problem = $_GET["problemId"] == "new" ? new Problem() : Problem::get($_GET["problemId"]);
            if ($problem == null) {
                $content .= showNotification("ERROR", "Не съществува задача с този идентификатор!");
            }

            $redirect = "/admin/problems";
            $content .= "
                <script>
                    showEditProblemForm(`{$this->getEditProblemForm($problem)}`, `{$redirect}`);
                    let anchor = (document.URL.split('#').length > 1) ? document.URL.split('#')[1] : '';
                    if (anchor === 'options') changeTab('optionsTab');
                    if (anchor === 'statement') changeTab('statementTab');
                    if (anchor === 'tests') changeTab('testsTab');
                    if (anchor === 'solutions') changeTab('solutionsTab');
                </script>
            ";
            $content .= $this->getEditProblemScript($problem);
        }

        return $content;
    }
}

?>