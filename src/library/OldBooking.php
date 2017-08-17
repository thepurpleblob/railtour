<?php

namespace thepurpleblob\railtour\library;

// Lifetime of incomplete purchases in seconds
use Exception;

/**
 * Class Booking
 * @package thepurpleblob\railtour\library
 */
class Booking
{

    protected $controller;

    /**
     * Booking constructor.
     * @param $controller
     */
    public function __construct($controller) {
        $this->controller = $controller;
    }

    /**
     * Get/check the service
     * @param $id
     * @return mixed
     * @throws Exception
     */
    public function Service($id) {
        $service = \ORM::forTable('service')->findOne($id);

        if (!$service) {
            throw new Exception('Unable to find Service record for id = ' . $id);
        }

        return $service;
    }

    /**
     * Get the service given the service 'code'
     * @param string $code - booking code
     * @return mixed
     * @throws Exception
     */
    public function serviceFromCode($code) {
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
     * Get services available to book
     */
    public function availableServices() {

        // Get 'likely' candidates
        $potentialservices = \ORM::for_table('service')->where('visible', true)->findMany();

        // We need to do more checks to see if it is really available
        $services = array();
        foreach ($potentialservices as $service) {
            $count = $this->countStuff($service->id);
            if ($this->canProceedWithBooking($service, $count)) {
                $services[$service->id] = $service;
            }
        }

        return $services;
    }


    /**
     * Create new pricebandgroup
     */
    public function createPricebandgroup($serviceid) {
        $pricebandgroup = \ORM::forTable('pricebandgroup')->create();
        $pricebandgroup->serviceid = $serviceid;
        $pricebandgroup->name = '';

        return $pricebandgroup;
    }




    /**
     * Create new pricebands (as required)
     */
    public function createPricebands($serviceid) {
        $pricebands = array();
        $destinations = \ORM::forTable('destination')->where('serviceid', $serviceid)->order_by_asc('destination.name')->findMany();
        if (!$destinations) {
            throw new Exception('No destinations found for serviceid = ' . $serviceid);
        }

        foreach ($destinations as $destination) {
            $priceband = \ORM::forTable('priceband')->create();
            $priceband->name = $destination->name;
            $priceband->serviceid = $serviceid;
            $priceband->destinationid = $destination->id;
            $priceband->first = 0;
            $priceband->standard = 0;
            $priceband->child = 0;
            $pricebands[] = $priceband;
        }

        return $pricebands;
    }

    /**
     * Get limits
     * (may need to create a new record)
     * @param $serviceid
     */
    public function getLimits($serviceid) {
        $limits = \ORM::forTable('limits')->where('serviceid', $serviceid)->findOne();

        // Its's possible that the limits for this service don't exist (yet)
        if (!$limits) {
            $limits = \ORM::forTable('limits')->create();
            $limits->serviceid = $serviceid;
            $limits->first = 0;
            $limits->standard = 0;
            $limits->firstsingles = 0;
            $limits->meala = 0;
            $limits->mealb = 0;
            $limits->mealc = 0;
            $limits->meald = 0;
            $limits->maxparty = 0;
            $limits->maxpartyfirst = 0;
            $limits->save();
        }

        return $limits;
    }

    /**
     * Get the maximum party size in theory.
     * We have to use this before we know the first/standard choice
     * (even though they can, effectively, have different limits)
     * @param $limits limits object
     * @return int
     */
    public function getMaxparty($limits) {
        $maxparty = $limits->maxparty;
        if ($limits->maxpartyfirst and ($limits->maxpartyfirst > $maxparty)) {
            $maxparty = $limits->maxpartyfirst;
        }

        return $maxparty;
    }

    /**
     * Basic checks to ensure booking can procede
     * TODO: Fix the date shit so it works!
     */
    public function canProceedWithBooking($service, $count) {
        $today = date('Y-m-d');
        $seatsavailable =
            (($count->remainingfirst > 0) or ($count->remainingstandard > 0));
        $isvisible = ($service->visible);
        $isindate = ($service->date > $today);

        return ($seatsavailable and $isvisible and $isindate);
    }

    public function getDestination($serviceid, $crs) {
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
     * Get a list of joining stations indexed by CRS
     * @param $serviceid
     * @return array stations
     * @throws Exception
     */
    public function getJoiningStations($serviceid) {
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
     * Get single joining record
     * @param $serviceid
     * @param $crs
     * @return object
     * @throws Exception
     */
    public function getJoining($serviceid, $crs) {
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
     * Get a list of destination stations indexed by CRS
     * @param $serviceid
     * @return array stations
     * @throws Exception
     */
    public function getDestinationStations($serviceid) {
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
     * @param $purchase purchase object
     * @return array complicated destination objects
     * @throws Exception
     */
    public function getDestinationsExtra($purchase, $service) {

        // Get counts info
        $numbers = $this->countStuff($service->id, $purchase);
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
     * Clear incomplete purchases that are time expired
     */
    public function deleteOldPurchases() {
        $oldtime = time() - PURCHASE_LIFETIME;
        \ORM::forTable('purchase')
            ->where('completed', 0)
            ->where_lt('timestamp', $oldtime)
            ->delete_many();

        // IF we've deleted the current purchase then we have
        // an interesting problem!

        // See if the current purchase still exists
        if (isset($_SESSION['purchaseid'])) {
            $purchaseid = $_SESSION['purchaseid'];
            $purchase = \ORM::forTable('purchase')->findOne($purchaseid);
            if (!$purchase) {
                unset($_SESSION['key']);
                unset($_SESSION['purchaseid']);

                // Redirect out of here
                $this->controller->View('booking/timeout.html.twig');
            }
        }
    }


    /**
     * Duplicates a db record
     */
    private function duplicateRecord($from, $to) {
        $fields = $from->as_array();
        unset($fields['id']);
        foreach ($fields as $name => $value) {
            $to->$name = $value;
        }

        return $to;
    }

    /**
     * duplicate a complete service and return new service
     */
    public function duplicate($service) {

        $serviceid = $service->id;

        // duplicate service
        $newservice = \ORM::forTable('service')->create();
        $this->duplicateRecord($service, $newservice);
        $newservice->code = "CHANGE";
        $newservice->date = date("Y-m-d");
        $newservice->visible = 0;
        $newservice->save();
        $newserviceid = $newservice->id();

        // duplicate destinations
        // create a map of old to new ids
        $destmap = array();
        $destinations = \ORM::forTable('destination')->where('serviceid', $serviceid)->findMany();
        if ($destinations) {
            foreach ($destinations as $destination) {
                $newdestination = \ORM::forTable('destination')->create();
                $this->duplicateRecord($destination, $newdestination);
                $newdestination->serviceid = $newserviceid;
                $newdestination->save();
                $destmap[$destination->id] = $newdestination->id();
            }
        }

        // duplicate pricebandgroup
        // create a map of old to new ids
        $pbmap = array();
        $pricebandgroups = \ORM::forTable('pricebandgroup')->where('serviceid', $serviceid)->findMany();
        if ($pricebandgroups) {
            foreach ($pricebandgroups as $pricebandgroup) {
                $newpricebandgroup = \ORM::forTable('pricebandgroup')->create();
                $newpricebandgroup->serviceid = $newserviceid;
                $newpricebandgroup->name = $pricebandgroup->name;
                $newpricebandgroup->save();
                $pbmap[$pricebandgroup->id] = $newpricebandgroup->id();
            }
        }

        // duplicate joining
        $joinings = \ORM::forTable('joining')->where('serviceid', $serviceid)->findMany();
        if ($joinings) {
            foreach ($joinings as $joining) {
                $newjoining = \ORM::forTable('joining')->create();
                $this->duplicateRecord($joining, $newjoining);
                $newjoining->serviceid = $newserviceid;
                if (empty($pbmap[$joining->pricebandgroupid])) {
                    throw new Exception('No pricebandgroup mapping exists for id = ' . $joining->pricebandgroupid);
                }
                $newjoining->pricebandgroupid = $pbmap[$joining->pricebandgroupid];
                $newjoining->save();
            }
        }

        // duplicate pricebands
        $pricebands = \ORM::forTable('priceband')->where('serviceid', $serviceid)->findMany();
        if ($pricebands) {
            foreach ($pricebands as $priceband) {
                if (empty($pbmap[$priceband->pricebandgroupid])) {
                    throw new Exception('No pricebandgroup mapping exists for id = ' . $priceband->pricebandgroupid);
                }
                $newpriceband = \ORM::forTable('priceband')->create();
                $this->duplicateRecord($priceband, $newpriceband);
                $newpriceband->serviceid = $newserviceid;
                if (empty($destmap[$priceband->destinationid])) {
                    throw new Exception('No destination mapping exists for id = ' . $priceband->destinationid);
                }
                $newpriceband->destinationid = $destmap[$priceband->destinationid];
                if (empty($pbmap[$priceband->pricebandgroupid])) {
                    throw new Exception('No pricebandgroup mapping exists for id = ' . $priceband->pricebandgroupid);
                }
                $newpriceband->pricebandgroupid = $pbmap[$priceband->pricebandgroupid];
                $newpriceband->save();
            }
        }

        // duplicate limits
        $limits = \ORM::forTable('limits')->where('serviceid', $serviceid)->findOne();
        if ($limits) {
            $newlimits = \ORM::forTable('limits')->create();
            $this->duplicateRecord($limits, $newlimits);
            $newlimits->serviceid = $newserviceid;
            $newlimits->save();
        }

        return $newservice;
    }

    /**
     * Delete complete service
     */
    public function deleteService($service) {

        // Check there are no purchases. We should not have got here if there
        // are, but we'll check anyway
        if (\ORM::forTable('purchase')->where('serviceid', $service->id)->count()) {
            throw new Exception('Trying to delete service with purchases. id = ' . $service->id);
        }

        $serviceid = $service->id;

        // Delete limits
        \ORM::forTable('limits')->where('serviceid', $serviceid)->delete_many();

        // Delete pricebands
        \ORM::forTable('priceband')->where('serviceid', $serviceid)->delete_many();

        // Delete joining stations
        \ORM::forTable('joining')->where('serviceid', $serviceid)->delete_many();

        // Delete pricebandgroups
        \ORM::forTable('pricebandgroup')->where('serviceid', $serviceid)->delete_many();

        // Delete destinations
        \ORM::forTable('destination')->where('serviceid', $serviceid)->delete_many();

        // Finally, delete the service
        $service->delete();
    }

    /**
     * Get the purchase record from database, or create a new one
     * @param int $purchaseid 0 to create new one
     * @param object
     * @return object purchase - existing or new
     */
    public function getPurchaseRecord($purchaseid, $service = null) {
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
     * Find the current purchase record and/or create a new one if
     * needed
     */
    public function getPurchase($serviceid=0) {
        global $CFG;

        if (isset($_SESSION['key'])) {
            $key = $_SESSION['key'];

            // then we should have the record id and they should match
            if (isset($_SESSION['purchaseid'])) {
                $purchaseid = $_SESSION['purchaseid'];
                $purchase = $this->getPurchaseRecord($purchaseid);

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
        } else {
            $key = '';
        }

        // If we get here, there is no session set up, so
        // there won't be a purchase record either

        // if no code or serviceid was supplied then we are not allowed a new one
        // ...so display expired message
        if (!$serviceid) {
            $this->controller->View('booking/timeout.html.twig');
        }

        // Get the service
        $service = $this->Service($serviceid);

        // create a random new key
        $key = sha1(microtime(true).mt_rand(10000,90000));

        // create the new purchase object
        $purchase = $this->getPurchaseRecord(0, $service);

        // id should be set automagically
        $id = $purchase->id();
        $_SESSION['key'] = $key;
        $_SESSION['purchaseid'] = $id;

        // we can add the booking ref (generated from id) and key
        $purchase->seskey = $key;
        $purchase->bookingref = $CFG->sage_prefix . $id;
        $purchase->save();

        return $purchase;
    }

    /**
     * Find the purchase from the VendorTxCode
     * (Same as our bookingref)
     * @param string $VendorTxCode
     * @return mixed Purchase record of false if not found
     */
    public function getPurchaseFromVendorTxCode($VendorTxCode) {
        $purchase = \ORM::forTable('purchase')->where('bookingref', $VendorTxCode)->findOne();

        return $purchase;
    }

    /**
     * Convert a null to a zero
     * (done a lot in countStuff)
     */
    private function zero($value) {
        $result = ($value) ? $value : 0;
        return $result;
    }

    /**
     * Count the purchases and work out what's left. Major PITA this
     */
    public function countStuff($serviceid, $currentpurchase=null) {

        // get incomplete purchases
        $this->deleteOldPurchases();

        // Always a chance the limits don't exist yet
        $limits = $this->getLimits($serviceid);

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
        $count->bookedfirst = $this->zero($fbtotal->fb);

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
        $count->pendingfirst = $this->zero($fptotal->fp);

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
        $count->bookedstandard = $this->zero($sbtotal->sb);

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
        $count->pendingstandard = $this->zero($sptotal->sp);

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
        $count->bookedfirstsingles = $this->zero($suptotal->sup);

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
        $count->pendingfirstsingles = $this->zero($supptotal->supp);

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
        $count->bookedmeala = $this->zero($bmeals->suma);
        $count->bookedmealb = $this->zero($bmeals->sumb);
        $count->bookedmealc = $this->zero($bmeals->sumc);
        $count->bookedmeald = $this->zero($bmeals->sumd);

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
        $count->pendingmeala = $this->zero($pmeals->suma);
        $count->pendingmealb = $this->zero($pmeals->sumb);
        $count->pendingmealc = $this->zero($pmeals->sumc);
        $count->pendingmeald = $this->zero($pmeals->sumd);

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
            $destinationcount->booked = $this->zero($dtotal->dt);

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
            $dpcount = $this->zero($ptotal->pt);

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
     * Work out the price of the tour
     * This will work (optionally) for first or standard travel
     * @param object $service
     * @param object $purchase
     * @param string $class (F or S)
     */
    public function calculateFare($service, $purchase, $class) {

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
        $join = $purchase->joining;
        $dest= $purchase->destination;

        // get the db records for above
        $joining = $this->getJoining($serviceid, $join);
        $destination = $this->getDestination($serviceid, $dest);
        $pricebandgroupid = $joining->pricebandgroupid;
        $destinationid = $destination->id;
        $priceband = \ORM::forTable('priceband')->where(array(
            'pricebandgroupid' => $pricebandgroupid,
            'destinationid' => $destinationid,
        ))->findOne();
        if (!$priceband) {
            throw new Exception('No priceband found for pricebandgroup id = ' . $pricebandgroupid . ' destinationid = ' . $destinationid);
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
     * detect if any meals are available
     * @return boolean
     */
    public function mealsAvailable($service) {
        return
            $service->mealavisible ||
            $service->mealbvisible ||
            $service->mealcvisible ||
            $service->mealdvisible
            ;
    }

    /**
     * Create an array of available meals
     * along with names, price and array of choices
     * @param $service
     * @param $purchase
     * @return array
     */
    public function mealsForm($service, $purchase) {

        // we need to know about the number
        $numbers = $this->countStuff($service->id);

        // Get the passenger count
        $maxpassengers = $purchase->adults + $purchase->children;

        // get the joining station (to see what meals available)
        $station = $this->getJoining($service->id, $purchase->joining);

        $letters = array('a', 'b', 'c', 'd');
        $meals = array();
        foreach ($letters as $letter) {
            $prefix = 'meal' . $letter;
            $mealname = $prefix . 'name';
            $mealvisible = $prefix . 'visible';
            $mealprice = $prefix . 'price';
            $remaining = 'remainingmeal' . $letter;

            // NB maxmeals=0 if they are sold out
            if ($service->$mealvisible) {
                $meal = new \stdClass();
                $meal->letter = $letter;
                $meal->formname = $prefix;
                $meal->price = $service->$mealprice;
                $meal->label = $service->$mealname . "  <span class=\"labelinfo\">(&pound;$meal->price each)</span>";
                $meal->name = $service->$mealname;
                $meal->available = $station->$prefix;
                $meal->purchase = $purchase->$prefix;
                $meal->maxmeals = $numbers->$remaining > $maxpassengers ? $maxpassengers : $numbers->$remaining;

                // precaution
                $meal->maxmeals = $meal->maxmeals < 0 ? 0 : $meal->maxmeals;
                $meal->choices = $this->choices($meal->maxmeals, true);
                $meals[$letter] = $meal;
            }
        }

        return $meals;
    }

    /**
     * Create array of choices for numeric drop-down
     * @param $max maximum value of numeric choices
     * @param $none if true add 'None' in 0th place
     * @return array
     */
    public function choices($max, $none) {
        if ($none) {
            $choices = array(0 => 'None');
        } else {
            $choices = array();
        }
        for ($i=1; $i <= $max; $i++) {
            $choices[$i] = "$i";
        }

        return $choices;
    }

    /**
     * Update purchase with data returned from SagePay
     * @param object $purchase
     * @param array $data
     */
    public function updatePurchase($purchase, $data) {
        $purchase->status = $data['Status'];
        $purchase->statusdetail = $data['StatusDetail'];
        $purchase->cardtype = $data['CardType'];
        $purchase->last4digits = $data['Last4Digits'];
        $purchase->bankauthcode = $data['BankAuthCode'];
        $purchase->declinecode = $data['DeclineCode'];
        $purchase->completed = 1;
        $purchase->save();
    }

}