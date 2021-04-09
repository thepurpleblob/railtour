<?php

namespace thepurpleblob\core;

use \ORM;
use \PDO;
use thepurpleblob\core\setup;
use thepurpleblob\core\update;

class install {

    public static function action($version) {
        
        // Does the database structure exist
        $db = ORM::get_db();
        $db->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, 0);

        $query = $db->query('SHOW TABLES');
        $show = $query->fetchAll(PDO::FETCH_COLUMN);
        //dd('Tables', $show);
        if (!$show) {

            // load schema
            require(dirname(__FILE__) . '/../src/schema/schema.php');

            // run through table creation
            foreach ($schema as $sql) {
                $res = setup::pdo_execute($db, $sql);
            }

            // Set current version - start with 0
            $config = ORM::forTable('config')->create();
            $config->name = 'version';
            $config->value = 0;
            $config->save();

            // Any schema updates
            update::action($version);

            // Project specific seeding/setup
            $project = $_ENV['projectname'];
            $class = 'thepurpleblob\\' . $project . '\\setup\\setup';
            $setup = new $class;
            $setup->action();
        } else {

            // Check regardless in case of changes
            update::action($version);
        }


    }

}
