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
        $this->View('admin/index.html.twig');
    }
    
    public function displayAction($serviceid)
    {
        // Entity Manager
        $em = $this->getDoctrine()->getManager();
        
        // Service 
        $service = $em->getRepository('SRPSBookingBundle:Service')
            ->find($serviceid);

        // List of destinations
        $destinations = $em->getRepository('SRPSBookingBundle:Destination')
            ->findByServiceid($serviceid);    
        
        // List of Pricebandgroups
        $pricebandgroups = $em->getRepository('SRPSBookingBundle:Pricebandgroup')
            ->findByServiceid($serviceid);
        
         return $this->render('SRPSBookingBundle:Admin:display.html.twig',
            array(
                'service' => $service,
                'serviceid' => $serviceid
                ));       
    }
}
