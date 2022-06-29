<?php
require_once(__DIR__ . '/../db/brain.php');
require_once(__DIR__ . '/../page.php');


class AdminDeleteUserPage extends Page {
    public function getTitle() {
        return 'O(N)::Admin - Delete User';
    }


    public function getContent() {
        $user = Brain::getUser($_GET['userId']);
        if ($user != null) {
            if (Brain::deleteUser($user['id'], $user['username'])) {
                return 'Deleted user successfully.';
            } else {
                return 'Failed to delete user. See logs for details.';
            }
        } else {
            return "No user with id = {$_GET['userId']} found.";
        }
    }

}

?>
