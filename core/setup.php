<?php

namespace thepurpleblob\core;

use thepurpleblob\core\coreController;
use thepurpleblob\core\coreSession;
use \ORM;

class setup {

    /**
     * function to work around PDOs nastiness
     * Use this for table creation and manipulation
     * (because Idiorm doesn't)
     */
    public static function pdo_execute($db, $sql) {
        $stmt = $db->prepare($sql);
        if (!$stmt->execute()) {
            $error = $stmt->errorInfo();
            echo "<pre>PDO error... ";
            var_dump($error);
            die;
        }
        var_dump($stmt);
        if ($stmt->rowCount()) {
            $result = $stmt->fetchAll();
            $stmt->closeCursor();
            return $result;
        } else {
            return array();
        }
    }

    /**
     * Configure database
     */
    public static function database() {

        // Configure Idiorm
        ORM::configure($_ENV['dsn']);
        ORM::configure('username', $_ENV['dbuser']);
        ORM::configure('password', $_ENV['dbpass']);
        ORM::configure('logging', true);
    }

// Sessions
//$coresession = new coreSession;
}
