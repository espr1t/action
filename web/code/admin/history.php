<?php
require_once(__DIR__ . "/../common.php");
require_once(__DIR__ . "/../page.php");
require_once(__DIR__ . "/../entities/submit.php");

class AdminHistoryPage extends Page {
    public function getTitle(): string {
        return "O(N)::Admin - History";
    }

    public function getExtraScripts(): array {
        return array("/scripts/admin.js");
    }

    private function getTable(): string {
        $submit = Submit::get($_GET["submitId"]);
        if ($submit == null) {
            return "Няма събмит с този идентификатор.";
        }

        $tests = Brain::getProblemTests($submit->getProblemId());
        $runInfo = array();
        foreach ($tests as $test) {
            $runInfo[$test["inpFile"]] = array("-", "-", "-", "-", "-");
        }

        $history = Brain::getHistory($submit->getId());
        for ($run = 1; $run <= 5; $run++) {
            if ($history[sprintf("time%02d", $run)] != "") {
                $times = explode(",", $history[sprintf("time%02d", $run)]);
                if (count($times) != count($tests)) {
                    echo("WARNING: Different number of tests and results!");
                }
                for ($i = 0; $i < count($tests); $i++) {
                    $runInfo[$tests[$i]["inpFile"]][$run - 1] = sprintf("%.2f", floatval($times[$i]));
                }
            }
        }

        $testsRows = "";
        foreach ($runInfo as $test => $times) {
            $styleCols = array("", "", "", "", "");

            $minIdx = $maxIdx = -1;
            for ($i = 0; $i < count($times); $i++) {
                if ($times[$i] != "-") {
                    if ($minIdx == -1 || floatval($times[$i]) < floatval($times[$minIdx])) $minIdx = $i;
                    if ($maxIdx == -1 || floatval($times[$i]) > floatval($times[$maxIdx])) $maxIdx = $i;
                }
            }
            if ($minIdx != -1) {
                $minVal = floatval($times[$minIdx]);
                $maxVal = floatval($times[$maxIdx]);
                $shouldWarn = ($maxVal - $minVal > 0.05);
                // if ($minVal > 0.1 && ($maxVal - $minVal) / $minVal > 0.1)
                //     $shouldWarn = true;
                if ($shouldWarn) {
                    $styleCols[$minIdx] = "font-weight: bold; color: #129D5A;";
                    $styleCols[$maxIdx] = "font-weight: bold; color: #DD4337;";
                }
            }


            $testsRows .= "
                <tr>
                    <td>{$test}</td>
                    <td><span style='{$styleCols[0]}'>{$times[0]}</span></td>
                    <td><span style='{$styleCols[1]}'>{$times[1]}</span></td>
                    <td><span style='{$styleCols[2]}'>{$times[2]}</span></td>
                    <td><span style='{$styleCols[3]}'>{$times[3]}</span></td>
                    <td><span style='{$styleCols[4]}'>{$times[4]}</span></td>
                </tr>
            ";
        }

        return "
            <div class='centered'>
                <table class='default'>
                    <tr>
                        <th>Тест</th><th>Run 1</th><th>Run 2</th><th>Run 3</th><th>Run 4</th><th>Run 5</th>
                    </tr>
                    {$testsRows}
                </table>
            </div>
        ";
    }

    public function getContent(): string {
        $content = "
            <h1>Админ :: Тест на скорост</h1>
            <br>
        ";

        if (isset($_GET["submitId"])) {
            $content .= $this->getTable();
        } else {
            $content .= "Трябва да подадете ID на събмит.";
        }
        return inBox($content);
    }
}

?>