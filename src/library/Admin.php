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
     * Get all services
     */
    public function getServices() {
        $allservices = \ORM::forTable('service')->order_by_asc('date')->findMany();

        return $allservices;
    }

}