<?php

namespace thepurpleblob\railtour\service;

// Lifetime of incomplete purchases in seconds
define('PURCHASE_LIFETIME', 3600);

class Booking
{

    /**
     * Get/check the service
     * @param $id
     * @return mixed
     */
    public function Service($id) {
        $service = \ORM::forTable('Service')->findOne($id);

        if (!$service) {
            throw new \Exception('Unable to find Service record for id = ' . $id);
        }

        return $service;
    }

    /**
     * Get the service given the service 'code'
     */
    public function serviceFromCode($code) {
        $service = \ORM::forTable('Service')->where('code', $code)->findOne();

        if (!$service) {
            throw new \Exception('Unable to find Service record for code = ' . $code);
        }

        return $service;
    }

    /**
     * Create new Service
     */
    public function createService() {
        $service = \ORM::for_table('Service')->create();
        $service->code = '';
        $service->name = '';
        $service->description = '';
        $service->visible = true;
        $service->date = date('Y-m-d', time());
        $service->mealaname = 'Breakfast';
        $service->mealbname = 'Lunch';
        $service->mealcname = 'Dinner';
        $service->mealdname = 'Not used';
        $service->mealaprice = 0;
        $service->mealbprice = 0;
        $service->mealcprice = 0;
        $service->mealdprice = 0;
        $service->mealavisible = 0;
        $service->mealbvisible = 0;
        $service->mealcvisible = 0;
        $service->mealdvisible = 0;
        $service->singlesupplement = 10.00;
        $service->maxparty = 16;
        $service->commentbox = false;

        return $service;
    }

    /**
     * Create new Destination
     */
    public function createDestination($serviceid) {
        $destination = \ORM::forTable('Destination')->create();
        $destination->serviceid = $serviceid;
        $destination->name = '';
        $destination->crs = '';
        $destination->description = '';
        $destination->bookinglimit = 0;

        return $destination;
    }

    /**
     * Create new pricebandgroup
     */
    public function createPricebandgroup($serviceid) {
        $pricebandgroup = \ORM::forTable('Pricebandgroup')->create();
        $pricebandgroup->serviceid = $serviceid;
        $pricebandgroup->name = '';

        return $pricebandgroup;
    }

    /**
     * Create options list for pricebandgroup select dropdown(s)
     *
     */
    public function pricebandgroupOptions($pricebandgroups) {
        $options = array();
        foreach ($pricebandgroups as $pricebandgroup) {
            $options[$pricebandgroup->id] = $pricebandgroup->name;
        }

        return $options;
    }

    /**
     * Create new joining thing
     * @param $serviceid int
     * @param $pricebandgroups array
     * @return object new (empty) joining object
     */
    public function createJoining($serviceid, $pricebandgroups) {
        $joining = \ORM::forTable('Joining')->create();
        $joining->serviceid = $serviceid;
        $joining->station = '';
        $joining->crs = '';
        $joining->meala = 0;
        $joining->mealb = 0;
        $joining->mealc = 0;
        $joining->meald = 0;

        // find and set to the first pricebandgoup
        $pricebandgroup = array_shift($pricebandgroups);
        $joining->pricebandgroupid = $pricebandgroup->id;

        return $joining;
    }

    /**
     * Do pricebands exist for service
     */
    public function isPricebandsConfigured($serviceid) {

        // presumably we need at least one pricebandgroup
        $pricebandgroup_count = \ORM::forTable('Pricebandgroup')->where('serviceid', $serviceid)->count();
        if (!$pricebandgroup_count) {
            return false;
        }

        // ...and there must be some pricebands too
        $priceband_count = \ORM::forTable('Priceband')->where('serviceid', $serviceid)->count();
        if (!$priceband_count) {
            return false;
        }

        return true;
    }

    /**
     * Get pricebands ordered by destinations (create any new missing ones)
     */
    public function getPricebands($serviceid, $pricebandgroupid, $save=true) {
        $destinations = \ORM::forTable('Destination')->where('serviceid', $serviceid)->order_by_asc('destination.name')->findMany();
        if (!$destinations) {
            throw new \Exception('No destinations found for serviceid = ' . $serviceid);
        }
        $pricebands = array();
        foreach ($destinations as $destination) {
            $priceband = \ORM::forTable('Priceband')->where('destinationid', $destination->id)->findOne();
            if (!$priceband) {
                $priceband = \ORM::forTable('Priceband')->create();
                $priceband->serviceid = $serviceid;
                $priceband->pricebandgroupid = $pricebandgroupid;
                $priceband->destinationid = $destination->id;
                $priceband->first = 0;
                $priceband->standard = 0;
                $priceband->child = 0;

                // In some cases we don't want to create it (yet)
                if ($save) {
                    $priceband->save();
                }
            }

            // Add the destination name as a spurious field
            $priceband->name = $destination->name;
            $pricebands[] = $priceband;
        }

        return $pricebands;
    }

    /**
     * Create new pricebands (as required)
     */
    public function createPricebands($serviceid) {
        $pricebands = array();
        $destinations = \ORM::forTable('Destination')->where('serviceid', $serviceid)->order_by_asc('destination.name')->findMany();
        if (!$destinations) {
            throw new \Exception('No destinations found for serviceid = ' . $serviceid);
        }

        foreach ($destinations as $destination) {
            $priceband = \ORM::forTable('Priceband')->create();
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
     * (may need to create a new record
     */
    public function getLimits($serviceid) {
        $limits = \ORM::forTable('Limits')->where('serviceid', $serviceid)->findOne();

        // Its's possible that the limits for this service don't exist (yet)
        if (!$limits) {
            $limits = \ORM::forTable('Limits')->create();
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
        $today = new \DateTime('today midnight');
        $seatsavailable =
            (($count->remainingfirst > 0) or ($count->remainingstandard > 0));
        $isvisible = ($service->visible);
        $isindate = ($service->date > $today);

        return ($seatsavailable and $isvisible and $isindate);
    }

    /**
     * Is destination used?
     * Checks if destination can be deleted
     * @param object $destination
     * @return boolean true if used
     */
    public function isDestinationUsed($destination) {

        // find pricebands that specify this destination
        $pricebands = \ORM::forTable('Priceband')->where('destinationid', $destination->id)->findMany();

        // if there are non then not used
        if (!$pricebands) {
            return false;
        }

        // otherwise, all prices MUST be 0
        foreach ($pricebands as $priceband) {
            if (($priceband->first>0) or ($priceband->standard>0)
                    and ($priceband->child>0)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Is the priceband group assigned
     * in any joining station
     * @param object $pricebandgroup
     */
    public function isPricebandUsed($pricebandgroup) {

        // find joining stations that specify this group
        $joinings = \ORM::forTable('Joining')->where('pricebandgroupid', $pricebandgroup->id)->findMany();

        // if there are any then it is used
        if ($joinings) {
            return true;
        }

        return false;
    }

    /**
     * Clear incomple purchases that are time expired
     */
    public function deleteOldPurchases() {
        $oldtime = time() - PURCHASE_LIFETIME;
        \ORM::forTable('Purchase')
            ->where('completed', 0)
            ->where_gt('timestamp', $oldtime)
            ->delete_many();
    }

    /**
     * Clear the current session data and delete any expired purchases
     */
    public function cleanPurchases() {

        // TODO (fix) remove the key and the purchaseid
        unset($_SESSION['key']);
        unset($_SESSION['purchaseid']);

        // get incomplete purchases
        $this->deleteOldPurchases();
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
                    throw new \Exception('No pricebandgroup mapping exists for id = ' . $joining->pricebandgroupid);
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
                    throw new \Exception('No pricebandgroup mapping exists for id = ' . $priceband->pricebandgroupid);
                }
                $newpriceband = \ORM::forTable('priceband')->create();
                $this->duplicateRecord($priceband, $newpriceband);
                $newpriceband->serviceid = $newserviceid;
                if (empty($destmap[$priceband->destinationid])) {
                    throw new \Exception('No destination mapping exists for id = ' . $priceband->destinationid);
                }
                $newpriceband->destinationid = $destmap[$priceband->destinationid];
                if (empty($pbmap[$priceband->pricebandgroupid])) {
                    throw new \Exception('No pricebandgroup mapping exists for id = ' . $priceband->pricebandgroupid);
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
        if (\ORM::forTable('purchase')->where('serviceid', $serviceid)->count()) {
            throw new \Exception('Trying to delete service with purchases. id = ' . $serviceid);
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
     * Find the current purchase record and/or create a new one if
     * needed
     */
    public function getPurchase($serviceid=0, $code='', $bookingrefprefix='') {
        $em = $this->em;

        // See if the purchase session attribute exists
        $session = new Session();
//        if (!$session->isStarted()) {
        $session->start();
//        }
        $session->migrate();
        if ($key = $session->get('key')) {

            // then we should have the record id and they should match
            if ($purchaseid = $session->get('purchaseid')) {
                $purchase = $em->getRepository('SRPSBookingBundle:Purchase')
                    ->find($purchaseid);

                // If no purchase record despite id
                if (!$purchase) {
                    throw new \Exception('Purchase record not found');
                }

                // if it exists then the key must match (security I think)
                if ($purchase->getSesKey() != $key) {
                    throw new \Exception('Purchase key does not match session');
                } else {

                    // if it has a sagapay status then something is wrong
                    if ($purchase->getStatus()) {
                        throw new \Exception('This booking has already been submitted for payment');
                    }

                    // All is well. Return the record
                    $purchase->setTimestamp(time());
                    return $purchase;
                }
            } else {

                 // if record id isn't there then this is an exception
                throw new \Exception('Purchase id is missing in session');
            }
        }

        // If we get here, there is no session set up, so
        // there won't be a purchase record either

        // if no code or serviceid was supplied then we are not allowed a new one
        if (empty($code) or empty($serviceid)) {
            throw new \Exception("The purchase record was not found (code='$code', id=$serviceid, key='$key')");
        }


        // create a random new key
        $key = sha1(microtime(true).mt_rand(10000,90000));

        // create the new purchase object
        $purchase = new Purchase();
        $purchase->setServiceid($serviceid);
        $purchase->setSeskey($key);
        $purchase->setCode($code);
        $purchase->setCreated(time());
        $purchase->setTimestamp(time());

        // and persist it
        $em->persist($purchase);
        $em->flush();

        // id should be set automagically
        $id = $purchase->getId();
        $session->set('key', $key);
        $session->set('purchaseid', $id);

        // we can add the booking ref (generated from id) - it should get
        // just need another persist to make sure
        $purchase->setBookingref($bookingrefprefix . $id);
        $em->persist($purchase);
        $em->flush();

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
        $fbtotal = \ORM::forTable('Purchase')
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
        $fptotal = \ORM::forTable('Purchase')
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
        $sbtotal = \ORM::forTable('Purchase')
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
        $sptotal = \ORM::forTable('Purchase')
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
            if ($currentpurchase->getClass()=='S') {
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
        $suptotal = \ORM::forTable('Purchase')
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
        $supptotal = \ORM::forTable('Purchase')
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
        $bmeals = \ORM::forTable('Purchase')
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
        $pmeals = \ORM::forTable('Purchase')
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
        $destinations = \ORM::forTable('Destination')->where('serviceid', $serviceid)->findMany();
        $destinationcounts = array();
        foreach ($destinations as $destination) {
            $name = $destination->name;
            $crs = $destination->crs;
            $destinationcount = new \stdClass();
            $destinationcount->name = $name;

            // bookings for this destination
            $dtotal = \ORM::forTable('Purchase')
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
            $ptotal = \ORM::forTable('Purchase')
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
        $em = $this->em;

        // Need to drag everything out of the database
        $serviceid = $service->getId();

        // Get basic numbers from purchase
        $adults = $purchase->getAdults();
        $children = $purchase->getChildren();
        $meala = $purchase->getMeala();
        $mealb = $purchase->getMealb();
        $mealc = $purchase->getMealc();
        $meald = $purchase->getMeald();

        // get basic start/destination info
        $join = $purchase->getJoining();
        $dest= $purchase->getDestination();

        // get the db records for above
        $joining = $em->getRepository('SRPSBookingBundle:Joining')
            ->findOneBy(array('crs'=>$join, 'serviceid'=>$serviceid));
        $destination = $em->getRepository('SRPSBookingBundle:Destination')
            ->findOneBy(array('crs'=>$dest, 'serviceid'=>$serviceid));
        $pricebandgroupid = $joining->getPricebandgroupid();
        $destinationid = $destination->getId();
        $priceband = $em->getRepository('SRPSBookingBundle:Priceband')
            ->findOneBy(array('pricebandgroupid'=>$pricebandgroupid, 'destinationid'=>$destinationid));

        // we return an object with various info
        $result = new \stdClass();
        if ($class=="F") {
            $result->adultunit = $priceband->getFirst();
            $result->childunit = $priceband->getFirst();
            $result->adultfare = $adults * $result->adultunit;
            $result->childfare = $children * $result->childunit;
        } else {
            $result->adultunit = $priceband->getStandard();
            $result->childunit = $priceband->getChild();
            $result->adultfare = $adults * $result->adultunit;
            $result->childfare = $children * $result->childunit;
        }

        // Calculate meals
        $result->meals = $meala * $service->getMealaprice() +
            $mealb * $service->getMealbprice() +
            $mealc * $service->getMealcprice() +
            $meald * $service->getMealdprice();

        // Calculate seat supplement
        $passengers = $adults + $children;
        $suppallowed = (($passengers==1) or ($passengers==2));
        if (($purchase->getClass()=='F') and $purchase->isSeatsupplement() and $suppallowed) {
            $result->seatsupplement = $passengers * $service->getSinglesupplement();
        } else {
            $result->seatsupplement = 0;
        }

        // Grand total
        $result->total = $result->adultfare + $result->childfare + $result->meals + $result->seatsupplement;

        return $result;
    }

    /**
     * detect if any meals are available
     *
     */
    public function mealsAvailable($service) {
        return
            $service->isMealavisible() ||
            $service->isMealbvisible() ||
            $service->isMealcvisible() ||
            $service->isMealdvisible()
            ;
    }

    /**
     * Returns object with all the Sage stuff therein
     */
    public function getSage($service, $purchase) {
        $em = $this->em;

        $sage = new \stdClass();

        $sage->submissionurl = '';
        $sage->login = '';
        $sage->crypt = '';

        return $sage;
    }

}
