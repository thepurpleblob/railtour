<?php

namespace thepurpleblob\railtour\controller;

use thepurpleblob\core\coreController;
use thepurpleblob\core\Session;
use thepurpleblob\core\Form;
use thepurpleblob\railtour\library\Admin;

/**
 * Destination controller.
 *
 */
class DestinationController extends coreController
{

    /**
     * Lists all Service entities.
     * @param int $serviceid
     */
    public function indexAction($serviceid)
    {
        $this->require_login('ROLE_ADMIN', 'destination/index/' . $serviceid);

        $service = Admin::getService($serviceid);

        $destinations = Admin::getDestinations($serviceid);

        // Check if used
        foreach ($destinations as $destination) {
            $destination->used = Admin::isDestinationUsed($destination);
        }

        $this->View('destination/index',
            array(
                'nodestinations' => empty($destinations),
                'destinations' => $destinations,
                'service' => $service,
                'serviceid' => $serviceid,
                'saved' => Session::read('save_destination', 0),
                ));
    }

    /**
     * Displays a form to edit an existing Destination entity.
     * @param int $serviceid
     * @param int $destinationid
     */
    public function editAction($serviceid, $destinationid) {
        global $CFG;

        $this->require_login('ROLE_ADMIN', 'destination/index/' . $serviceid . '/' . $destinationid);

        if ($destinationid) {
            $destination = Admin::getDestination($destinationid);
        } else {
            $destination = Admin::createDestination($serviceid);
        }

        // Service
        if ($destination->serviceid != $serviceid) {
            throw $this->Exception('Service ID mismatch');
        }
        $service = Admin::getService($serviceid);

        // hopefully no errors
        $errors = null;

        // Create form
        $form = new \stdClass();
        $form->crs = Form::text('crs', 'CRS', $destination->crs, true);
        $form->name = Form::text('name', 'Name', $destination->name, true);
        $form->description = Form::textarea('description', 'Description', $destination->description);
        $form->meala = Form::yesno('meala', $service->mealaname . ' available for this destination', $destination->meala);
        $form->mealb = Form::yesno('mealb', $service->mealbname . ' available for this destination', $destination->mealb);
        $form->mealc = Form::yesno('mealc', $service->mealcname . ' available for this destination', $destination->mealc);
        $form->meald = Form::yesno('meald', $service->mealdname . ' available for this destination', $destination->meald);
        $form->ajaxpath = Form::hidden('ajaxpath', $this->Url('destination/ajax'));


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
                Session::writeFlash('save_destination', 1);
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
        $this->require_login('ROLE_ADMIN', 'destination/delete/' . $destinationid);

        $serviceid = Admin::deleteDestination($destinationid);

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
       if ($location = Admin::getCRSLocation($crs)) {
           $station = $location->name;
       } else {
           $station = '';
       }
       //error_log('Name returned - ' . $station);
       echo $station;
    }
}
