<?php

namespace thepurpleblob\railtour\library;

// Lifetime of incomplete purchases in seconds
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
    private function mungeService($service) {
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
     * Get all services
     * @return array
     */
    public function getServices() {
        $allservices = \ORM::forTable('service')->order_by_asc('date')->findMany();

        // Run through for (some) mods
        foreach ($allservices as $service) {
            $this->mungeService($service);
        }

        return $allservices;
    }

    /**
     * Get a single service
     * @param int serviceid
     * @return object
     */
    public function getService($id) {
        $service = \ORM::forTable('service')->findOne($id);

        if (!$service) {
            throw new Exception('Unable to find Service record for id = ' . $id);
        }

        $this->mungeService($service);

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

}