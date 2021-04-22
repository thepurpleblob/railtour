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

            // update password
            // (obviously, change to a better one)
            $user = \ORM::forTable('srps_users')->where('username', 'admin')->findOne();
            if ($user) {
                $user->password = password_hash('password', PASSWORD_DEFAULT);
                $user->save();
            }
        }

        if ($dbversion < 2021041901) {
            $db->exec('DROP TABLE session');
            $db->exec('CREATE TABLE session (
                `id` int NOT NULL AUTO_INCREMENT,
                `sessionid` varchar(32) NOT NULL,
                `access` int unsigned DEFAULT NULL,
                `name` varchar(32) NOT NULL DEFAULT "",
                `data` text,
                `ip` varchar(32) DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_sina` (`sessionid`,`name`)
                )');
        }

        if ($dbversion < 2021041902) {
            $db->exec('ALTER TABLE service
                ADD mealsinfirst int(1) NOT NULL DEFAULT 1,
                ADD mealsinstandard int(1) NOT NULL DEFAULT 1');
        }

        if ($dbversion < 2021042200) {
            $db->exec('ALTER TABLE session
                ADD flash int(1) NOT NULL DEFAULT "0"');
        }

        // Make config version up to date
        $config->name = 'version';
        $config->value = $version;
        $config->save();
    }
}
