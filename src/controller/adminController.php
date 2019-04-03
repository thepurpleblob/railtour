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

        // counts
        foreach ($services as $service) {
            $service->progress = $this->bookinglib->getProgress($service->id);
            $service->anyseatsremaining = $this->bookinglib->anySeatsRemaining($service->id);
            if ($service->progress > 80) {
                $service->progresscol = 'bg-danger';
            } else if ($service->progress > 50) {
                $service->progresscol = 'bg-warning';
            } else {
                $service->progresscol = 'bg-success';
            }
        } 

        // Display the services
        $this->View('admin/main', array(
            'services' => $services,
            'anyservices' => !empty($services),
        ));
    }
}
