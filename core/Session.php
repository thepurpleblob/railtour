<?php

namespace thepurpleblob\core;

define('SESSION_LIFE', 3600);
define('SESSION_COOKIE', 'sessionpb');

class Session {

    protected $sessionid = '';

    private static $instance = null;

    public function __construct() {
        if (isset($_COOKIE[SESSION_COOKIE])) {
            $this->sessionid = $_COOKIE[SESSION_COOKIE];
        } else {
            $this->sessionid = Session::generateSessionID();
        }

        // Start the session
        $sessionlife = time() + SESSION_LIFE;
        setcookie(SESSION_COOKIE, $this->sessionid, [
            'expires' => $sessionlife,
            'path' => '/',
            //'domain' => '',
            //'secure' => true,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    /**
     * Create sessionid
     * @return string
     */
    private static function generateSessionID() {
        $id = sha1(uniqid($_SERVER['REMOTE_ADDR'], true));

        return substr($id, 0, 32);
    }

    /**
     * Expire old sessions
     * (don't care about session id)
     */
    private static function _expire() {

        // define what is old
        $old = time() - SESSION_LIFE;

        // get expired sessions
        \ORM::forTable('session')->where_lt('access', $old)->deleteMany();
    }

    /**
     * Get the instance of the class
     */
    protected static function getInstance() {
        Session::_expire();
        if (!self::$instance ) {
            self::$instance = new Session();
        }

        return self::$instance;
    }

    /**
     * Session start 
     * So that cookie stuff always gets processed
     */
    public static function session_start() {
        $session = self::getInstance();
    }

    /**
     * Get user IP address (we might check this one day)
     * @return string
     */
    private static function getIp() {
        return $_SERVER['REMOTE_ADDR'];
    }

    /**
     * Internal write to database function
     * @param string $name
     * @param mixed $value
     * @param int $flash 0/1 (1 - flash)
     */
    private function _write($name, $value, $flash = 0) { 
        if (!$entry = \ORM::forTable('session')->where(['sessionid' => $this->sessionid, 'name' => $name])->findOne()) {
            $entry = \ORM::forTable('session')->create();
            $entry->sessionid = $this->sessionid;
        }
        $entry->data = json_encode($value);
        $entry->name = $name;
        $entry->ip = Session::getIp();
        $entry->access = time();
        $entry->flash = $flash;
        $entry->save();
    }

    /**
     * Write to session
     * @param string $name
     * @param mixed $value;
     */
    public static function write($name, $value) {
        $session = self::getInstance();
        $session->_write($name, $value);
    }

    /**
     * Write flash entry
     * (gets deleted as soon as it's read)
     * @param string $name
     * @param mixed $value
     */
    public static function writeFlash($name, $value) {
        $session = self::getInstance();
        $session->_write($name, $value, 1);
    }

    /** 
     * Read from session
     * @param string name
     * @return mixed
     */
    private function _read($name) {
        $entry = \ORM::forTable('session')->where(['sessionid' => $this->sessionid, 'name' => $name])->findOne();
        if (!$entry) {
            return false;
        }
        if ($entry->ip != Session::getIp()) {
            throw new \Exception('IP addresses do not match in session');
        }

        $entry->access = time();
        $entry->save();

        $value =  json_decode($entry->data);

        if ($entry->flash) {
            $entry->delete();
        }

        return $value;
    }

    /**
     * Read from session
     * @param string name
     * @parm mixed $default
     * @return mixed 
     */
    public static function read($name, $default = false) {
        $session = self::getInstance();
        if ($value = $session->_read($name)) {
            return $value;
        } else {
            return $default;
        }
    }

    /**
     * Does entry exist
     * @param string $name
     * @return boolean
     */
    private function _exists($name) {
        $entry = \ORM::forTable('session')->where(['sessionid' => $this->sessionid, 'name' => $name])->findOne();
        return $entry ? true : false;
    }

    /**
     * Does entry exist
     * @param string $name
     * @return boolean
     */
    public static function exists($name) {
        $session = self::getInstance();
        return $session->_exists($name);       
    }

    /**
     * Delete from session
     */
    private function _delete($name) {
        \ORM::forTable('session')->where(['sessionid' => $this->sessionid, 'name' => $name])->deleteMany();
    }

    /**
     * Delete from session
     * @param string name
     */
    public static function delete($name) {
        $session = self::getInstance();
        $session->_delete($name);
    }

}
