<?php

namespace thepurpleblob\railtour\library;

use Exception;

/**
 * Class Admin
 * @package thepurpleblob\railtour\library
 * @return array list of services
 */
class Booking extends Admin {

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
     * Get the maximum party size in theory.
     * We have to use this before we know the first/standard choice
     * (even though they can, effectively, have different limits)
     * @param $limits object
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
     * Convert a null to a zero
     * (done a lot in countStuff)
     * @param mixed $value int or null
     * @return int
     */
    protected function zero($value) {
        $result = ($value) ? $value : 0;
        return $result;
    }

    /**
     * Basic checks to ensure booking can procede
     * @param object $service
     * @param object $count
     * TODO: Fix the date shit so it works!
     * @return bool
     */
    public function canProceedWithBooking($service, $count) {
        $today = date('Y-m-d');
        $seatsavailable =
            (($count->remainingfirst > 0) or ($count->remainingstandard > 0));
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
     * Find the current (session) purchase record and/or create a new one if
     * needed
     * @param int $serviceid - needed to create new purchase record only
     * @return object
     * @throws Exception
     */
    public function getSessionPurchase($serviceid = 0) {
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
        }

        // If we get here, there is no session set up, so
        // there won't be a purchase record either

        // if no code or serviceid was supplied then we are not allowed a new one
        // ...so display expired message
        if (!$serviceid) {
            $this->controller->View('booking/timeout');
        }

        // Get the service
        $service = $this->getService($serviceid);

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
     * Get the purchase record from database, or create a new one
     * @param int $purchaseid 0 to create new one
     * @param object $service
     * @return object purchase - existing or new
     * @throws Exception
     */
    private function getPurchaseRecord($purchaseid, $service = null) {
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
}