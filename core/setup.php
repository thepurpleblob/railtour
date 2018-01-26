<?php

use thepurpleblob\core\coreController;

/**
 * Custom exception handler
 */
function exception_handler($e) {
    echo "<pre>$e</pre>"; die;
    //$controller = new coreController(true);
    //$controller->View('header');
    //$controller->View('exception', array(
    //    'e' => $e,
    //));
    //$controller->View('footer');
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

// Configure Idiorm
ORM::configure($CFG->dsn);
ORM::configure('username', $CFG->dbuser);
ORM::configure('password', $CFG->dbpass);
ORM::configure('logging', true);

// Set exception handler
set_exception_handler('exception_handler');

// Start the session
//ini_set('session.gc_maxlifetime', 7200);
//ini_set('session.cookie_lifetime', 7200);
$sessionlife = 3600;
session_set_cookie_params($sessionlife);
session_name('SRPS_Railtour');
session_start();
setcookie(session_name(), session_id(), time() + $sessionlife);

