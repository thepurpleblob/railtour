<?php

namespace thepurpleblob\railtour\library;

// Lifetime of incomplete purchases in seconds
use Exception;


/**
 * Class Booking
 * @package thepurpleblob\railtour\library
 */
class User
{

    protected $controller;

    /**
     * Booking constructor.
     * @param $controller
     */
    public function __construct($controller) {
        $this->controller = $controller;
    }

    /**
     * Validate user
     * @param string $username
     * @param string $password
     * @return mixed user object or false
     */
    public function validate($username, $password) {
        $user = \ORM::for_table('srps_users')
            ->where(array(
                'username' => $username,
                'password' => md5($password),
            ))
            ->findOne();

        return $user;
    }

    /**
     * Create admin user (if there isn't one)
     */
    public function installAdmin() {

        // Get all our users
        $users = \ORM::forTable('User')->findMany();

        // if there are none, we will create the default admin user
        if (!$users) {
            $user = \ORM::forTable('User')->create();
            $user->firstname = 'admin';
            $user->lastname = 'admin';
            $user->username = 'admin';
            $user->password = md5('admin');
            $user->role = 'ROLE_ADMIN';
            $user->save();
        }
    }

    /**
     * Get all users
     * @return array user objects
     */
    public function getUsers() {
        $users = \ORM::forTable('srps_users')->findMany();

        return $users;
    }
}