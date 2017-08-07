<?php

namespace thepurpleblob\railtour\controller;

use thepurpleblob\core\coreController;

class AdminController extends coreController {

    protected $bookinglib;

    /**
     * Constructor
     */
    public function __construct($exception = false) {
        parent::__construct($exception);

        // Library
        $this->bookinglib = $this->getLibrary('Booking');
    }

    // default (no route) page shows available services
    public function mainAction() {

        // Find available services
        $services = $this->bookinglib->availableServices();

        // Display the services
        $this->View('admin/main', array(
            'services' => $services,
            'anyservices' => !empty($services),
        ));
    }
}
