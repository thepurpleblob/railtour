<?php

use thepurpleblob\core\coreController;
use thepurpleblob\core\coreSession;

/**
 * Custom exception handler
 */
function exception_handler($e) {
    global $CFG;

    error_log($e->getMessage() . "\n" . $e->getTraceAsString());
    $controller = new coreController(true);
    $controller->View('exception', array(
        'e' => $e,
        'message' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
        'debugging' => $CFG->debugging,
    ));
}

/**
 * function to work around PDOs nastiness
 * Use this for table creation and manipulation
 * (because Idiorm doesn't)
 */
function pdo_execute($db, $sql) {
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

// MAIN SETUP STUFF
// (order matters)

// Configure Idiorm
ORM::configure($CFG->dsn);
ORM::configure('username', $CFG->dbuser);
ORM::configure('password', $CFG->dbpass);
ORM::configure('logging', true);

// Make a check for any schema updates and such
require_once(dirname(__FILE__) . '/update.php');

// Set exception handler
set_exception_handler('exception_handler');

// Sessions
$coresession = new coreSession;
