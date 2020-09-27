<?php
require_once(__DIR__ . '/../config.php');
require_once(__DIR__ . '/../common.php');
require_once(__DIR__ . '/../db/brain.php');

class Match {
    public $id = -1;
    public $problemId = -1;
    public $test = -1;
    public $userOne = -1;
    public $userTwo = -1;
    public $submitOne = -1;
    public $submitTwo = -1;
    public $scoreOne = 0;
    public $scoreTwo = 0;
    public $message = '';
    public $replayId = '';

    public function __construct($problemId, $test, $userOne, $userTwo, $submitOne = -1, $submitTwo = -1) {
        $this->id = -1;
        $this->problemId = $problemId;
        $this->test = $test;
        $this->userOne = $userOne;
        $this->userTwo = $userTwo;
        $this->submitOne = $submitOne;
        $this->submitTwo = $submitTwo;
        $this->scoreOne = 0;
        $this->scoreTwo = 0;
        $this->message = '';
        $this->replayId = '';
    }

    static function instanceFromArray($info) {
        $match = new Match(-1, -1, -1, -1);
        $match->id = intval(getValue($info, 'id'));
        $match->problemId = intval(getValue($info, 'problemId'));
        $match->test = intval(getValue($info, 'test'));
        $match->userOne = intval(getValue($info, 'userOne'));
        $match->userTwo = intval(getValue($info, 'userTwo'));
        $match->submitOne = intval(getValue($info, 'submitOne'));
        $match->submitTwo = intval(getValue($info, 'submitTwo'));
        $match->scoreOne = floatval(getValue($info, 'scoreOne'));
        $match->scoreTwo = floatval(getValue($info, 'scoreTwo'));
        $match->message = getValue($info, 'message');
        $match->replayId = getValue($info, 'replayId');
        return $match;
    }

    static public function getById($matchId) {
        $brain = new Brain();
        $info = $brain->getMatchById($matchId);
        return $info == null ? null : Match::instanceFromArray($info);
    }

    static public function get($problemId, $test, $userOne, $userTwo) {
        $brain = new Brain();
        $info = $brain->getMatch($problemId, $test, $userOne, $userTwo);
        return $info == null ? null : Match::instanceFromArray($info);
    }

    public function update() {
        $brain = new Brain();
        return $brain->updateMatch($this);
    }
}

?>