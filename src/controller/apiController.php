<?php

namespace thepurpleblob\railtour\controller;

use thepurpleblob\core\coreController;
use thepurpleblob\core\Session;
use thepurpleblob\core\Form;
use thepurpleblob\railtour\library\Admin;

/**
 * Destination controller.
 *
 */
class ApiController extends coreController {

    /**
     * API to get service as JSON
     * @param int serviceid
     */
    public function serviceAction($serviceid) {
        $this->require_login('ROLE_ADMIN');
        if ($serviceid) {
            $service = Admin::getService($serviceid);
        } else {
            $service = Admin::createService();
        }

        header('Content-type:application/json;charset=utf-8');
        echo json_encode($service->as_array());
    }

    /**
     * Get destination
     * @param int $destinationid
     * @return string
     */
    public function destinationAction($serviceid, $destinationid) {
        $this->require_login('ROLE_ADMIN');
        if ($destinationid) {
            $destination = Admin::getDestination($destinationid);
        } else {
            $destination = Admin::createDestination($serviceid);
        }

        header('Content-type:application/json;charset=utf-8');
        echo json_encode($destination->as_array());
    }

    /**
     * Get joining
     * @param int $joiningid
     * @return string
     */
    public function joiningAction($joiningid) {
        $this->require_login('ROLE_ADMIN');
        if ($joiningid) {
            $joining = Admin::getJoining($joiningid);
        } else {
            $joining = [];
        }

        header('Content-type:application/json;charset=utf-8');
        echo json_encode($joining->as_array());
    }

    /**
     * Get station name
     * @param string $crs
     * @return string
     */
    public function crsAction($crs) {
        if ($station = \ORM::forTable('station')->where('crs', $crs)->findOne()) {
            $name = $station->name;
        } else {
            $name = '';
        }

        echo $name;
    }

}