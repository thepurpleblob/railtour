<?php

namespace thepurpleblob\railtour\service;

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
            throw $this->Exception('Unable to find Service entity.');
        }

        return $service;
    }

    /**
     * Create the table to display price band group
     * @param integer $pricebandgroupid
     */
    public function createPricebandTable($pricebandgroupid) {

        // get the basic price bands
        $pricebands = \ORM::forTable('Priceband')->where('pricebandgroupid', $pricebandgroupid)->findMany();

        // iterate over these and get destinations
        // (very inefficiently)
        foreach ($pricebands as $priceband) {
            $destinationid = $priceband->destinationid;
            $destination = \ORM::forTable('Destination')->findOne($destinationid);
            $priceband->destination = $destination->nameame;
        }

        return $pricebands;
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
        $em = $this->em;

        // find joining stations that specify this group
        $joinings = $em->getRepository('SRPSBookingBundle:Joining')
            ->findByPricebandgroupid($pricebandgroup->getId());

        // if there are any then it is used
        if ($joinings) {
            return true;
        }

        return false;
    }

    /**
     * Clear the current session data and delete any expired purchases
     */
    public function cleanPurchases() {
        $em = $this->em;

        // Get the session
        $session = new Session();
//        if (!$session->isStarted()) {
//            $session->start();
//        }

        // remove the key and the purchaseid
        $session->remove('key');
        $session->remove('purchaseid');

        // get incomplete purchases older than 1 hour
        $oldtime = time() - 3600;
        $query = $em->createQuery("SELECT p FROM SRPSBookingBundle:Purchase p
            WHERE p.completed=0 AND p.timestamp < :oldtime")
            ->setParameter('oldtime', $oldtime);
        $purchases = $query->getResult();

        if ($purchases) {
            foreach ($purchases as $purchase) {
                $em->remove($purchase);
            }
            $em->flush();
        }
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
        $em = $this->em;

        // Clear incomplete purchases older than 1 hour
        $oldtime = time() - 3600;
        $query = $em->createQuery("SELECT p FROM SRPSBookingBundle:Purchase p
            WHERE p.completed=0 AND p.timestamp < :oldtime")
            ->setParameter('oldtime', $oldtime);
        $purchases = $query->getResult();
        if ($purchases) {
            foreach ($purchases as $purchase) {
                $em->remove($purchase);
            }
            $em->flush();
        }

        // Always a chance the limits don't exist yet
        $limits = $em->getRepository('SRPSBookingBundle:Limits')
            ->findOneByServiceid($serviceid);
        if (!$limits) {
            $limits = new Limits();
            $limits->setServiceid($serviceid);
            $em->persist($limits);
            $em->flush();
        }

        // Create counts entity
        $count = new Count();

        // get first class booked
        $fbquery = $em->createQuery("SELECT (SUM(p.adults)+SUM(p.children)) AS a FROM SRPSBookingBundle:Purchase p
            WHERE p.completed=1 AND p.class='F' AND p.status='OK' AND p.serviceid=$serviceid ");
        $fbtotal = $fbquery->getResult();
        $count->setBookedfirst($this->zero($fbtotal[0]['a']));

        // get first class in progress
        $fpquery = $em->createQuery("SELECT (SUM(p.adults)+SUM(p.children)) AS a FROM SRPSBookingBundle:Purchase p
            WHERE p.completed=0 AND p.class='F' AND p.serviceid=$serviceid ");
        $fptotal = $fpquery->getResult();
        $count->setPendingfirst($this->zero($fptotal[0]['a']));

        // if we have a purchase object then remove any current count from pending
        if ($currentpurchase) {
            if ($currentpurchase->getClass()=='F') {
                $pf = $count->getPendingfirst();
                $pf = $pf - $currentpurchase->getAdults() - $currentpurchase->getChildren();
                $pf = $pf < 0 ? 0 : $pf;
                $count->setPendingfirst($pf);
            }
        }

        // firct class remainder is simply
        $count->setRemainingfirst($limits->getFirst() - $count->getBookedfirst() - $count->getPendingfirst());

        // get standard class booked
        $sbquery = $em->createQuery("SELECT (SUM(p.adults)+SUM(p.children)) AS a FROM SRPSBookingBundle:Purchase p
            WHERE p.completed=1 AND p.class='S' AND p.status='OK' AND p.serviceid=$serviceid ");
        $sbtotal = $sbquery->getResult();
        $count->setBookedstandard($this->zero($sbtotal[0]['a']));

        // get standard class in progress
        $spquery = $em->createQuery("SELECT (SUM(p.adults)+SUM(p.children)) AS a FROM SRPSBookingBundle:Purchase p
            WHERE p.completed=0 AND p.class='S' AND p.serviceid=$serviceid ");
        $sptotal = $spquery->getResult();
        $count->setPendingstandard($this->zero($sptotal[0]['a']));

        // if we have a purchase object then remove any current count from pending
        if ($currentpurchase) {
            if ($currentpurchase->getClass()=='S') {
                $ps = $count->getPendingstandard();
                $ps = $ps - $currentpurchase->getAdults() - $currentpurchase->getChildren();
                $ps = $ps < 0 ? 0 : $ps;
                $count->setPendingstandard($ps);
            }
        }

        // standard class remainder is simply
        $count->setRemainingstandard($limits->getStandard() - $count->getBookedstandard() - $count->getPendingstandard());

        // get first supplements booked. Note field is a boolean and applies to
        // all persons in booking (which is only asked for parties of one or two)
        $supquery = $em->createQuery("SELECT (SUM(p.adults)+SUM(p.children)) AS a FROM SRPSBookingBundle:Purchase p
            WHERE p.completed=1 AND p.class='F' AND p.status='OK' AND p.seatsupplement>0 AND p.serviceid=$serviceid ");
        $suptotal = $supquery->getResult();
        $count->setBookedfirstsingles($this->zero($suptotal[0]['a']));

        // get first supplements in progress. Note field is a boolean and applies to
        // all persons in booking (which is only asked for parties of one or two)
        $sppquery = $em->createQuery("SELECT (SUM(p.adults)+SUM(p.children)) AS a FROM SRPSBookingBundle:Purchase p
            WHERE p.completed=0 AND p.class='F' AND p.seatsupplement=1 AND p.serviceid=$serviceid ");
        $spptotal = $sppquery->getResult();
        $count->setPendingfirstsingles($this->zero($spptotal[0]['a']));

        // First suppliements remainder
        $count->setRemainingfirstsingles($limits->getFirstsingles() - $count->getBookedfirstsingles() - $count->getPendingfirstsingles());

        // Get booked meals
        $mbquery = $em->createQuery("SELECT SUM(p.meala) AS a, SUM(p.mealb) AS b,
            SUM(p.mealc) AS c, SUM(p.meald) AS d FROM SRPSBookingBundle:Purchase p
            WHERE p.completed=1 AND p.status='OK' AND p.serviceid=$serviceid ");
        $mbtotal = $mbquery->getResult();
        $count->setBookedmeala($this->zero($mbtotal[0]['a']));
        $count->setBookedmealb($this->zero($mbtotal[0]['b']));
        $count->setBookedmealc($this->zero($mbtotal[0]['c']));
        $count->setBookedmeald($this->zero($mbtotal[0]['d']));

        // Get pending meals
        $mpquery = $em->createQuery("SELECT SUM(p.meala) AS a, SUM(p.mealb) AS b,
            SUM(p.mealc) AS c, SUM(p.meald) AS d FROM SRPSBookingBundle:Purchase p
            WHERE p.completed=0 AND p.serviceid=$serviceid ");
        $mptotal = $mpquery->getResult();
        $count->setPendingmeala($this->zero($mptotal[0]['a']));
        $count->setPendingmealb($this->zero($mptotal[0]['b']));
        $count->setPendingmealc($this->zero($mptotal[0]['c']));
        $count->setPendingmeald($this->zero($mptotal[0]['d']));

        // Get remaining meals
        //$rma = $limits->getMeala() - $count->getBookedmeala() - $count->getPendingmeala();
        //$count->setRemainingmeala($rma);
        $count->setRemainingmeala($limits->getMeala() - $count->getBookedmeala() - $count->getPendingmeala());
        $count->setRemainingmealb($limits->getMealb() - $count->getBookedmealb() - $count->getPendingmealb());
        $count->setRemainingmealc($limits->getMealc() - $count->getBookedmealc() - $count->getPendingmealc());
        $count->setRemainingmeald($limits->getMeald() - $count->getBookedmeald() - $count->getPendingmeald());

        // Get counts for destination limits
        $destinations = $em->getRepository('SRPSBookingBundle:Destination')
            ->findByServiceid($serviceid);
        $destinationcounts = array();
        foreach ($destinations as $destination) {
            $name = $destination->getName();
            $crs = $destination->getCrs();
            $destinationcount = new \stdClass();
            $destinationcount->name = $name;

            // bookings for this destination
            $dbquery = $em->createQuery("SELECT SUM(p.adults) AS a, SUM(p.children) AS c FROM SRPSBookingBundle:Purchase p
                 WHERE p.completed=1 AND p.status='OK' AND p.serviceid=$serviceid
                 AND p.destination='$crs'");
            $dbresult = $dbquery->getResult();
            $dbcount = $this->zero($dbresult[0]['a']) + $this->zero($dbresult[0]['c']);
            $destinationcount->booked = $dbcount;

            // pending bookings for this destination
            $dpquery = $em->createQuery("SELECT SUM(p.adults) AS a, SUM(p.children) AS c FROM SRPSBookingBundle:Purchase p
                 WHERE p.completed=0 AND p.serviceid=$serviceid
                 AND p.destination='$crs'");
            $dpresult = $dpquery->getResult();
            $dpcount = $this->zero($dpresult[0]['a']) + $this->zero($dpresult[0]['c']);

            // if we have a purchase object then remove any current count from pending
            if ($currentpurchase) {
                if ($currentpurchase->getDestination()==$crs) {
                    $dpcount = $dpcount - $currentpurchase->getAdults() - $currentpurchase->getChildren();
                    $dpcount = $dpcount < 0 ? 0 : $dpcount;
                }
            }
            $destinationcount->pending = $dpcount;

            // limit=0 means the limit is not being used
            $dlimit = $destination->getBookinglimit();
            $destinationcount->limit = $dlimit;
            if ($dlimit==0) {
                $destinationcount->remaining = '-';
            } else {
                $destinationcount->remaining = $dlimit - $dbcount - $dpcount;
            }

            $destinationcounts[$crs] = $destinationcount;
        }
        $count->setDestinations($destinationcounts);

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
