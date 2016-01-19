<?php

use thepurpleblob\core\coreController;

/**
 * Custom exception handler
 */
function exception_handler(Exception $e) {
    echo "<pre>$e</pre>"; die;
    //$controller = new coreController(true);
    //$controller->View('header');
    //$controller->View('exception', array(
    //    'e' => $e,
    //));
    //$controller->View('footer');
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
ini_set('session.gc_maxlifetime', 7200);
session_set_cookie_params(7200);
session_name('SRPS_Santas');
session_start();

