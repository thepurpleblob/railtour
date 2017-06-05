<?php

namespace thepurpleblob\railtour\library;

// Lifetime of incomplete purchases in seconds
define('PURCHASE_LIFETIME', 3600);

use Exception;


/**
 * Class Booking
 * @package thepurpleblob\railtour\library
 * @return array list of services
 */
class Admin {

    /**
     * munge service for formatting
     * @param object $service
     * @return object
     */
    public function formatService($service) {
        $service->unixdate = strtotime($service->date);
        $service->formatteddate = date('d/m/Y', $service->unixdate);
        $service->formattedvisible = $service->visible ? 'Yes' : 'No';
        $service->formattedcommentbox = $service->commentbox ? 'Yes' : 'No';
        $service->formattedmealavisible = $service->mealavisible ? 'Yes' : 'No';
        $service->formattedmealbvisible = $service->mealbvisible ? 'Yes' : 'No';
        $service->formattedmealcvisible = $service->mealcvisible ? 'Yes' : 'No';
        $service->formattedmealdvisible = $service->mealdvisible ? 'Yes' : 'No';
        $service->formattedmealaname = $service->mealaname ? $service->mealaname : 'Meal A';
        $service->formattedmealbname = $service->mealbname ? $service->mealbname : 'Meal B';
        $service->formattedmealcname = $service->mealcname ? $service->mealcname : 'Meal C';
        $service->formattedmealdname = $service->mealdname ? $service->mealdname : 'Meal D';

        // ETicket selected
        if ($service->eticketenabled) {
            $etmode = $service->eticketforce ? 'Enabled: Forced' : 'Enabled: Optional';
        } else {
            $etmode = 'Disabled';
        }
        $service->formattedetmode = $etmode;

        return $service;
    }

    /**
     * munge services for display
     * @param array $services
     * @return array
     */
    public function formatServices($services) {
        foreach ($services as $service) {
            $this->formatService($service);
        }

        return $services;
    }

    /**
     * Get all services
     * @return array
     */
    public function getServices() {
        $allservices = \ORM::forTable('service')->order_by_asc('date')->findMany();

        return $allservices;
    }

    /**
     * Create new Service
     * @return object
     */
    public function createService() {
        $service = \ORM::for_table('service')->create();
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
        $service->commentbox = 0;
        $service->eticketenabled = 0;
        $service->eticketforce = 0;

        return $service;
    }

    /**
     * Get a single service
     * @param int serviceid (0 = new one)
     * @return object
     */
    public function getService($id = 0) {
        $service = \ORM::forTable('service')->findOne($id);

        if ($service === 0) {
            $service = $this->createService();
            return $service;
        }

        if (!$service) {
            throw new Exception('Unable to find Service record for id = ' . $id);
        }

        return $service;
    }

    /**
     * Get pricebands ordered by destinations (create any new missing ones)
     * @param int $serviceid
     * @param int $pricebandgroupid
     * @param boolean $save
     * @return array
     */
    public function getPricebands($serviceid, $pricebandgroupid, $save=true) {
        $destinations = \ORM::forTable('destination')->where('serviceid', $serviceid)->order_by_asc('destination.name')->findMany();
        if (!$destinations) {
            throw new Exception('No destinations found for serviceid = ' . $serviceid);
        }
        $pricebands = array();
        foreach ($destinations as $destination) {
            $priceband = \ORM::forTable('priceband')->where(array(
                'pricebandgroupid' => $pricebandgroupid,
                'destinationid' => $destination->id,
            ))->findOne();
            if (!$priceband) {
                $priceband = \ORM::forTable('priceband')->create();
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
     * Get destinations
     * @param int $serviceid
     * @return array
     */
    public function getDestinations($serviceid) {
        $destinations = \ORM::forTable('destination')->where('serviceid', $serviceid)->findMany();

        return $destinations;
    }

    /**
     * Get single destination
     * @param int $destinationid
     * @return object
     */
    public function getDestination($destinationid) {
        $destination = \ORM::forTable('destination')->findOne($destinationid);
        if (!$destination) {
            throw new Exception('Destination was not found id=' . $destinationid);
        }

        return $destination;
    }

    /**
     * Create new Destination
     * @param int $serviceid
     * @return object
     */
    public function createDestination($serviceid) {
        $destination = \ORM::forTable('destination')->create();
        $destination->serviceid = $serviceid;
        $destination->name = '';
        $destination->crs = '';
        $destination->description = '';
        $destination->bookinglimit = 0;

        return $destination;
    }

    /**
     * Is destination used?
     * Checks if destination can be deleted
     * @param object $destination
     * @return boolean true if used
     */
    public function isDestinationUsed($destination) {

        // find pricebands that specify this destination
        $pricebands = \ORM::forTable('priceband')->where('destinationid', $destination->id)->findMany();

        // if there are non then not used
        if (!$pricebands) {
            return false;
        }

        // otherwise, all prices MUST be 0
        foreach ($pricebands as $priceband) {
            if (($priceband->first > 0) || ($priceband->standard > 0) && ($priceband->child > 0)) {
                return true;
            }
        }

        return false;
    }

    /**
     * munge priceband group
     * @param object $pricebandgroup
     * @return object
     */
    private function mungePricebandgroup($pricebandgroup) {
        $pricebandgroupid = $pricebandgroup->id;
        $serviceid = $pricebandgroup->serviceid;
        $bandtable = $this->getPricebands($serviceid, $pricebandgroupid);
        $pricebandgroup->bandtable = $bandtable;

        return $pricebandgroup;
    }

    /**
     * Get pricebandgroups
     * @param int $serviceid
     * @return array
     */
    public function getPricebandgroups($serviceid) {
        $pricebandgroups = \ORM::forTable('pricebandgroup')->where('serviceid', $serviceid)->findMany();

        // Munge price bands
        foreach ($pricebandgroups as $pricebandgroup) {
            $this->mungePricebandgroup($pricebandgroup);
        }

        return $pricebandgroups;
    }

    /**
     * Get priceband group
     * @param int $pricebandgroupid
     * @return object
     */
    public function getPricebandgroup($pricebandgroupid) {
        $pricebandgroup = \ORM::forTable('pricebandgroup')->findOne($pricebandgroupid);

        if (!$pricebandgroup) {
            throw new Exception('Unable to find Pricebandgroup record for id = ' . $pricebandgroupid);
        }

        $this->mungePricebandgroup($pricebandgroup);

        return $pricebandgroup;
    }

    /**
     * Munge joining
     * @param object $joining
     * @return object
     */
    private function mungeJoining($joining) {
        $pricebandgroup = $this->getPricebandgroup($joining->pricebandgroupid);
        $joining->pricebandname = $pricebandgroup->name;

        return $joining;
    }

    /**
     * Get joining stations
     * @param int $serviceid
     * @return array
     */
    public function getJoinings($serviceid) {
        $joinings = \ORM::forTable('joining')->where('serviceid', $serviceid)->findMany();

        foreach ($joinings as $joining) {
            $this->mungeJoining($joining);
        }

        return $joinings;
    }

    /**
     * Get limits
     * @param int $serviceid
     * @return array
     */
    public function getLimits($serviceid) {
        $limits = \ORM::forTable('limits')->where('serviceid', $serviceid)->findOne();

        return $limits;
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
     * Format purchase for UI
     * @param object $purchase
     * @return object
     */
    public function formatPurchase($purchase) {
        $purchase->unixdate = strtotime($purchase->date);
        $purchase->formatteddate = date('d/m/Y', $purchase->unixdate);
        $purchase->statusok = $purchase->status == 'OK';

        return $purchase;
    }

    /**
     * Format list of purchases for UI
     * @param array $purchases
     * @return array
     */
    public function formatPurchases($purchases) {
        foreach ($purchases as $purchase) {
            $this->formatPurchase($purchase);
        }

        return $purchases;
    }

    /**
     * Get purchases for service
     * @param int service id
     * @parm bool $complete, only complete purchases
     * @return array
     */
    public function getPurchases($serviceid, $completed = true) {
        $dbcompleted = $completed ? 1 : 0;

        $purchases = \ORM::forTable('purchase')
            ->where(array(
                'serviceid' => $serviceid,
                'completed' => $dbcompleted,
            ))
            ->order_by_asc('timestamp')
            ->findMany();

        return $purchases;
    }

}