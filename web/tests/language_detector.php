<?php
require_once(__DIR__ . "/../code/config.php");
require_once(__DIR__ . "/../code/common.php");
require_once(__DIR__ . "/../code/page.php");
require_once(__DIR__ . "/../code/entities/user.php");
require_once(__DIR__ . "/../code/db/brain.php");

class LanguageDetectorPage extends Page {
    public function getTitle(): string {
        return "O(N)::Test";
    }

    public function getExtraScripts(): array {
        return array("/scripts/language_detector.js");
    }

    public function onLoad(): string {
        return "";
    }

    public function getContent(): string {
        if ($this->user->getAccess() < $GLOBALS["ACCESS_ADMIN_PAGES"]) {
            return inBox("<h1>Language Detector Test</h1><br>
                Дадената функционалност изисква администраторски права.
            ");
        } else {
            $NUM_EVALUATED_SOURCES = 1000;
            $startIdx = $_GET["start"];
            $endIdx = $startIdx + $NUM_EVALUATED_SOURCES;

            return inBox("<h1>Language Detector Test</h1><br>
                <div>Evaluating submit <span id='curSubmit'>0</span> out of {$NUM_EVALUATED_SOURCES}.</div><br><br>
            ") . "
                <script>
                    var curSource = 0;
                    function evalSource(id, response) {
                        document.getElementById('curSubmit').innerText = '' + ++curSource;

                        response = JSON.parse(response);
                        let el = document.createElement('div');
                        if (response['status'] !== 'OK') {
                            el.innerHTML = 'Trying source with id ' + id + '<br>';
                            el.innerHTML += '&nbsp;&nbsp;&nbsp;&nbsp;>> got non-OK response.<br>';
                            el.innerHTML += '&nbsp;&nbsp;&nbsp;&nbsp;>> status: ' + response['status'] + '<br>';
                            el.innerHTML += '&nbsp;&nbsp;&nbsp;&nbsp;>> reason: ' + response['reason'] + '<br>';
                            el.innerHTML += '<br>';
                        } else {
                            let detected = detectLanguage(response['source'], id);
                            if (response['language'] !== detected) {
                                el.innerHTML = 'Trying source with id ' + id + '<br>';
                                el.innerHTML += '&nbsp;&nbsp;&nbsp;&nbsp;>> current language: ' + response['language'] + '<br>';
                                el.innerHTML += '&nbsp;&nbsp;&nbsp;&nbsp;>> detected language: ' + detected + '<br>';
                                el.innerHTML += '<br>';
                            }
                        }
                        document.getElementsByClassName('box')[0].appendChild(el);
                    }
                
                    async function checkSources() {
                        for (let id = $startIdx; id < $endIdx; id++) {
                            ajaxCall('/actions/data/source/' + id, {}, evalSource.bind(null, id));
                            await sleep(10);
                        }
                    }
                    checkSources();
                </script>
            ";
        }
    }
}

?>