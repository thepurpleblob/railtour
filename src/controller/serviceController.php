<?php

namespace thepurpleblob\railtour\controller;

use thepurpleblob\core\coreController;

/**
 * Service controller.
 *
 */
class ServiceController extends coreController
{
    /**
     * Lists all Service entities.
     *
     */
    public function indexAction() {
        global $CFG;

        $entities = \ORM::forTable('Service')->order_by_asc('date')->findMany();

        // submitted year
        $filteryear = $this->getParam('filter_year');
        
        // get possible years and filter results
        // shouldn't have to do this in PHP but Doctrine sucks badly!
        $services = array();
        $years = array();
        $years['All'] = 'All';        
        foreach ($entities as $service) {
            $servicedate = $service->date;
            $year = substr($servicedate, 0, 4);
            $years[$year] = $year;
            if ($filteryear=='All' or $filteryear=='') {
                $services[] = $service;
            } else if ($year == $filteryear) {
                $services[] = $service;
            }
        }


        // get booking status
        $enablebooking = $CFG->enablebooking;

        $this->View('service/index.html.twig',
            array('entities' => $services,
                  'enablebooking' => $enablebooking,
                  'years' => $years,
                  'filteryear' => $filteryear,
                ));
    }

    /**
     * Create the table to display price band group
     * @param integer $pricebandgroupid
     */
    private function createPricebandTable($pricebandgroupid) {
        $em = $this->getDoctrine()->getManager();

        // get the basic price bands
        $pricebands = $em->getRepository('SRPSBookingBundle:Priceband')
            ->findByPricebandgroupid($pricebandgroupid);

        // iterate over these and get destinations
        // (very inefficiently)
        foreach ($pricebands as $priceband) {
            $destinationid = $priceband->getDestinationid();
            $destination = $em->getRepository('SRPSBookingBundle:Destination')
                ->find($destinationid);
            $priceband->setDestination($destination->getName());
        }

        return $pricebands;
    }

    /**
     * Finds and displays a Service entity.
     *
     * @Route("/{id}/show", name="admin_service_show")
     * @Template()
     */
    public function showAction($id)
    {
        $service = \ORM::forTable('Service')->findOne($id);

        if (!$service) {
            throw $this->Exception('Unable to find Service entity.');
        }

        // Get the other information stored for this service
        $destinations = \ORM::forTable('Destination')->where('serviceid', $id)->findMany();
        $pricebandgroups = \ORM::forTable('Pricebandgroup')->where('serviceid', $id)->findMany();
        $joinings = \ORM::forTable('Joining')->where('serviceid', $id)->findMany();

        // iterate over these and get destinations
        // (very inefficiently)
        $booking = $this->getService('Booking');
        foreach ($pricebandgroups as $band) {
            $pricebandgroupid = $band->id;
            $bandtable = $booking->createPricebandTable($pricebandgroupid);
            $band->bandtable = $bandtable;
        }

        // add pricebandgroup names
        foreach ($joinings as $joining) {
            $pricebandgroup = \ORM::forTable('Pricebandgroup')->findOne($joining->pricebandgroupid);
            $joining->pricebandname = $pricebandgroup->name;
        }

        $this->View('service/show.html.twig', array(
            'service' => $service,
            'destinations' => $destinations,
            'pricebandgroups' => $pricebandgroups,
            'joinings' => $joinings,
            'serviceid' => $id,
        ));
    }

    /**
     * Displays a form to create a new Service entity.
     */
    public function newAction()
    {
        $entity = new Service();

        $form   = $this->createForm(new ServiceType(), $entity);

        return $this->render('SRPSBookingBundle:Service:new.html.twig', array(
            'entity' => $entity,
            'form'   => $form->createView(),
        ));
    }

    /**
     * Creates a new Service entity.
     */
    public function createAction(Request $request)
    {
        $entity  = new Service();
        $form = $this->createForm(new ServiceType(), $entity);
        $form->bind($request);

        if ($form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($entity);
            $em->flush();

            return $this->redirect($this->generateUrl('admin_service_show', array('id' => $entity->getId())));
        }

        return $this->render('SRPSBookingBundle:Service:new.html.twig', array(
            'entity' => $entity,
            'form'   => $form->createView(),
        ));
    }

    /**
     * Displays a form to edit an existing Service entity.
     */
    public function editAction($id)
    {
        $service = \ORM::forTable('Service')->findOne($id);

        if (!$service) {
            throw $this->Exception('Unable to find Service.');
        }

        $editForm = $this->createForm(new ServiceType(), $entity);

        $this->View('service/edit.html.twig', array(
            'service'      => $service,
            'edit_form'   => $editForm->createView(),
            'serviceid' => $id,
        ));
    }

    /**
     * Edits an existing Service entity.
     */
    public function updateAction(Request $request, $id)
    {
        $em = $this->getDoctrine()->getManager();

        $entity = $em->getRepository('SRPSBookingBundle:Service')->find($id);

        if (!$entity) {
            throw $this->createNotFoundException('Unable to find Service entity.');
        }

        $editForm = $this->createForm(new ServiceType(), $entity);
        $editForm->bind($request);

        if ($editForm->isValid()) {
            $em->persist($entity);
            $em->flush();

            return $this->redirect($this->generateUrl('admin_service_show', array('id' => $id)));
        }

        return $this->render('SRPSBookingBundle:Service:edit.html.twig', array(
            'entity'      => $entity,
            //'destinations' => $destinations,
            'edit_form'   => $editForm->createView(),
            'serviceid' => $id,
        ));
    }

    /**
     * Calls routines to set the system up
     * (hidden)
     */
    public function installAction() {

        // Install the list of crs codes and stations
        $stations = $this->get('srps_stations');

        if ($stations->installStations()) {
            return new Response("<p>The Stations list was installed</p>");
        }
        else {
            return new Response("<p>The Stations list is already populated. No action taken</p>");
        }

    }
}
