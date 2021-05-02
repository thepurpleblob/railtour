<?php

namespace thepurpleblob\railtour\controller;

use thepurpleblob\core\coreController;
use thepurpleblob\railtour\library\Admin;
use thepurpleblob\railtour\library\Booking;
use thepurpleblob\core\Form;

class AdminController extends coreController {

    // default (no route) page shows available services
    public function mainAction() {

        // Must be logged in
        $this->require_login('ROLE_TELEPHONE', 'admin/main');

        // Find available services
        $services = Booking::availableServices();
        $services = Admin::formatServices($services);

        // counts
        foreach ($services as $service) {
            $service->progress = Booking::getProgress($service->id);
            $service->anyseatsremaining = Booking::anySeatsRemaining($service->id);
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
