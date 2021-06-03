<?php

namespace thepurpleblob\railtour\library;

use Exception;
use thepurpleblob\railtour\library\Admin;
use thepurpleblob\core\Session;

define('CLASS_STANDARD', 'S');
define('CLASS_FIRST', 'F');

/**
 * Class Booking
 * @package thepurpleblob\railtour\library
 */
class Booking  {

    /**
     * Get services available to book
     * @return array zero-indexed for mustache
     */
    public static function availableServices() {

        // Get 'likely' candidates
        $potentialservices = \ORM::for_table('service')->where('visible', true)->order_by_asc('date')->findMany();

        // We need to do more checks to see if it is really available
        $services = array();
        foreach ($potentialservices as $service) {
            $count = Booking::countStuff($service->id);
            if (Booking::canProceedWithBooking($service, $count, true)) {
                $services[$service->id] = $service;
            }
        }

        return array_values($services);
    }

    /**
     * Get the service given the service 'code'
     * @param string $code - booking code
     * @return mixed
     * @throws Exception
     */
    public static function serviceFromCode($code) {
        $services = \ORM::forTable('service')->where('code', $code)->findMany();

        if (!$services) {
            throw new Exception('Unable to find Service record for code = ' . $code);
        }

        if (count($services) > 1) {
            throw new Exception('More than one service defined with code = ' . $code);
        }

        return reset($services);
    }

    /**
     * Get the maximum party size in theory.
     * We have to use this before we know the first/standard choice
     * (even though they can, effectively, have different limits)
     * @param $limits object
     * @return int
     */
    public static function getMaxparty($limits) {
        $maxparty = $limits->maxparty;
        if ($limits->maxpartyfirst and ($limits->maxpartyfirst > $maxparty)) {
            $maxparty = $limits->maxpartyfirst;
        }

        return $maxparty;
    }

    /**
     * Convert a null to a zero
     * (done a lot in countStuff)
     * @param mixed $value int or null
     * @return int
     */
    protected static function zero($value) {
        $result = ($value) ? $value : 0;
        return $result;
    }

    /**
     * Basic checks to ensure booking can procede
     * @param object $service
     * @param object $count
     * @param bool $showempty return true even if no seats left
     * TODO: Fix the date shit so it works!
     * @return bool
     */
    public static function canProceedWithBooking($service, $count, $showempty = false) {
        $today = date('Y-m-d');
        if ($showempty) {
            $seatsavailable = true;
        } else {
            $seatsavailable = 
                (($count->remainingfirst > 0) or ($count->remainingstandard > 0));
        }
        $isvisible = ($service->visible);
        $isindate = ($service->date > $today);

        return ($seatsavailable and $isvisible and $isindate);
    }

    /**
     * Count the purchases and work out what's left. Major PITA this
     * @param int $serviceid
     * @param object $currentpurchase (purchase in progress)
     * @return object various counts
     */
    public static function countStuff($serviceid, $currentpurchase=null) {

        // get incomplete purchases
        Admin::deleteOldPurchases();

        // Always a chance the limits don't exist yet
        $limits = Admin::getLimits($serviceid);

        // Create counts entity
        $count = new \stdClass();

        // get first class booked
        $fbtotal = \ORM::forTable('purchase')
            ->select_expr('SUM(adults + children)', 'fb')
            ->where(array(
                'completed' => 1,
                'class' => 'F',
                'status' => 'OK',
                'serviceid' => $serviceid,
            ))
            ->findOne();
        $count->bookedfirst = Booking::zero($fbtotal->fb);

        // get first class in progress
        $fptotal = \ORM::forTable('purchase')
            ->select_expr('SUM(adults + children)', 'fp')
            ->where(array(
                'completed' => 0,
                'class' => 'F',
                'status' => 'OK',
                'serviceid' => $serviceid,
            ))
            ->findOne();
        $count->pendingfirst = Booking::zero($fptotal->fp);

        // if we have a purchase in progress, adjust current pending count
        if ($currentpurchase) {
            if ($currentpurchase->class == 'F') {
                $pf = $count->pendingfirst;
                $pf = $pf - $currentpurchase->adults - $currentpurchase->children;
                $pf = $pf < 0 ? 0 : $pf;
                $count->pendingfirst = $pf;
            }
        }

        // firct class remainder is simply...
        $count->remainingfirst = $limits->first - $count->bookedfirst - $count->pendingfirst;

        // get standard class booked
        $sbtotal = \ORM::forTable('purchase')
            ->select_expr('SUM(adults + children)', 'sb')
            ->where(array(
                'completed' => 1,
                'class' => 'S',
                'status' => 'OK',
                'serviceid' => $serviceid,
            ))
            ->findOne();
        $count->bookedstandard = Booking::zero($sbtotal->sb);

        // get standard class in progress
        $sptotal = \ORM::forTable('purchase')
            ->select_expr('SUM(adults + children)', 'sp')
            ->where(array(
                'completed' => 0,
                'class' => 'S',
                'status' => 'OK',
                'serviceid' => $serviceid,
            ))
            ->findOne();
        $count->pendingstandard = Booking::zero($sptotal->sp);

        // if we have a purchase object then remove any current count from pending
        if ($currentpurchase) {
            if ($currentpurchase->class == 'S') {
                $ps = $count->pendingstandard;
                $ps = $ps - $currentpurchase->adults - $currentpurchase->children;
                $ps = $ps < 0 ? 0 : $ps;
                $count->pendingstandard = $ps;
            }
        }

        // standard class remainder is simply
        $count->remainingstandard = $limits->standard - $count->bookedstandard - $count->pendingstandard;

        // get first supplements booked. Note field is a boolean and applies to
        // all persons in booking (which is only asked for parties of one or two)
        $suptotal = \ORM::forTable('purchase')
            ->select_expr('SUM(adults + children)', 'sup')
            ->where(array(
                'completed' => 1,
                'class' => 'F',
                'status' => 'OK',
                'serviceid' => $serviceid,
            ))
            ->where_gt('seatsupplement', 0)
            ->findOne();
        $count->bookedfirstsingles = Booking::zero($suptotal->sup);

        // get first supplements in progress. Note field is a boolean and applies to
        // all persons in booking (which is only asked for parties of one or two)
        $supptotal = \ORM::forTable('purchase')
            ->select_expr('SUM(adults + children)', 'supp')
            ->where(array(
                'completed' => 0,
                'class' => 'F',
                'status' => 'OK',
                'serviceid' => $serviceid,
            ))
            ->where_gt('seatsupplement', 0)
            ->findOne();
        $count->pendingfirstsingles = Booking::zero($supptotal->supp);

        // First suppliements remainder
        $count->remainingfirstsingles = $limits->firstsingles - $count->bookedfirstsingles - $count->pendingfirstsingles;

        // Get booked meals
        $bmeals = \ORM::forTable('purchase')
            ->select_expr('SUM(meala)', 'suma')
            ->select_expr('SUM(mealb)', 'sumb')
            ->select_expr('SUM(mealc)', 'sumc')
            ->select_expr('SUM(meald)', 'sumd')
            ->where(array(
                'completed' => 1,
                'status' => 'OK',
                'serviceid' => $serviceid,
            ))
            ->findOne();
        $count->bookedmeala = Booking::zero($bmeals->suma);
        $count->bookedmealb = Booking::zero($bmeals->sumb);
        $count->bookedmealc = Booking::zero($bmeals->sumc);
        $count->bookedmeald = Booking::zero($bmeals->sumd);

        // Get pending meals
        $pmeals = \ORM::forTable('purchase')
            ->select_expr('SUM(meala)', 'suma')
            ->select_expr('SUM(mealb)', 'sumb')
            ->select_expr('SUM(mealc)', 'sumc')
            ->select_expr('SUM(meald)', 'sumd')
            ->where(array(
                'completed' => 0,
                'status' => 'OK',
                'serviceid' => $serviceid,
            ))
            ->findOne();
        $count->pendingmeala = Booking::zero($pmeals->suma);
        $count->pendingmealb = Booking::zero($pmeals->sumb);
        $count->pendingmealc = Booking::zero($pmeals->sumc);
        $count->pendingmeald = Booking::zero($pmeals->sumd);

        // Get remaining meals
        $count->remainingmeala = $limits->meala - $count->bookedmeala - $count->pendingmeala;
        $count->remainingmealb = $limits->mealb - $count->bookedmealb - $count->pendingmealb;
        $count->remainingmealc = $limits->mealc - $count->bookedmealc - $count->pendingmealc;
        $count->remainingmeald = $limits->meald - $count->bookedmeald - $count->pendingmeald;

        // Get counts for destination limits
        $destinations = \ORM::forTable('destination')->where('serviceid', $serviceid)->findMany();
        $destinationcounts = array();
        foreach ($destinations as $destination) {
            $name = $destination->name;
            $crs = $destination->crs;
            $destinationcount = new \stdClass();
            $destinationcount->name = $name;

            // bookings for this destination
            $dtotal = \ORM::forTable('purchase')
                ->select_expr('SUM(adults + children)', 'dt')
                ->where(array(
                    'completed' => 1,
                    'destination' => $crs,
                    'status' => 'OK',
                    'serviceid' => $serviceid,
                ))
                ->findOne();
            $destinationcount->booked = Booking::zero($dtotal->dt);

            // pending bookings for this destination
            $ptotal = \ORM::forTable('purchase')
                ->select_expr('SUM(adults + children)', 'pt')
                ->where(array(
                    'completed' => 0,
                    'destination' => $crs,
                    'status' => 'OK',
                    'serviceid' => $serviceid,
                ))
                ->findOne();
            $dpcount = Booking::zero($ptotal->pt);

            // if we have a purchase object then remove any current count from pending
            if ($currentpurchase) {
                if ($currentpurchase->destination == $crs) {
                    $dpcount = $dpcount - $currentpurchase->adults - $currentpurchase->children;
                    $dpcount = $dpcount < 0 ? 0 : $dpcount;
                }
            }
            $destinationcount->pending = $dpcount;

            // limit=0 means the limit is not being used
            $dlimit = $destination->bookinglimit;
            $destinationcount->limit = $dlimit;
            if ($dlimit==0) {
                $destinationcount->remaining = '-';
            } else {
                $destinationcount->remaining = $dlimit - $destinationcount->booked - $dpcount;
            }

            $destinationcounts[$crs] = $destinationcount;
        }
        $count->destinations = $destinationcounts;

        return $count;
    }

    /**
     * Get Vue form info
     * Rambling set of data to describe possible limits and options for
     * Vue driven input forms. 
     * @param int purchaseid
     * @return object
     */
    public static function getSingleFormLimits($purchaseid) {
        $single = new \stdClass();

        // Anything that requires limited availability warning
        $limited = false;

        // Basic data
        $purchase = Booking::getPurchaseRecord($purchaseid);
        $serviceid = $purchase->serviceid;
        $service = Admin::getService($serviceid);
        $limits = Admin::getLimits($serviceid);
        $maxparty = Booking::getMaxparty($limits);
        $count = Booking::countStuff($serviceid, $purchase);

        // Booking numbers (work out booking numbers)
        $single->maxparty = (int)$maxparty;
        if ($limits->minparty) {
            $single->minparty = $limits->minparty;
        } else {
            $single->minparty = 1;
        }
        $single->minparty = 1;

        // Travel class may not be defined (yet)
        if ($purchase->class == CLASS_FIRST) {
            if ($limits->minpartyfirst) {
                $single->minparty = $limits->minpartyfirst;
            }
            if ($limits->maxpartfirst) {
                $single->maxparty = $limits->maxpartyfirst;
            }
            if ($count->remainingfirst < $single->maxparty) {
                $single->maxparty = $count->remainingfirst;
                $limited = true;
            }
        } else if ($purchase->class == CLASS_STANDARD) {
            if ($count->remainingstandard < $single->maxparty) {
                $single->maxparty = $count->remainingstandard;
                $limit = true;
            }
        }
        if ($purchase->adults) {
            $single->maxchildren = $purchase->adults + $purchase->children - 1;
            $single->showchildren = true;
        } else {
            $single->maxchildren = 0;
            $single->showchildren = false;
        }
        $single->noseats = $single->maxparty <= 0;

        $single->limited = $limited;
        $single->minparty = (int)$single->minparty;
        $single->maxparty = (int)$single->maxparty;

        return $single;
    }

    /**
     * Use count to get an idea of the progress of the bookings
     * @param int $serviceid
     * @param int percentage
     */
    public static function getProgress($serviceid) {

        $count = Booking::countStuff($serviceid);

        $total = $count->bookedfirst + $count->remainingfirst + $count->bookedstandard + $count->remainingstandard;
        $booked = $count->bookedfirst + $count->bookedstandard;

        return round($booked * 100 / $total); 
    }

    /**
     * Check if there are no remaining seats
     * @param int $serviceid
     * @return bool
     */
    public static function anySeatsRemaining($serviceid) {
        $count = Booking::countStuff($serviceid);
        return $count->remainingfirst || $count->remainingstandard;
    }

    /**
     * Find the current (session) purchase record and/or create a new one if
     * needed
     * @param object $controller 
     * @param int $serviceid - needed to create new purchase record only
     * @return object
     * @throws Exception
     */
    public static function getSessionPurchase($controller, $serviceid = 0) {

        if (Session::exists('key')) {
            $key = Session::read('key');

            // then we should have the record id and they should match
            if (Session::exists('purchaseid')) {
                $purchaseid = Session::read('purchaseid');
                $purchase = Booking::getPurchaseRecord($purchaseid);

                // if it exists then the key must match (security I think)
                if ($purchase->seskey != $key) {
                    throw new Exception('Purchase key (' . $purchase->seskey .') does not match session (' . $key . ')');
                } else {

                    // if it has a Sagepay status then something is wrong
                    if ($purchase->status) {
                        throw new Exception('This booking has already been submitted for payment, purchaseid = ' . $purchase->id);
                    }

                    // All is well. Return the record
                    $purchase->timestamp = time();
                    return $purchase;
                }
            } else {

                // if record id isn't there then this is an exception
                throw new Exception('Purchase id is missing in session');
            }
        }

        // If we get here, there is no session set up, so
        // there won't be a purchase record either

        // if no code or serviceid was supplied then we are not allowed a new one
        // ...so display expired message
        if (!$serviceid) {
            $controller->View('booking/timeout');
        }

        // Get the service
        $service = Admin::getService($serviceid);

        // create a random new key
        $key = sha1(microtime(true).mt_rand(10000,90000));

        // create the new purchase object
        $purchase = Booking::getPurchaseRecord(0, $service);

        // id should be set automagically
        $id = $purchase->id();
        Session::write('key', $key);
        Session::write('purchaseid', $id);

        // we can add the booking ref (generated from id) and key
        $purchase->seskey = $key;
        $purchase->bookingref = $_ENV['sage_prefix'] . $id;
        $purchase->save();

        return $purchase;
    }

    /**
     * Get the purchase record from database, or create a new one
     * @param int $purchaseid 0 to create new one
     * @param object $service
     * @return object purchase - existing or new
     * @throws Exception
     */
    private static function getPurchaseRecord($purchaseid, $service = null) {
        if (!$purchaseid) {
            if (!$service) {
                throw new Exception('A service object must be supplied to create new purchase');
            }
            $purchase = \ORM::forTable('purchase')->create();
            $purchase->created = time();
            $purchase->seskey = '';
            $purchase->timestamp = time();
            $purchase->serviceid = $service->id;
            $purchase->type = 0;
            $purchase->code = $service->code;
            $purchase->bookingref = '';
            $purchase->completed = 0;
            $purchase->manual = 0;
            $purchase->title = '';
            $purchase->firstname = '';
            $purchase->surname = '';
            $purchase->address1 = '';
            $purchase->address2 = '';
            $purchase->city = '';
            $purchase->county = '';
            $purchase->postcode = '';
            $purchase->phone = '';
            $purchase->email = '';
            $purchase->joining = '';
            $purchase->destination = '';
            $purchase->class = '';
            $purchase->adults = 0;
            $purchase->children = 0;
            $purchase->meala = 0;
            $purchase->mealb = 0;
            $purchase->mealc = 0;
            $purchase->meald = 0;
            $purchase->payment = 0;
            $purchase->seatsupplement = 0;
            $purchase->comment = '';
            $purchase->date = date('Y-m-d');
            $purchase->status = '';
            $purchase->statusdetail = '';
            $purchase->cardtype = '';
            $purchase->last4digits = 0;
            $purchase->bankauthcode = 0;
            $purchase->declinecode = 0;
            $purchase->emailsent = 0;
            $purchase->eticket = 0;
            $purchase->einfo = 0;
            $purchase->securitykey = '';
            $purchase->regstatus = '';
            $purchase->VPSTxId = '';
            $purchase->bookedby = '';
            $purchase->save();
        } else if (!$purchase = \ORM::forTable('purchase')->findOne($purchaseid)) {
            throw new Exception('Cannot find purchase record for id=' . $purchaseid);
        }

        return $purchase;
    }

    /**
     * Create array of choices for numeric drop-down
     * @param int $max maximum value of numeric choices
     * @param bool $none if true add 'None' in 0th place
     * @param int $min minumum value of numeric choices
     * @return array
     */
    public static function choices($max, $none, $min=1) {
        if ($none) {
            $choices = [0 => 'None'];
        } else {
            $choices = [];
        }
        for ($i=$min; $i <= $max; $i++) {
            //$choices[$i] = "$i";
            $choices[$i] = $i;
        }

        return $choices;
    }

    /**
     * Get a list of joining stations indexed by CRS
     * @param $serviceid
     * @return array stations
     * @throws Exception
     */
    public static function getJoiningStations($serviceid) {
        $joinings = \ORM::forTable('joining')->where('serviceid', $serviceid)->findMany();
        if (!$joinings) {
            throw new Exception('No joining stations found for service id = ' . $serviceid);
        }
        $stations = array();
        foreach ($joinings as $joining) {
            $stations[$joining->crs] = $joining->station;
        }

        return $stations;
    }

    /**
     * Get a list of destination stations indexed by CRS
     * @param $serviceid
     * @return array stations
     * @throws Exception
     */
    public static function getDestinationStations($serviceid) {
        $destinations = \ORM::forTable('destination')->where('serviceid', $serviceid)->findMany();
        if (!$destinations) {
            throw new Exception('No destination stations found for service id = ' . $serviceid);
        }
        $stations = array();
        foreach ($destinations as $destination) {
            $stations[$destination->crs] = $destination->name;
        }

        return $stations;
    }

    /**
     * Creates an array of destinations with extra stuff to enhance the
     * user form.
     * @param object $purchase purchase object
     * @param object $service
     * @return array complicated destination objects
     * @throws Exception
     */
    public static function getDestinationsExtra($purchase, $service) {

        // Get counts info
        $numbers = Booking::countStuff($service->id, $purchase);
        $destinationcounts = $numbers->destinations;
        $passengercount = $purchase->adults + $purchase->children;

        // get Destinations
        $destinations = \ORM::forTable('destination')->where('serviceid', $service->id)->findMany();
        if (!$destinations) {
            throw new Exception('No destinations found for service id = ' . $service->id);
        }

        // Get joining information
        $joining = \ORM::forTable('joining')->where(array(
            'serviceid' => $service->id,
            'crs' => $purchase->joining,
        ))->findOne();
        if (!$joining) {
            throw new Exception('Missing joining record for service id = ' . $service->id . ', crs = ' . $purchase->joining);
        }

        $pricebandgroupid = $joining->pricebandgroupid;
        foreach ($destinations as $destination) {
            $destinationcount = $destinationcounts[$destination->crs];
            $priceband = \ORM::forTable('priceband')->where(array(
                'pricebandgroupid' => $pricebandgroupid,
                'destinationid' => $destination->id,
            ))->findOne();
            if (!$priceband) {
                throw new Exception("No priceband for pricebandgroup id = $pricebandgroupid destination id = " . $destination->id . " service = " . $service->id);
            }
            $destination->first = $priceband->first;
            $destination->standard = $priceband->standard;
            $destination->child = $priceband->child;

            // a limit of 0 means ignore the limit
            if (($destinationcount->limit==0) or ($destinationcount->remaining>=$passengercount)) {
                $destination->available = true;
            } else {
                $destination->available = false;
            }
        }

        return $destinations;
    }

    /**
     * detect if any meals are available
     * @param object $service
     * @param object $purchase
     * @return boolean
     */
    public static function mealsAvailable($service, $purchase) {
        /*
        if ($purchase->class = 'F' && !$service->mealsinfirst) {
            return false;
        }
        if ($purchase->class = 'S' && !$service->mealsinstandard) {
            return false;
        }
        */
        return
            $service->mealavisible ||
            $service->mealbvisible ||
            $service->mealcvisible ||
            $service->mealdvisible;
    }

    /**
     * Create an array of available meals
     * along with names, price and array of choices
     * @param $service
     * @param $purchase
     * @return array
     */
    public static function mealsForm($service, $purchase) {

        // we need to know about the number
        $numbers = Booking::countStuff($service->id);

        // Get the passenger count
        $maxpassengers = $purchase->adults + $purchase->children;

        // get the joining station (to see what meals available)
        $station = Booking::getJoiningCRS($service->id, $purchase->joining);

        // Get the destination station (to see what meals available)
        $destination = Booking::getDestinationCRS($service->id, $purchase->destination);

        $letters = array('a', 'b', 'c', 'd');
        $meals = array();
        foreach ($letters as $letter) {
            $prefix = 'meal' . $letter;
            $mealname = $prefix . 'name';
            $mealvisible = $prefix . 'visible';
            $mealprice = $prefix . 'price';
            $remaining = 'remainingmeal' . $letter;

            // NB maxmeals=0 if they are sold out
            if ($service->$mealvisible && $station->$prefix && $destination->$prefix) {
                $meal = new \stdClass();
                $meal->letter = $letter;
                $meal->formname = $prefix;
                $meal->price = $service->$mealprice;
                $meal->label = $service->$mealname . "  <span class=\"labelinfo\">(&pound;$meal->price each)</span>";
                $meal->name = $service->$mealname;
                $meal->available = $station->$prefix && $destination->$prefix;
                $meal->purchase = $purchase->$prefix;
                $meal->maxmeals = $numbers->$remaining > $maxpassengers ? $maxpassengers : $numbers->$remaining;

                // precaution
                $meal->maxmeals = $meal->maxmeals < 0 ? 0 : $meal->maxmeals;
                $meal->choices = Booking::choices($meal->maxmeals, true);
                $meals[$letter] = $meal;
            }
        }

        return array_values($meals);
    }

    /**
     * Work out the price of the tour
     * This will work (optionally) for first or standard travel
     * @param object $service
     * @param object $purchase
     * @param string $class (F or S)
     * @throws Exception
     */
    public static function calculateFare($service, $purchase, $class) {

        // Need to drag everything out of the database
        $serviceid = $service->id;

        // Get basic numbers from purchase
        $adults = $purchase->adults;
        $children = $purchase->children;
        $meala = $purchase->meala;
        $mealb = $purchase->mealb;
        $mealc = $purchase->mealc;
        $meald = $purchase->meald;

        // get basic start/destination info
        $joincrs = $purchase->joining;
        $destcrs = $purchase->destination;

        // get the db records for above
        $joining = Booking::getJoiningCRS($serviceid, $joincrs);
        $destination = Booking::getDestinationCRS($serviceid, $destcrs);
        $pricebandgroupid = $joining->pricebandgroupid;
        $destinationid = $destination->id;
        $priceband = \ORM::forTable('priceband')->where(array(
            'pricebandgroupid' => $pricebandgroupid,
            'destinationid' => $destinationid,
        ))->findOne();
        if (!$priceband) {
            throw new Exception('No priceband found for pricebandgroupid = ' . $pricebandgroupid . ' destinationid = ' . $destinationid);
        }

        // we return an object with various info
        $result = new \stdClass();
        if ($class=="F") {
            $result->adultunit = $priceband->first;
            $result->childunit = $priceband->first;
            $result->adultfare = $adults * $result->adultunit;
            $result->childfare = $children * $result->childunit;
        } else {
            $result->adultunit = $priceband->standard;
            $result->childunit = $priceband->child;
            $result->adultfare = $adults * $result->adultunit;
            $result->childfare = $children * $result->childunit;
        }

        // Calculate meals
        $result->meals = $meala * $service->mealaprice +
            $mealb * $service->mealbprice +
            $mealc * $service->mealcprice +
            $meald * $service->mealdprice;

        // Calculate seat supplement
        $passengers = $adults + $children;
        $suppallowed = (($passengers==1) or ($passengers==2));
        if (($purchase->class == 'F') && $purchase->seatsupplement && $suppallowed) {
            $result->seatsupplement = $passengers * $service->singlesupplement;
        } else {
            $result->seatsupplement = 0;
        }

        // Grand total
        $result->total = $result->adultfare + $result->childfare + $result->meals + $result->seatsupplement;

        return $result;
    }

    /**
     * Get single joining record from CRS code
     * @param $serviceid
     * @param $crs
     * @return object
     * @throws Exception
     */
    public static function getJoiningCRS($serviceid, $crs) {
        $joining = \ORM::forTable('joining')->where(array(
            'serviceid' => $serviceid,
            'crs' => $crs,
        ))->findOne();
        if (!$joining) {
            throw new Exception('No joining station found for service id = ' . $serviceid . ' and CRS = ' . $crs);
        }

        return $joining;
    }

    /**
     * Get destination from CRS code
     * @param int $serviceid
     * @param string $crs
     * @return object
     * @throws Exception
     */
    public static function getDestinationCRS($serviceid, $crs) {
        $destination = \ORM::forTable('destination')->where(array(
            'serviceid' => $serviceid,
            'crs' => $crs,
        ))->findOne();
        if (!$destination) {
            throw new Exception('No destination station for for service id = ' . $serviceid . ' and CRS = ' . $crs);
        }

        return $destination;
    }

    /**
     * Find the purchase from the VendorTxCode
     * (Same as our bookingref)
     * @param string $VendorTxCode
     * @return mixed Purchase record of false if not found
     */
    public static function getPurchaseFromVendorTxCode($VendorTxCode) {
        $purchase = \ORM::forTable('purchase')->where('bookingref', $VendorTxCode)->findOne();

        return $purchase;
    }

    /**
     * Try to find existing booking from user details
     * @param object $purchase
     * @return array of purchases
     */
    public static function findOldPurchase($purchase) {
        if (!$purchase) {
            return false;
        }

        // NOte to self: replace stuff is so that postcodes match
        // Regardless of customer adding space or no space. 
        $criteria = array(
            'firstname' => $purchase->firstname,
            'surname' => $purchase->surname,
            'postcode' => str_replace(' ', '', $purchase->postcode),
        );

        $purchases = \ORM::forTable('purchase')->raw_query('
            SELECT * FROM purchase
            WHERE firstname = :firstname
            AND surname = :surname
            AND REPLACE(postcode, " ", "") = :postcode
            AND status = "OK"
            ORDER BY timestamp DESC
            LIMIT 5
        ', $criteria)->findMany();           

        // Fix up date
        foreach ($purchases as $purchase) {
            $purchase->formatteddate = date('d/m/Y', strtotime($purchase->date));
        }

        return $purchases;
    }

    /**
     * Check purchase id valid in list of purchases
     * @param int $purchaseid
     * @param array $purchases
     * @return mixed selected purchase
     * @throws \Exception
     */
    public static function checkPurchaseID($purchaseid, $purchases) {
        foreach ($purchases as $purchase) {
            if ($purchase->id == $purchaseid) {
                return $purchase;
            }
        }

        throw new \Exception("Matching purchaseid not found - " . $purchaseid);
    }

    /**
     * Update purchase with data returned from SagePay
     * @param object $purchase
     * @param array $data
     * @return purchase
     */
    public static function updatePurchase($purchase, $data) {
        $purchase->status = $data['Status'];
        $purchase->statusdetail = $data['StatusDetail'];
        $purchase->cardtype = $data['CardType'];
        $purchase->last4digits = empty($data['Last4Digits']) ? '0000' : $data['Last4Digits'];
        $purchase->bankauthcode = $data['BankAuthCode'];
        $purchase->declinecode = empty($data['DeclineCode']) ? '0000' : $data['DeclineCode'];
        $purchase->completed = 1;
        $purchase->save();

        return $purchase;
    }

}
