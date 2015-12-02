<?php

namespace thepurpleblob\railtour\controller;

use thepurpleblob\core\coreController;

/**
 * Destination controller.
 *
 */
class DestinationController extends coreController
{
    /**
     * Lists all Service entities.
     *
     */
    public function indexAction($serviceid)
    {
        $booking = $this->getService('Booking');

        $service = $booking->Service($serviceid);

        $destinations = \ORM::forTable('Destination')->where('serviceid', $serviceid)->findMany();

        // Check if used
        foreach ($destinations as $destination) {
            $destination->used = $booking->isDestinationUsed($destination);
        }

        $this->View('destination/index.html.twig',
            array(
                'destinations' => $destinations,
                'service' => $service,
                'serviceid' => $serviceid
                ));
    }

    /**
     * Displays a form to create a new Destination entity.
     */
    public function newAction($serviceid)
    {
        $entity = new Destination();
        $entity->setServiceid($serviceid);

        $em = $this->getDoctrine()->getManager();
        $service = $em->getRepository('SRPSBookingBundle:Service')
            ->find($serviceid);

        $form   = $this->createForm(new DestinationType(), $entity);

        return $this->render('SRPSBookingBundle:Destination:new.html.twig', array(
            'entity' => $entity,
            'service' => $service,
            'serviceid' => $serviceid,
            'form'   => $form->createView(),
        ));
    }

    /**
     * If we add a destination then we have to pad up the existing
     * pricebands
     * @param object $service service object
     */
    private function checkPricebands($service) {
        $em = $this->getDoctrine()->getManager();

        // Get all the destinations for this service
        $destinations = $em->getRepository('SRPSBookingBundle:Destination')
            ->findByServiceid($service->getId());

        // Get all the priceband groups for this service
        $groups = $em->getRepository('SRPSBookingBundle:Pricebandgroup')
            ->findByServiceid($service->getId());

        // run through groups checking all destinations are represented
        foreach ($groups as $group) {
            foreach ($destinations as $destination) {
                $priceband = $em->getRepository('SRPSBookingBundle:Priceband')
                    ->findOneBy(array('pricebandgroupid' => $group->getId(), 'destinationid' => $destination->getId()));
                if (!$priceband) {
                    $priceband = new Priceband();
                    $priceband->setServiceid($service->getId());
                    $priceband->setDestinationid($destination->getId());
                    $priceband->setPricebandgroupid($group->getId());
                    $em->persist($priceband);
                    $em->flush();
                }
            }
        }
    }

    /**
     * If CRS is populated and name is not, try looking
     * up name
     * @param string $dcrs
     */
    private function findCrs($crs, $name) {
        $em = $this->getDoctrine()->getManager();

        if (empty($name) and !empty($crs)) {
            $station = $em->getRepository('SRPSBookingBundle:Station')
                ->findOneByCrs($crs);
            if ($station) {
                return $station->getName();
            }
        }

        return $name;
    }

    /**
     * Creates a new Destination entity.
     */
    public function createAction(Request $request, $serviceid)
    {
        $entity = new Destination();
        $entity->setServiceid($serviceid);

        $em = $this->getDoctrine()->getManager();
        $service = $em->getRepository('SRPSBookingBundle:Service')
            ->find($serviceid);

        $form = $this->createForm(new DestinationType(), $entity);
        $form->bind($request);

        if ($form->isValid()) {
            $entity->setCrs(strtoupper($entity->getCrs()));
            $em->persist($entity);
            $em->flush();

            // Pad the pricebands with new destination
            $this->checkPricebands($service);

            return $this->redirect($this->generateUrl('admin_destination', array('serviceid' => $serviceid)));
        }

        return $this->render('SRPSBookingBundle:Destination:new.html.twig', array(
            'entity' => $entity,
            'service' => $service,
            'serviceid' => $serviceid,
            'form'   => $form->createView(),
        ));
    }

    /**
     * Displays a form to edit an existing Destination entity.
     */
    public function editAction($id) {

        $booking = $this->getService('Booking');

        $destination = \ORM::forTable('Destination')->findOne($id);

        // Service
        $serviceid = $destination->serviceid;
        $service = $booking->Service($serviceid);

        if (!$destination) {
            throw $this->createNotFoundException('Unable to find Destination entity.');
        }

        // hopefully no errors
        $errors = null;

        // anything submitted?
        if ($data = $this->getRequest()) {

            // Cancel?
            if (!empty($data['cancel'])) {
                $this->redirect('destination/index/' . $serviceid);
            }

            // Validate
            $this->gump->validation_rules(array(
                'crs' => 'required',
                'name' => 'required',
                'description' => 'required',
            ));
            if ($data = $this->gump->run($data)) {
                $destination->crs = $data['crs'];
                $destination->name = $data['name'];
                $destination->description = $data['description'];
                $destination->save();
                $id = $destination->id();
                $this->redirect('destination/index/' . $serviceid);
            } else {
                $errors = $this->gump->get_readable_errors(true);
            }
        }

        $this->View('destination/edit.html.twig', array(
            'destination' => $destination,
            'service' => $service,
            'serviceid' => $serviceid,
        ));
    }

    /**
     * Edits an existing Destination entity.
     */
    public function updateAction(Request $request, $id)
    {
        $em = $this->getDoctrine()->getManager();
        $entity = $em->getRepository('SRPSBookingBundle:Destination')->find($id);

        // Service
        $serviceid = $entity->getServiceid();
        $service = $em->getRepository('SRPSBookingBundle:Service')
            ->find($serviceid);

        if (!$entity) {
            throw $this->createNotFoundException('Unable to find Destination entity.');
        }

        $editForm = $this->createForm(new DestinationType(), $entity);
        $editForm->bind($request);


        if ($editForm->isValid()) {
            $entity->setCrs(strtoupper($entity->getCrs()));
            $em->persist($entity);
            $em->flush();

            // Check the pricebands are synced to destinations
            $this->checkPricebands($service);

            return $this->redirect($this->generateUrl('admin_destination', array('serviceid' => $serviceid)));
        }

        return $this->render('SRPSBookingBundle:Destination:edit.html.twig', array(
            'entity'      => $entity,
            'edit_form'   => $editForm->createView(),
            'service' => $service,
            'serviceid' => $serviceid,
        ));
    }

    /**
     * Deletes a Service entity.
     *
     */
    public function deleteAction($id)
    {
        $em = $this->getDoctrine()->getManager();

        // delete pricebands associated with this
        $pricebands = $em->getRepository('SRPSBookingBundle:Priceband')
            ->findByDestinationid($id);
        if ($pricebands) {
            foreach ($pricebands as $priceband) {
                $em->remove($priceband);
            }
        }
        $em->flush();

        // delete destination
        $destination = $em->getRepository('SRPSBookingBundle:Destination')
            ->find($id);
        if ($destination) {
            $serviceid = $destination->getServiceid();
            $em->remove($destination);
            $em->flush();
            return $this->redirect($this->generateUrl('admin_destination', array('serviceid' => $serviceid)));
        }

        return $this->redirect($this->generateUrl('admin_service'));
    }

    /**
     * Ajax function to find name from crs
     */
    public function ajaxAction() {

       // Get post variable for CRS
       $crs = $_POST['crstyped'];

       // Attempt to find in db
       $station = \ORM::forTable('Station')->where('crs', $crs)->findOne();
       if ($station) {
           echo $station->name;
       } else {
           echo '';
       }
    }
}
