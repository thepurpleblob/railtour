<?php

namespace thepurpleblob\core;

use \ORM;

/**
 * Check for schema updates
 * User: howard
 * Date: 30/03/2016
 * Time: 21:35
 */
class update { 

    public static function action($version) {

        // Try to find current version in database
        $config = ORM::forTable('config')->where('name', 'version')->findOne();
        if ($config) {
            $dbversion = $config->value;
        } else {
            $config = ORM::forTable('config')->create();
            $dbversion = 0;
        }

        $db = ORM::get_db();
        $db->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, 0);

        if ($dbversion < 2021032600) {
            $db->exec('ALTER TABLE srps_users
                MODIFY password varchar(255) NOT NULL');
            $db->exec('ALTER TABLE limits
                  ADD minparty int(11) NOT NULL,
                  ADD minpartyfirst int(11) NOT NULL');
            $db->exec('CREATE TABLE `session_data` (
                `session_id` varchar(32) NOT NULL default "",
                `hash` varchar(32) NOT NULL default "",
                `session_data` blob NOT NULL,
                `session_expire` int(11) NOT NULL default 0,
                PRIMARY KEY  (`session_id`)
              )');

            // update password
            // (obviously, change to a better one)
            $user = \ORM::forTable('srps_users')->where('username', 'admin')->findOne();
            if ($user) {
                $user->password = password_hash('password', PASSWORD_DEFAULT);
                $user->save();
            }
        }

        // Make config version up to date
        $config->name = 'version';
        $config->value = $version;
        $config->save();
    }
}
