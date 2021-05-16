<?php

namespace thepurpleblob\railtour\controller;

use thepurpleblob\core\coreController;
use thepurpleblob\core\Session;
use thepurpleblob\core\Form;
use thepurpleblob\railtour\library\Admin;
use thepurpleblob\railtour\library\Booking;

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

    /**
     * Get current purchase
     * @param int $serviceid
     */
    public function getpurchaseAction($serviceid) {
        $service = Admin::getService($serviceid);
        $purchase = Booking::getSessionPurchase($this, $serviceid);
        if ($purchase->bookedby) {
            $this->require_login('ROLE_TELEPHONE', 'booking/numbers/' . $serviceid);
        }

        header('Content-type:application/json;charset=utf-8');
        echo json_encode($purchase->as_array(), JSON_NUMERIC_CHECK);        
    }

    /**
     * Update class
     * @param string class (F or S)
     */
    public function setclassAction($class) {
        if (($class != 'S') && ($class != 'F')) {
            throw new \Exception('Class must be S or F');
        }
        $purchase = Booking::getSessionPurchase($this);
        if ($purchase->bookedby) {
            $this->require_login('ROLE_TELEPHONE', 'booking/joining');
        }
        $purchase->class = $class;
        $purchase->save();
    }

    /**
     * Update passenger numbers
     * @param int $adults
     * @param int $children
     */
    public function setpassengersAction($adults, $children) {
        $purchase = Booking::getSessionPurchase($this);
        if ($purchase->bookedby) {
            $this->require_login('ROLE_TELEPHONE', 'booking/joining');
        }
        $formlimits = Booking::getSingleFormLimits($purchase->id);

        // validation
        if (!is_numeric($adults) || !is_numeric($children)) {
            throw new \Exception('Passenger numbers are invalid parameters');
        }
        $passengers = $adults + $children;
        if (($passengers < $formlimits->minparty) || ($passengers > $formlimits->maxparty)) {
            throw new \Exception('Number of passengers out of permitted range');
        }

        $purchase->adults = $adults;
        $purchase->children = $children;
        $purchase->save();
    }

    /**
     * Get party limits for booking form
     */
    public function getbookingnumbersAction() {
        $purchase = Booking::getSessionPurchase($this);
        $serviceid = $purchase->serviceid;
        $service = Admin::getService($serviceid);
        if ($purchase->bookedby) {
            $this->require_login('ROLE_TELEPHONE', 'booking/joining');
        }
        $limits = Booking::getSingleFormLimits($purchase->id);
  
        header('Content-type:application/json;charset=utf-8');
        echo json_encode($limits); 
    }


}