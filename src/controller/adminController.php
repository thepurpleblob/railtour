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

        // Must be logged in
        $this->require_login('ROLE_TELEPHONE', 'admin/main');

        // Find available services
        $services = $this->bookinglib->availableServices();
        $services = $this->bookinglib->formatServices($services);

        // Display the services
        $this->View('admin/main', array(
            'services' => $services,
            'anyservices' => !empty($services),
        ));
    }
}
