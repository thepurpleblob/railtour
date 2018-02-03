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
                $this->controller->View('booking/timeout.mustache');
            }
        }
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











}
