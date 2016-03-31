<?php

namespace thepurpleblob\railtour\controller;

use thepurpleblob\core\coreController;

class AdminController extends coreController
{
    // default (no route) page shows available services
    public function mainAction() {

        $booking = $this->getLibrary('Booking');

        // Find available services
        $services = $booking->availableServices();

        // Display the services
        $this->View('admin/main.html.twig', array(
            'services' => $services,
        ));
    }

    public function indexAction()
    {
        $this->redirect('admin/main');
    }
}
