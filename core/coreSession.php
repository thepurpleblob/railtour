<?php

namespace thepurpleblob\core;

define('SESSION_COOKIE', 'sessionpb');

class coreSession {

    private $pdo = null;

    protected $sessionid = '';

    private static $instance = null;

    public function __construct($pdo) {
        if ($_COOKIE[SESSION_COOKIE]) {
            $this->sessionid = $_COOKIE[SESSION_COOKIE];
        } else {
            $this->sessionid = coreSession::generateSessionID();
        }

        // Start the session
        $sessionlife = 3600;
        setcookie(SESSION_COOKIE, $this->sessionid, time() + $sessionlife);
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
     * Get the instance of the class
     */
    protected static function getInstance() {
        if (!self::$instance ) {
            self::$instance = new coreSession(\ORM::get_db());
        }

        return self::$instance;
    }

    /**
     * Execute PDO query
     * @param string $sql
     * @param array $params
     * @return array
     */
    private function _query($sql, $params = []) {
        $stmt = $this->pdo->prepare($sql);
        if ($stmt->execute($params)) {
            $rows = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $rows[] = $row;
            }
            return $rows;
        }

        return [];
    }

    /**
     * Internal write to database function
     * @param string $name
     * @param mixed $value
     */
    private function _write($name, $value) {

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
        $sessions = \ORM::forTable('session')->where_lt('access', $old)->deleteMany();

        return true;
    }

}
