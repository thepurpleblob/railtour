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

        // Initialise station CRS codes
        $this->adminlib->initialiseStations();
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
        global $CFG;

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
        $form->crs = $this->form->text('crs', 'CRS', $destination->crs, true);
        $form->name = $this->form->text('name', 'Name', $destination->name, true);
        $form->description = $this->form->textarea('description', 'Description', $destination->description);
        $form->meala = $this->form->yesno('meala', $service->mealaname . ' available for this destination', $destination->meala);
        $form->mealb = $this->form->yesno('mealb', $service->mealbname . ' available for this destination', $destination->mealb);
        $form->mealc = $this->form->yesno('mealc', $service->mealcname . ' available for this destination', $destination->mealc);
        $form->meald = $this->form->yesno('meald', $service->mealdname . ' available for this destination', $destination->meald);
        $form->ajaxpath = $this->form->hidden('ajaxpath', $this->Url('destination/ajax'));


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
                if (isset($data['meala'])) {
                    $destination->meala = $data['meala'];
                }
                if (isset($data['mealb'])) {
                    $destination->mealb = $data['mealb'];
                }
                if (isset($data['mealc'])) {
                    $destination->mealc = $data['mealc'];
                }
                if (isset($data['meald'])) {
                    $destination->meald = $data['meald'];
                }
                $destination->save();
                $this->redirect('destination/index/' . $serviceid);
                return;
            } else {
                $errors = $this->gump->get_readable_errors();
            }
        }

        $this->View('destination/edit', array(
            'new' => empty($destinationid),
            'form' => $form,
            'errors' => $errors,
            'destination' => $destination,
            'service' => $service,
            'serviceid' => $serviceid,
        ));
    }

    /**
     * Deletes a destination.
     * @param int $destinationid
     */
    public function deleteAction($destinationid) {
        $this->require_login('ROLE_ADMIN', 'destination/index/' . $serviceid);

        $serviceid = $this->adminlib->deleteDestination($destinationid);

        $this->redirect('destination/index/' . $serviceid);
    }

    /**
     * Ajax function to find name from crs
     */
    public function ajaxAction() {


       // Get post variable for CRS
       $crs = $_POST['crstyped'];
        //error_log('CRS TYPED - ' . $crs);

       // Attempt to find in db
       if ($location = $this->adminlib->getCRSLocation($crs)) {
           $station = $location->name;
       } else {
           $station = '';
       }
       //error_log('Name returned - ' . $station);
       echo $station;
    }
}
