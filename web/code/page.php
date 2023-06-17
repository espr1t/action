<?php

class Page {
    protected User $user;

    function __construct(User $user) {
        $this->user = $user;
    }

    public function getTitle(): string {
        return "O(N)::Page";
    }

    public function getExtraStyles(): array {
        return array();
    }

    public function getExtraScripts(): array {
        return array();
    }

    public function getContent(): string {
        return "ERROR: Virtual method in base class.";
    }

    public function onLoad(): string {
        return "";
    }
}

?>