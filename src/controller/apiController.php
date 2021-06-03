<?php

namespace thepurpleblob\railtour\controller;

use thepurpleblob\core\coreController;
use thepurpleblob\core\Session;
use thepurpleblob\core\Form;
use thepurpleblob\railtour\library\Admin;
use thepurpleblob\railtour\library\Booking;

define('LIMITED_TICKETS', 20);

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
     * @param string $class (F or S)
     * @param int $reset (1 = clear down other stuff on class change)
     */
    public function setclassAction($class, $reset = 0) {
        if (($class != 'S') && ($class != 'F')) {
            throw new \Exception('Class must be S or F');
        }
        $purchase = Booking::getSessionPurchase($this);
        if ($purchase->bookedby) {
            $this->require_login('ROLE_TELEPHONE', 'booking/joining');
        }
        $purchase->class = $class;

        // reset stuff that changing the class might screw up
        if ($reset) {
            $purchase->adults = 0;
            $purchase->children = 0;
            $purchase->meala = 0;
            $purchase->mealb = 0;
            $purchase->mealc = 0;
            $purchase->meald = 0;
        }
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
     * Set destination
     * @param string $crs
     */
    public function setdestinationAction($crs) {
        $purchase = Booking::getSessionPurchase($this);
        if ($purchase->bookedby) {
            $this->require_login('ROLE_TELEPHONE', 'booking/joining');
        }

        // Validation
        $stations = Booking::getDestinationStations($purchase->serviceid);
        if (!array_key_exists($crs, $stations)) {
            throw new \Exception('Destination CRS code is invalid ' + $crs);
        } 

        $purchase->destination = trim($crs);
        $purchase->save();
    }

    /**
     * Set joining
     * @param string $crs
     */
    public function setjoiningAction($crs) {
        $purchase = Booking::getSessionPurchase($this);
        if ($purchase->bookedby) {
            $this->require_login('ROLE_TELEPHONE', 'booking/joining');
        }
        
        // Validation
        $stations = Booking::getJoiningStations($purchase->serviceid);
        if (!array_key_exists($crs, $stations)) {
            throw new \Exception('Destination CRS code is invalid ' + $crs);
        }

        $purchase->joining = trim($crs);
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

    /**
     * Get supplemental information for Class selection
     * Seats remaining
     * Prices
     * (Assumes joining and destination have been selected)
     * @param string 
     * @return string
     */
    public function getclasssupplementalAction() {
        $purchase = Booking::getSessionPurchase($this);
        $supp = new \stdClass();
        $supp->valid = false;
        if ($purchase->joining & $purchase->destination) {

            // get first and standard fares
            $service = Admin::getService($purchase->serviceid);
            $farestandard = Booking::calculateFare($service, $purchase, 'S');
            $farefirst = Booking::calculateFare($service, $purchase, 'F');
            $supp->standardadult = $farestandard->adultunit;
            $supp->standardchild = $farestandard->childunit;
            $supp->firstadult = $farefirst->adultunit;
            $supp->firstchild = $farefirst->childunit;

            // we need to know about the number
            // it's a bodge - but if the choice is made then skip this check
            $numbers = Booking::countStuff($purchase->serviceid, $purchase);
            $supp->availablefirst = $numbers->remainingfirst > 0;
            $supp->availablestandard = $numbers->remainingstandard > 0;

            // limited
            $supp->limitedfirst = $numbers->remainingfirst <= LIMITED_TICKETS;
            $supp->limitedstandard = $numbers->remainingstandard <= LIMITED_TICKETS;

            $supp->valid = true;
        }

        header('Content-type:application/json;charset=utf-8');
        echo json_encode($supp); 
    }

    /**
     * Get steps
     * Booking steps on SPA booking screen
     */
    public function getstepsAction() {
        $purchase = Booking::getSessionPurchase($this);
        $serviceid = $purchase->serviceid;
        $service = Admin::getService($serviceid);
        $destinations = Booking::getDestinationStations($serviceid);
        $joinings = Booking::getJoiningStations($serviceid);

        $steps = [];
        $first = 3;
        if (count($joinings) > 1) {
            $steps[2] = 'Joining';
            $first = 2;
        }
        if (count($destinations) > 1) {
            $steps[1] = 'Destination';
            $first = 1;
        }

        $steps[3] = 'Class';
        $steps[4] = 'Numbers';

        if (Booking::mealsAvailable($service, $purchase)) {
            $steps[5] = 'Meals';
        }

        $stepinfo = new \stdClass();
        $stepinfo->first = $first;
        $stepinfo->steps = $steps;

        header('Content-type:application/json;charset=utf-8');
        echo json_encode($stepinfo);        
    }

    /**
     * Get meals form info
     */
    public function getmealsAction() {
        $purchase = Booking::getSessionPurchase($this);
        $serviceid = $purchase->serviceid;
        $service = Admin::getService($serviceid);

        // we can't show meals unless we already know some stuff
        $displaymeals = $purchase->class && $purchase->joining && $purchase->destination;

        // ...in which case we can create the form data
        if ($displaymeals) {
            $mealsform = Booking::mealsForm($service, $purchase);
        } else {
            $mealsform = [];
        }

        header('Content-type:application/json;charset=utf-8');
        echo json_encode($mealsform);
    }

    /**
     * Check if meals available for currently selected class
     */
    public function getclassmealsAction() {
        $purchase = Booking::getSessionPurchase($this);
        $serviceid = $purchase->serviceid;
        $service = Admin::getService($serviceid);

        $available = true;
        if (($purchase->class == 'F') && !$service->mealsinfirst) {
            $available = false;
        }
        if (($purchase->class == 'S') && !$service->mealsinstandard) {
            $available = false;
        }

        header('Content-type:application/json;charset=utf-8');
        echo json_encode($available);
    }

    /**
     * Set meal
     * @param string $letter
     * @param int $number
     */
    public function setmealAction($letter, $number) {
        if (strpos('abcd', $letter) === false) {
            throw new \Exception('Meal letter is invalid - ' . $letter);
        }
        $purchase = Booking::getSessionPurchase($this);
        $serviceid = $purchase->serviceid;
        $service = Admin::getService($serviceid);
        $mealsform = Booking::mealsForm($service, $purchase);

        // validation
        $verified = false;
        foreach ($mealsform as $meal) {
            if ($meal->letter = $letter) {
                if (($number < 0) || ($number > $meal->maxmeals)) {
                    throw new \Exception('Meal quantity out of range - ' . $letter . ' ' . $number);
                }
                $verified = true;
                break;
            }
        }
        if (!$verified) {
            throw new \Exception('Meal letter not found for this service - ' . $letter);
        }
        $field = 'meal' . $letter;
        $purchase->$field = $number;
        $purchase->save();
    }

    /**
     * Get service details
     * @param int $serviceid
     */
    public function getserviceAction() {
        $service = Admin::getService($serviceid);
        $info = new \stdClass;
    }


}