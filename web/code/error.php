<?php
require_once("common.php");
require_once("page.php");

class ErrorPage extends Page {
    public function getTitle(): string {
        return "O(N)::Error";
    }
    
    public function getContent(): string {
        return inBox("Error Page (404).");
    }
    
}

class ForbiddenPage extends Page {
    public function getTitle(): string {
        return "O(N)::Forbidden";
    }

    public function getContent(): string {
        return inBox("Forbidden (403).");
    }
}

class MaintenancePage extends Page {
    public function getTitle(): string {
        return "O(N)::Maintenance";
    }

    public function getContent(): string {
        return inBox("
            <table style='margin: 0 auto;'>
                <tr style='height: 3em;'>
                    <td><i class='fa fa-wrench' style='font-size: 2em;'></i>&nbsp;&nbsp;&nbsp;</td>
                    <td><strong>Системата е временно недостъпна поради профилактика.</strong></td>
                </tr>
            </table>
        ");
    }
}


?>