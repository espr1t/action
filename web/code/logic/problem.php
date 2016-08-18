<?php
require_once('common.php');

class Problem {
    public $id = -1;
    public $name = '';
    public $author = '';
    public $folder = '';
    public $time_limit = -1;
    public $memory_limit = -1;
    public $type = '';
    public $difficulty = '';
    public $tags = array();
    public $origin = '';
    public $checker = '';
    public $executor = '';
    public $tests = array();
    public $submits = array();

    private function arrayFromInstance() {
        return array(
            'id' => $this->id,
            'name' => $this->name,
            'author' => $this->author,
            'folder' => $this->folder,
            'time_limit' => $this->time_limit,
            'memory_limit' => $this->memory_limit,
            'type' => $this->type,
            'difficulty' => $this->difficulty,
            'tags' => $this->tags,
            'origin' => $this->origin,
            'checker' => $this->checker,
            'executor' => $this->executor,
            'tests' => $this->tests,
            'submits' => $this->submits
        );
    }

    private static function instanceFromArray($info) {
        $problem = new Problem;
        $problem->id = getValue($info, 'id');
        $problem->name = getValue($info, 'name');
        $problem->author = getValue($info, 'author');
        $problem->folder = getValue($info, 'folder');
        $problem->time_limit = getValue($info, 'time_limit');
        $problem->memory_limit = getValue($info, 'memory_limit');
        $problem->type = getValue($info, 'type');
        $problem->difficulty = getValue($info, 'difficulty');
        $problem->tags = getValue($info, 'tags');
        $problem->origin = getValue($info, 'origin');
        $problem->checker = getValue($info, 'checker');
        $problem->executor = getValue($info, 'executor');
        $problem->tests = getValue($info, 'tests');
        $problem->submits = getValue($info, 'submits');
        return $problem;
    }

    public static function get($id) {
        $entries = scandir($GLOBALS['PATH_PROBLEMS']);
        foreach ($entries as $folder) {
            if ($folder == '.' || $folder == '..') {
                continue;
            }
            $fileName = sprintf("%s/%s/%s", $GLOBALS['PATH_PROBLEMS'], $folder, $GLOBALS['PROBLEM_INFO_FILENAME']);
            $info = json_decode(file_get_contents($fileName), true);
            if ($info['id'] == $id) {
                return Problem::instanceFromArray($info);
            }
        }
        return null;
    }

    private function update() {
        $fileName = sprintf('%s/%s/%s', $GLOBALS['PATH_PROBLEMS'], $this->folder, $GLOBALS['PROBLEM_INFO_FILENAME']);
        $file = fopen($fileName, 'w');
        if (!$file) {
            error_log('Unable to open file ' . $fileName . ' for writing!');
            return false;
        }
        if (!fwrite($file, json_encode($this->arrayFromInstance(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
            error_log('Unable to write to file ' . $fileName . '!');
            return false;
        }
        return true;
    }

    public function addSubmission($id) {
        array_push($this->submits, $id);
        return $this->update();
    }

}

?>