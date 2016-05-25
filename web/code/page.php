<?php

class Page {
    protected $user;
    
    function __construct($user) {
        $this->user = $user;
    }

    public function getTitle() {
        return 'O(N)::Page';
    }
    
    public function getExtraStyles() {
        return array();
    }

    public function getExtraScripts() {
        return array();
    }
    
    public function getExtraCode() {
        return '';
    }

    public function getContent() {
        return 'ERROR: Virtual method in base class.';
    }
}

?>