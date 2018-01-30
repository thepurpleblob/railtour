<?php

namespace thepurpleblob\core;

class coreSession {

    public function __construct() {
    	session_set_save_handler(
    		array($this, "_open"),
    		array($this, "_close"),
    		array($this, "_read"),
    		array($this, "_write"),
    		array($this, "_destroy"),
    	    array($this, "_gc")
    	);

    	// Start the session
        $sessionlife = 3600;
        session_set_cookie_params($sessionlife);
        session_name('SRPS_Railtour');
        session_start();
        setcookie(session_name(), session_id(), time() + $sessionlife);
    }

    /**
     * Open the session. Does nothing
     */
    public function _open() {
    	return true;
    }

    /**
     * Close the session. Does nothing
     */
    public function _close() {
    	return true;
    }

    /**
     * Read session data
     * @param string $id session id
     * @return string session data
     */
    public function _read($id) {
    	$session = \ORM::forTable('session')->where('id', $id)->findOne();
    	if ($session) {

    		// Check if IP matches
    		if ($session->ip != $_SERVER['REMOTE_ADDR']) {
    			$this->_destroy($id);
    			return false;
    		}

    		return $session->data;
    	} else {
    		return '';
    	}
    }

    /**
     * Write session data
     * @param string $id session id
     * @param string $data
     * @return bool success
     */
    public function _write($id, $data) {
    	$session = \ORM::forTable('session')->where('id', $id)->findOne();
    	if (!$session) {
    	    $session = \ORM::forTable('session')->create();
    	    $session->id = $id;
    	} else {

    		// Check if IP matches
    		if ($session->ip != $_SERVER['REMOTE_ADDR']) {
    			$this->_destroy($id);
    			return false;
    		}
    	}  
    	$session->access = time();
    	$session->data = $data;
    	$session->ip = $_SERVER['REMOTE_ADDR'];
    	$session->save();

    	return true;
    }

    /** 
     * Destroy a session
     * @param string $id session id
     * @return bool success
     */
    public function _destroy($id) {
    	$session = \ORM::forTable('session')->where('id', $id)->findOne();
    	if ($session) {
    		$session->delete();
    		return true;
    	} else {
    		return false;
    	}
    }

    /**
     * Garbage collection
     * @param int $max
     * @return bool success
     */
    public function _gc($max) {

        // define what is old
        $old = time() - $max;

        // get expired sessions
        $sessions = \ORM::forTable('session')->where_lt('access', $old)->findMany();
        $sessions->delete();

        return true;
    }

}