<?php

namespace thepurpleblob\railtour\controller;

use thepurpleblob\core\coreController;

/**
 * Destination controller.
 *
 */
class DestinationController extends coreController
{
    protected $adminlib;

    /**
     * Constructor
     */
    public function __construct($exception = false)
    {
        parent::__construct($exception);

        // Library
        $this->adminlib = $this->getLibrary('Admin');
    }

    /**
     * Lists all Service entities.
     * @param int $serviceid
     */
    public function indexAction($serviceid)
    {
        $this->require_login('ROLE_ADMIN', 'destination/index/' . $serviceid);

        $service = $this->adminlib->getService($serviceid);

        $destinations = $this->adminlib->getDestinations($serviceid);

        // Check if used
        foreach ($destinations as $destination) {
            $destination->used = $this->adminlib->isDestinationUsed($destination);
        }

        $this->View('destination/index',
            array(
                'destinations' => $destinations,
                'service' => $service,
                'serviceid' => $serviceid
                ));
    }

    /**
     * Displays a form to edit an existing Destination entity.
     * @param int $serviceid
     * @param int $destinationid
     */
    public function editAction($serviceid, $destinationid) {

        $this->require_login('ROLE_ADMIN', 'destination/index/' . $serviceid);

        if ($destinationid) {
            $destination = $this->adminlib->getDestination($destinationid);
        } else {
            $destination = $this->adminlib->createDestination($serviceid);
        }

        // Service
        if ($destination->serviceid != $serviceid) {
            throw $this->Exception('Service ID mismatch');
        }
        $service = $this->adminlib->getService($serviceid);

        // hopefully no errors
        $errors = null;

        // Create form
        $form = new \stdClass();
        $form->crs = $this->form->text('crs', 'CRS', destination.crs);
        $form->name = $this->form->text('name', 'Name', destination.name);
        $form->description = $this->form->textarea('description', 'Description', $destination.description);


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
                $destinationid = $destination->id();
                $this->redirect('destination/index/' . $serviceid);
                return;
            } else {
                $errors = $this->gump->get_readable_errors();
            }
        }

        $this->View('destination/edit', array(
            'form' => $form,
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

        $booking = $this->getLibrary('Booking');

        // delete pricebands associated with this
        \ORM::for_table('Priceband')->where('destinationid', $id)->delete_many();

        // delete destination
        $destination = \ORM::forTable('destination')->find_one($id);
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
       $station = \ORM::forTable('station')->where('crs', $crs)->findOne();
       if ($station) {
           echo $station->name;
       } else {
           echo '';
       }
    }
}
