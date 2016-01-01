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
        $this->require_login('ROLE_ADMIN', 'destination/index/' . $serviceid);

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
     * Displays a form to edit an existing Destination entity.
     */
    public function editAction($serviceid, $id) {

        $this->require_login('ROLE_ADMIN', 'destination/index/' . $serviceid);

        $booking = $this->getService('Booking');

        if ($id) {
            $destination = \ORM::forTable('Destination')->findOne($id);
        } else {
            $destination = $booking->createDestination($serviceid);
        }

        // Service
        if ($destination->serviceid != $serviceid) {
            throw $this->Exception('Service ID mismatch');
        }
        $service = $booking->Service($serviceid);

        if (!$destination) {
            throw $this->Exception('Unable to find Destination entity.');
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
                return;
            } else {
                $errors = $this->gump->get_readable_errors();
            }
        }

        $this->View('destination/edit.html.twig', array(
            'errors' => $errors,
            'destination' => $destination,
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
        $this->require_login('ROLE_ADMIN', 'destination/index/' . $serviceid);

        $booking = $this->getService('Booking');

        // delete pricebands associated with this
        \ORM::for_table('Priceband')->where('destinationid', $id)->delete_many();

        // delete destination
        $destination = \ORM::for_table('Destination')->find_one($id);
        if ($destination) {
            $serviceid = $destination->serviceid;
            $destination->delete();
            $this->redirect('destination/index/' . $serviceid);
            return;
        } else {
            throw new \Exception('Destination record not found for id = ' . $id);
        }

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
