<?php
/**
 * Permits custom config of the application
 */

 namespace thepurpleblob\railtour\setup;

 use thepurpleblob\railtour\controller\userController;

 class setup {

    public static function action() {

        // Install stations (CRS codes)
        $stations = json_decode(file_get_contents(dirname(__FILE__) . '/stations.json'), false);
        $stations = $stations->locations;

        foreach ($stations as $station) {
            $crs = $station->crs;
            $name = $station->name;
            $record = \ORM::forTable('station')->create();
            $record->name = $name;
            $record->crs = $crs;
            $record->save();
        }

        // Create default admin user
        $user = new userController();
        $user->installAction();
    }
 }