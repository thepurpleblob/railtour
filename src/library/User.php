<?php

namespace thepurpleblob\railtour\library;

// Lifetime of incomplete purchases in seconds
use Exception;


/**
 * Class Booking
 * @package thepurpleblob\railtour\library
 */
class User {

    /**
     * Validate user
     * @param string $username
     * @param string $password
     * @return mixed user object or false
     */
    public static function validate($username, $password) {
        if (!$user = \ORM::for_table('srps_users')
            ->where(array(
                'username' => $username,
            ))
            ->findOne()) {
                return false;
            }

        if (password_verify($password, $user->password)) {    
            if (password_needs_rehash($user->password, PASSWORD_DEFAULT)) {
                $user->password = password_hash($password, PASSWORD_DEFAULT);
                $user->save();
            }
            return $user;
        } else {
            return false;
        }
    }

    /**
     * Create admin user (if there isn't one)
     */
    public static function installAdmin() {

        // Get all our users
        $users = \ORM::forTable('srps_users')->findMany();

        // if there are none, we will create the default admin user
        if (!$users) {
            $user = \ORM::forTable('srps_users')->create();
            $user->firstname = 'admin';
            $user->lastname = 'admin';
            $user->username = 'admin';
            $user->password = password_hash('password', PASSWORD_DEFAULT);
            $user->role = 'ROLE_ADMIN';
            $user->salt = '';
            $user->email = '';
            $user->is_active = 1;
            $user->save();
        }
    }

    /**
     * Get all users
     * @return array user objects
     */
    public static function getUsers() {
        $users = \ORM::forTable('srps_users')->findMany();

        // Check if editable
        foreach ($users as $user) {
            $user->editable = $user->username != 'admin';
        }

        return $users;
    }

    /**
     * Find user
     * Create empty user record if required
     * @param string $username username to find or empty for new
     * @return object user record
     */
    public static function getUser($username) {
        if (!empty($username)) {
            $user = \ORM::forTable('srps_users')->where('username', $username)->findOne();
            if (!$user) {
                throw new \Exception("User $username not found in db");
            }
        } else {
            $user = \ORM::forTable('srps_users')->create();
            $user->username = '';
            $user->firstname = '';
            $user->lastname = '';
            $user->role = 'ROLE_ORGANISER';
            $user->password = '';
            $user->salt = '';
            $user->email = '';
            $user->is_active = 1;
        }

        return $user;
    }

    /**
     * Delete user
     * @param string $username
     */
    public static function delete($username) {

        // find the user
        $user = \ORM::forTable('srps_users')->where('username', $username)->findOne();
        if (!$user) {
            throw new \Exception("User $username not found in db");
        }

        // Delete the user
        $user->delete();
    }
}
