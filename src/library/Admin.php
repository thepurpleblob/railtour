<?php

namespace thepurpleblob\railtour\library;

// Lifetime of incomplete purchases in seconds
define('PURCHASE_LIFETIME', 14400);

use Exception;
use ORM;
use thepurpleblob\core\Session;

/**
 * Class Admin
 * @package thepurpleblob\railtour\library
 * @return array list of services
 */
class Admin {

    private $stations = null;

    /**
     * Set up the stations json data
     * Need to call this first, if you want to use this data
     */
    /*
    public function initialiseStations() {

        $stationsjson = file_get_contents($_ENV['dirroot'] . '/src/assets/json/stations.json');
        $locations = json_decode($stationsjson);
        $locations = $locations->locations;
        $crs = array();
        foreach ($locations as $location) {
            $crs[$location->crs] = $location;
        }
        $this->stations = $crs;
    }
    */

    /**
     * Find station/location from crs
     * @param string $crs
     * @return object
     */
    public static function getCRSLocation($crs) {
        $station = \ORM::forTable('station')->where('crs', $crs)->findOne();
        if ($station) {
            return $station->name;
        } else {
            return null;
        }
    }

    /**
     * munge service for formatting
     * @param object $service
     * @return object
     */
    public static function formatService($service) {
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
        $service->formattedmealsinfirst = $service->mealsinfirst ? 'Yes' : 'No';
        $service->formattedmealsinstandard = $service->mealsinstandard ? 'Yes' : 'No';

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
    public static function formatServices($services) {
        foreach ($services as $service) {
            Admin::formatService($service);
        }

        return $services;
    }

    /**
     * Get all services
     * @return array
     */
    public static function getServices() {
        $allservices = ORM::forTable('service')->order_by_asc('date')->findMany();

        return $allservices;
    }

    /**
     * Get default year
     * @return int year
     */
    public static function getFilteryear() {
        $allservices = Admin::getServices();
        $maxyear = 0;
        foreach ($allservices as $service) {
            $year = substr($service->date, 0, 4);
            if ($year > $maxyear) {
                $maxyear = $year;
            }
        }

        return $maxyear;
    }

    /**
     * Create new Service
     * @return object
     */
    public static function createService() {
        $service = ORM::for_table('service')->create();
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
        $service->singlesupplement = 15.00;
        $service->maxparty = 16;
        $service->commentbox = 0;
        $service->eticketenabled = 0;
        $service->eticketforce = 0;
        $service->mealsinfirst = 1;
        $service->mealsinstandard = 1;

        return $service;
    }

    /**
     * Get a single service
     * @param int serviceid (0 = new one)
     * @return object
     * @throws Exception
     */
    public static function getService($id = 0) {
        $service = ORM::forTable('service')->findOne($id);

        if ($service === 0) {
            $service = Admin::createService();
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
     * @throws Exception
     */
    public static function getPricebands($serviceid, $pricebandgroupid, $save=true) {
        $destinations = ORM::forTable('destination')->where('serviceid', $serviceid)->order_by_asc('destination.name')->findMany();
        if (!$destinations) {
            throw new Exception('No destinations found for serviceid = ' . $serviceid);
        }
        $pricebands = array();
        foreach ($destinations as $destination) {
            $priceband = ORM::forTable('priceband')->where(array(
                'pricebandgroupid' => $pricebandgroupid,
                'destinationid' => $destination->id,
            ))->findOne();
            if (!$priceband) {
                $priceband = ORM::forTable('priceband')->create();
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
     * Is the priceband group assigned
     * in any joining station
     * @param object $pricebandgroup
     * @return bool
     */
    public static function isPricebandUsed($pricebandgroup) {

        // find joining stations that specify this group
        $joinings = ORM::forTable('joining')->where('pricebandgroupid', $pricebandgroup->id)->findMany();

        // if there are any then it is used
        if ($joinings) {
            return true;
        }

        return false;
    }

    /**
     * Get destinations
     * @param int $serviceid
     * @return array
     */
    public static function getDestinations($serviceid) {
        $destinations = ORM::forTable('destination')->where('serviceid', $serviceid)->findMany();

        return $destinations;
    }

    /**
     * Get single destination
     * @param int $destinationid
     * @return object
     * @throws Exception
     */
    public static function getDestination($destinationid) {
        $destination = ORM::forTable('destination')->findOne($destinationid);
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
    public static function createDestination($serviceid) {
        $destination = ORM::forTable('destination')->create();
        $destination->serviceid = $serviceid;
        $destination->name = '';
        $destination->crs = '';
        $destination->description = '';
        $destination->bookinglimit = 0;
        $destination->meala = 1;
        $destination->mealb = 1;
        $destination->mealc = 1;
        $destination->meald = 1;

        return $destination;
    }

    /**
     * Delete a destination
     * Note, this will also delete associated priceband data
     * @param int $destinationid
     * @return int
     * @throws Exception
     */
    public static function deleteDestination($destinationid) {
        $destination = Admin::getDestination($destinationid);
        $serviceid = $destination->serviceid;
        if (!Admin::isDestinationUsed($destination)) {

            // delete pricebands associated with this
            ORM::for_table('priceband')->where('destinationid', $destinationid)->delete_many();

            // delete the destination
            $destination->delete();
        }

        return $serviceid;
    }

    /**
     * Is destination used?
     * Checks if destination can be deleted
     * @param object $destination
     * @return boolean true if used
     */
    public static function isDestinationUsed($destination) {

        // find pricebands that specify this destination
        $pricebands = ORM::forTable('priceband')->where('destinationid', $destination->id)->findMany();

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
     * @throws Exception
     */
    private static function mungePricebandgroup($pricebandgroup) {
        $pricebandgroupid = $pricebandgroup->id;
        $serviceid = $pricebandgroup->serviceid;
        $bandtable = Admin::getPricebands($serviceid, $pricebandgroupid);
        $pricebandgroup->bandtable = $bandtable;

        return $pricebandgroup;
    }

    /**
     * @param array $pricebandgroups
     * @return array
     * @throws Exception
     */
    public static function mungePricebandgroups($pricebandgroups) {
        foreach ($pricebandgroups as $pricebandgroup) {
            Admin::mungePricebandgroup($pricebandgroup);
        }

        return $pricebandgroups;
    }

    /**
     * Get pricebandgroups
     * @param int $serviceid
     * @return array
     */
    public static function getPricebandgroups($serviceid) {
        $pricebandgroups = ORM::forTable('pricebandgroup')->where('serviceid', $serviceid)->findMany();

        return $pricebandgroups;
    }

    /**
     * Get priceband group
     * @param int $pricebandgroupid
     * @return object
     * @throws Exception
     */
    public static function getPricebandgroup($pricebandgroupid) {
        $pricebandgroup = ORM::forTable('pricebandgroup')->findOne($pricebandgroupid);

        if (!$pricebandgroup) {
            throw new Exception('Unable to find Pricebandgroup record for id = ' . $pricebandgroupid);
        }

        return $pricebandgroup;
    }

    /**
     * Create new pricebandgroup
     * @param int $serviceid
     * @return object pricebandgroup
     */
    public static function createPricebandgroup($serviceid) {
        $pricebandgroup = ORM::forTable('pricebandgroup')->create();
        $pricebandgroup->serviceid = $serviceid;
        $pricebandgroup->name = '';

        return $pricebandgroup;
    }


    /**
     * Delete priceband group
     * @param int pricebandgroupid
     * @return int serviceid
     * @throws Exception
     */
    public static function deletePricebandgroup($pricebandgroupid) {
        $pricebandgroup = Admin::getPricebandgroup($pricebandgroupid);
        $serviceid = $pricebandgroup->serviceid;
        if (!Admin::isPricebandUsed($pricebandgroup)) {

            // Remove pricebands associated with this group
            ORM::forTable('priceband')->where('pricebandgroupid', $pricebandgroupid)->deleteMany();

            $pricebandgroup->delete();
        }

        return $serviceid;
    }

    /**
     * Create options list for pricebandgroup select dropdown(s)
     * @param array $pricebandgroups
     * @return array
     */
    public static function pricebandgroupOptions($pricebandgroups) {
        $options = array();
        foreach ($pricebandgroups as $pricebandgroup) {
            $options[$pricebandgroup->id] = $pricebandgroup->name;
        }

        return $options;
    }

    /**
     * Munge joining
     * @param object $joining
     * @return object
     * @throws Exception
     */
    private static function mungeJoining($joining) {
        $pricebandgroup = Admin::getPricebandgroup($joining->pricebandgroupid);
        $joining->pricebandname = $pricebandgroup->name;

        return $joining;
    }

    /**
     * Munge joinings
     * @param array $joinings
     * @return array
     * @throws Exception
     */
    public static function mungeJoinings($joinings) {
        foreach ($joinings as $joining) {
            Admin::mungeJoining($joining);
        }

        return $joinings;
    }

    /**
     * Get joining stations
     * @param int $serviceid
     * @return array
     * @throws Exception
     */
    public static function getJoinings($serviceid) {
        $joinings = ORM::forTable('joining')->where('serviceid', $serviceid)->findMany();

        foreach ($joinings as $joining) {
            Admin::mungeJoining($joining);
        }

        return $joinings;
    }

    /**
     * Get joining station
     * @param int $joiningid
     * @return object
     * @throws Exception
     */
    public static function getJoining($joiningid) {
        $joining = ORM::forTable('joining')->findOne($joiningid);
        if (!$joining) {
            throw new \Exception('Unable to find joining, id = ' . $joiningid);
        }

        return $joining;
    }

    /**
     * Create new joining thing
     * @param $serviceid int
     * @param $pricebandgroups array
     * @return object new (empty) joining object
     */
    public static function createJoining($serviceid, $pricebandgroups) {
        $joining = ORM::forTable('joining')->create();
        $joining->serviceid = $serviceid;
        $joining->station = '';
        $joining->crs = '';
        $joining->meala = 1;
        $joining->mealb = 1;
        $joining->mealc = 1;
        $joining->meald = 1;

        // find and set to the first pricebandgoup
        $pricebandgroup = array_shift($pricebandgroups);
        $joining->pricebandgroupid = $pricebandgroup->id;

        return $joining;
    }

    /**
     * Delete joining station
     * @param int $joiningid
     * @return int
     * @throws Exception
     */
    public static function deleteJoining($joiningid) {
        $joining = Admin::getJoining($joiningid);
        $serviceid = $joining->serviceid;
        $joining->delete();

        return $serviceid;
    }

    /**
     * Get limits
     * @param int $serviceid
     * @return array
     */
    public static function getLimits($serviceid) {

        if (!$limits = ORM::forTable('limits')->where('serviceid', $serviceid)->findOne()) {
            
            // Limits table doesn't exist, so create a new one
            $limits = ORM::forTable('limits')->create();
            $limits->serviceid = $serviceid;
            $limits->first = $_ENV['default_limit'];
            $limits->standard = $_ENV['default_limit'];
            $limits->firstsingles = 0;
            $limits->meala = 0;
            $limits->mealb = 0;
            $limits->mealc = 0;
            $limits->meald = 0;
            $limits->maxparty = $_ENV['default_party'];
            $limits->maxpartyfirst = 0;
            $limits->minparty = 0;
            $limits->minpartyfirst = 0;
            $limits->save();
        }

        return $limits;
    }

    /**
     * Clear incomplete purchases that are time expired
     * @return boolean true if current purchase has been deleted
     *                 (redirect required)
     */
    public static function deleteOldPurchases() {

        // For the time being, don't delete anything
        //return;

        $oldtime = time() - PURCHASE_LIFETIME;
        ORM::forTable('purchase')
            ->where('completed', 0)
            ->where_lt('timestamp', $oldtime)
            ->delete_many();

        // IF we've deleted the current purchase then we have
        // an interesting problem!

        // See if the current purchase still exists
        if (Session::exists('purchaseid')) {
            $purchaseid = Session::read('purchaseid');
            $purchase = ORM::forTable('purchase')->findOne($purchaseid);
            if (!$purchase) {
                Session::delete('key');
                Session::delete('purchaseid');

                // Redirect out of here
                return true;
            }
        }

        return false;
    }

    /**
     * Clear the current session data and delete any expired purchases
     */
    public static function cleanPurchases() {

        // TODO (fix) remove the key and the purchaseid
        Session::delete('key');
        Session::delete('purchaseid');

        // get incomplete purchases
        return Admin::deleteOldPurchases();
    }

    /**
     * Format purchase for UI
     * @param object $purchase
     * @return object
     */
    public static function formatPurchase($purchase) {
        $purchase->unixdate = strtotime($purchase->date);
        $purchase->formatteddate = date('d/m/Y', $purchase->unixdate);
        $purchase->statusclass = '';
        if (!$purchase->status) {
            $purchase->statusclass = 'warning';
        } else if ($purchase->status != 'OK') {
            $purchase->statusclass = 'danger';
        }
        $purchase->formattedeticket = $purchase->eticket ? 'Yes' : 'No';
        $purchase->formattedeinfo = $purchase->einfo ? 'Yes' : 'No';
        $purchase->formattedseatsupplement = $purchase->seatsupplement ? 'Yes' : 'No';
        $purchase->formattedclass = $purchase->class == 'F' ? 'First' : 'Standard';

        return $purchase;
    }

    /**
     * Format list of purchases for UI
     * @param array $purchases
     * @return array
     */
    public static function formatPurchases($purchases) {
        foreach ($purchases as $purchase) {
            Admin::formatPurchase($purchase);
        }

        return $purchases;
    }

    /**
     * Get purchases for service
     * @param int serviceid
     * @param bool $completed
     * @param bool $descending
     * @return array
     */
    public static function getPurchases($serviceid, $completed = true, $descending = false) {
        $dbcompleted = $completed ? 1 : 0;

        if (!$descending) {
            $purchases = ORM::forTable('purchase')
                ->where(array(
                    'serviceid' => $serviceid,
                    'completed' => $dbcompleted,
                ))
                ->order_by_asc('timestamp')
                ->findMany();
        } else {
            $purchases = ORM::forTable('purchase')
                ->where(array(
                    'serviceid' => $serviceid,
                    'completed' => $dbcompleted,
                ))
                ->order_by_desc('timestamp')
                ->findMany();
        }

        return $purchases;
    }

    /**
     * Get single purchase
     * @param int $purchaseid
     * @return object
     * @throws Exception
     */
    public static function getPurchase($purchaseid) {
        $purchase = ORM::forTable('purchase')->findOne($purchaseid);

        if (!$purchase) {
            throw new \Exception('Purchase record not found, id=' . $purchaseid);
        }

        return $purchase;
    }

    /**
     * Do pricebands exist for service
     * @param int $serviceid
     * @return boolean
     */
    public static function isPricebandsConfigured($serviceid) {

        // presumably we need at least one pricebandgroup
        $pricebandgroup_count = ORM::forTable('pricebandgroup')->where('serviceid', $serviceid)->count();
        if (!$pricebandgroup_count) {
            return false;
        }

        // ...and there must be some pricebands too
        $priceband_count = ORM::forTable('priceband')->where('serviceid', $serviceid)->count();
        if (!$priceband_count) {
            return false;
        }

        return true;
    }

    /**
     * Clean string for export
     * @param $string
     * @param int $length
     * @return bool|string
     */
    private static function clean($string, $length=255) {

        // sanitize the string
        $string = trim(filter_var($string, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW ));

        // make an empty string into a single space (see Roger!)
        if (''==$string) {
            $string=' ';
        }

        // restrict to required length
        $string = substr($string, 0, $length);

        return $string;
    }

    /**
     * Get initials for user
     * @param string $bookedby
     * @return string
     */
    private static function getInitials($bookedby) {
        if ($user = ORM::forTable('srps_users')->where('username', $bookedby)->findOne()) {
            $ins = substr($user->firstname, 0, 1);
            $ins .= substr($user->lastname, 0, 1);
            return $ins;
        }

        return '';
    }

    /**
     * Turn the purchases into a big string, according
     * to Roger's requirements
     * @param array $purchases
     * @return string
     */
    public static function getExport($purchases) {
        $lines = array();

        // create each line
        foreach ($purchases as $p) {

            // Purchase status must be 'OK'
            if ($p->status != 'OK') {
                continue;
            }

            $l = array();

            // Record type
            $l[] = $p->bookedby ? 'P' : 'O';

            // Tour ref
            $l[] = Admin::clean($p->code);

            // Bkg ref
            $l[] = Admin::clean($p->bookingref);

            // Surname
            $l[] = Admin::clean($p->surname, 20);

            // Title
            $l[] = Admin::clean($p->title, 12);

            // First names
            $l[] = Admin::clean($p->firstname, 20);

            // Address line 1
            $l[] = Admin::clean($p->address1, 25);

            // Address line 2
            $l[] = Admin::clean($p->address2, 25);

            // Address line 3
            $l[] = Admin::clean($p->city, 25);

            // Address line 4
            $l[] = Admin::clean($p->county, 25);

            // Post code
            $l[] = Admin::clean($p->postcode, 8);

            // Phone No
            $l[] = Admin::clean($p->phone, 15);

            // Email
            $l[] = Admin::clean($p->email, 50);

            // Start
            $l[] = Admin::clean($p->joining);

            // Destination
            $l[] = Admin::clean($p->destination);

            // Class
            $l[] = Admin::clean($p->class, 1);

            // Adults
            $l[] = Admin::clean($p->adults);

            // Children
            $l[] = Admin::clean($p->children);

            // OAP (not used)
            $l[] = '0';

            // Family (not used)
            $l[] = '0';

            // Meal A
            $l[] = Admin::clean($p->meala);

            // Meal B
            $l[] = Admin::clean($p->mealb);

            // Meal C
            $l[] = Admin::clean($p->mealc);

            // Meal D
            $l[] = Admin::clean($p->meald);

            // Comment - add booker on the front
            // Remove 1/2 spurious characters from comment
            $comment = strlen($p->comment) < 3 ? '' : $p->comment;
            if ($p->bookedby) {
                $bookedby = Admin::getInitials($p->bookedby) . ' ';
            } else {
                $bookedby = '';
            }
            $l[] = Admin::clean($bookedby . $comment, 39);

            // Payment
            $l[] = Admin::clean(intval($p->payment * 100));

            // Booking Date
            $fdate = substr($p->date, 0, 4) . substr($p->date, 5, 2) . substr($p->date, 8, 2);
            $l[] = Admin::clean($fdate);

            // Seat supplement
            $l[] = $p->seatsupplement ? 'Y' : 'N';

            // Card Payment
            $l[] = 'Y';

            // Action required
            $l[] = 'N';

            // make tab separated line
            $line = implode("\t", $l);
            $lines[] = $line;
        }

        // combine lines
        return implode("\n", $lines);
    }

    /**
     * Duplicates a db record (used by service duplicate)
     * @param object $from
     * @param object $to
     * @return mixed
     */
    private static function duplicateRecord($from, $to) {
        $fields = $from->as_array();
        unset($fields['id']);
        foreach ($fields as $name => $value) {
            $to->$name = $value;
        }

        return $to;
    }

    /**
     * Duplicate a complete service and return new service
     * @param object $service
     * @return object
     * @throws Exception
     */
    public static function duplicate($service) {

        $serviceid = $service->id;

        // duplicate service
        $newservice = ORM::forTable('service')->create();
        Admin::duplicateRecord($service, $newservice);
        $newservice->code = "CHANGE";
        $newservice->date = date("Y-m-d");
        $newservice->visible = 0;
        $newservice->save();
        $newserviceid = $newservice->id();

        // duplicate destinations
        // create a map of old to new ids
        $destmap = array();
        $destinations = ORM::forTable('destination')->where('serviceid', $serviceid)->findMany();
        if ($destinations) {
            foreach ($destinations as $destination) {
                $newdestination = ORM::forTable('destination')->create();
                Admin::duplicateRecord($destination, $newdestination);
                $newdestination->serviceid = $newserviceid;
                $newdestination->save();
                $destmap[$destination->id] = $newdestination->id();
            }
        }

        // duplicate pricebandgroup
        // create a map of old to new ids
        $pbmap = array();
        $pricebandgroups = ORM::forTable('pricebandgroup')->where('serviceid', $serviceid)->findMany();
        if ($pricebandgroups) {
            foreach ($pricebandgroups as $pricebandgroup) {
                $newpricebandgroup = ORM::forTable('pricebandgroup')->create();
                $newpricebandgroup->serviceid = $newserviceid;
                $newpricebandgroup->name = $pricebandgroup->name;
                $newpricebandgroup->save();
                $pbmap[$pricebandgroup->id] = $newpricebandgroup->id();
            }
        }

        // duplicate joining
        $joinings = ORM::forTable('joining')->where('serviceid', $serviceid)->findMany();
        if ($joinings) {
            foreach ($joinings as $joining) {
                $newjoining = ORM::forTable('joining')->create();
                Admin::duplicateRecord($joining, $newjoining);
                $newjoining->serviceid = $newserviceid;
                if (empty($pbmap[$joining->pricebandgroupid])) {
                    throw new Exception('No pricebandgroup mapping exists for id = ' . $joining->pricebandgroupid);
                }
                $newjoining->pricebandgroupid = $pbmap[$joining->pricebandgroupid];
                $newjoining->save();
            }
        }

        // duplicate pricebands
        $pricebands = ORM::forTable('priceband')->where('serviceid', $serviceid)->findMany();
        if ($pricebands) {
            foreach ($pricebands as $priceband) {
                if (empty($pbmap[$priceband->pricebandgroupid])) {
                    throw new Exception('No pricebandgroup mapping exists for id = ' . $priceband->pricebandgroupid);
                }
                $newpriceband = ORM::forTable('priceband')->create();
                Admin::duplicateRecord($priceband, $newpriceband);
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
        $limits = ORM::forTable('limits')->where('serviceid', $serviceid)->findOne();
        if ($limits) {
            $newlimits = ORM::forTable('limits')->create();
            Admin::duplicateRecord($limits, $newlimits);
            $newlimits->serviceid = $newserviceid;
            $newlimits->save();
        }

        return $newservice;
    }

    /**
     * Are there any purchases for service
     * @param int $serviceid
     * @return boolean
     */
    public static function is_purchases($serviceid) {
        Admin::deleteOldPurchases();
        return ORM::forTable('purchase')->where([
            'serviceid' => $serviceid,
            'completed' => 1
         ])->count() > 0;
    }

    /**
     * Delete complete service
     * @param object $service
     * @throws Exception
     */
    public static function deleteService($service) {

        // Check there are no purchases. We should not have got here if there
        // are, but we'll check anyway
        if (Admin::is_purchases($service->id)) {
            throw new Exception('Trying to delete service with purchases. id = ' . $service->id);
        }

        $serviceid = $service->id;

        // Delete limits
        ORM::forTable('limits')->where('serviceid', $serviceid)->delete_many();

        // Delete pricebands
        ORM::forTable('priceband')->where('serviceid', $serviceid)->delete_many();

        // Delete joining stations
        ORM::forTable('joining')->where('serviceid', $serviceid)->delete_many();

        // Delete pricebandgroups
        ORM::forTable('pricebandgroup')->where('serviceid', $serviceid)->delete_many();

        // Delete destinations
        ORM::forTable('destination')->where('serviceid', $serviceid)->delete_many();

        // Finally, delete the service
        $service->delete();
    }

    /**
     * Add stations
     * Add CRS/Name combos for lookup
     */
    public static function loadStations() {

        $filename = $_ENV['dirroot'] . '/src/assets/json/stations.json';
        $stations = json_decode(file_get_contents($filename));
        //dd('STATIONS',$stations);
        foreach ($stations->locations as $station) {
            $s = ORM::forTable('station')->create();
            $s->crs = $station->crs;
            $s->name = $station->name;
            $s->save();
        }
    }

}
