<?php

class User {
    private $id = 0;
    private $handle = 'anonymous';
    
    public function getId() {
        return $this->id;
    }
    
    public function getHandle() {
        return $this->handle;
    }
    
    public function logOut() {
        // TODO
    }
}

?>