<?php
/**
 * Created by PhpStorm.
 * User: howard
 * Date: 10/06/2016
 * Time: 14:00
 */

// Does the database structure exist
$db = ORM::get_db();
$db->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, 0);

$query = $db->query('SHOW TABLES');
$show = $query->fetchAll(PDO::FETCH_COLUMN);
if (!$show) {

    // load schema
    require(dirname(__FILE__) . '/../src/schema/schema.php');

    // run through table creation
    foreach ($schema as $sql) {
        $res = pdo_execute($db, $sql);
    }

    // Set current version
    $config = ORM::forTable('config')->create();
    $config->name = 'version';
    $config->value = $version;
    $config->save();
    
    // Install CRSs
    $stations = new \thepurpleblob\railtour\service\Stations();
    $stations->installStations();

    // Create default admin user
    $user = new \thepurpleblob\railtour\controller\UserController();
    $user->installAction();
}