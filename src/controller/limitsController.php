<?php

namespace thepurpleblob\railtour\controller;

use thepurpleblob\core\coreController;

/**
 * Limits controller.
 *
 */
class LimitsController extends coreController {

    protected $adminlib;

    protected $bookinglib;

    /**
     * Constructor
     */
    public function __construct($exception = false)
    {
        parent::__construct($exception);

        // Library
        $this->adminlib = $this->getLibrary('Admin');
        $this->bookinglib = $this->getLibrary('Booking');
    }

    /**
     * Edits the existing Limits entity.
     */
    public function editAction($serviceid)
    {
        $service = $this->adminlib->getService($serviceid);
        $this->require_login('ROLE_ORGANISER', 'limits/edit/' . $serviceid);

        $limits = $this->adminlib->getLimits($serviceid);

        // Get destinations (for destination limits)
        $destinations = $this->adminlib->getDestinations($serviceid);

        // Get the current counts of everything
        $count = $this->bookinglib->countStuff($serviceid);

        // Create array of destinations limits
        $destinationlimits = array();
        $jsvalidatelist = array();
        $gump_rules = array();
        foreach ($destinations as $destination) {
            $crs = $destination->crs;
            $data = clone $count->destinations[$crs];
            $data->crs = $crs;
            $fieldname = 'destination_' . $destination->crs;
            $data->fieldname = $fieldname;
            $data->bookinglimit = $destination->bookinglimit;
            $destinationlimits[] = $data;
            $jsvalidatelist[] = "$fieldname: {required:true, number:true}";
            $gump_rules[$fieldname] = 'required|integer';
        }

        // hopefully no errors
        $errors = null;

        // anything submitted?
        if ($data = $this->getRequest()) {

            // Cancel?
            if (!empty($data['cancel'])) {
                $this->redirect('service/show/' . $serviceid);
            }

            // Validate
            $gump_rules = array_merge($gump_rules, array(
                'first' => 'required|integer',
                'standard' => 'required|integer',
                'meala' => 'required|integer',
                'mealb' => 'required|integer',
                'mealc' => 'required|integer',
                'meald' => 'required|integer',
                'firstsingles' => 'required|integer',
                'maxparty' => 'required|integer',
                'maxpartyfirst' => 'required|integer',
            ));
            $this->gump->validation_rules($gump_rules);
            if ($data = $this->gump->run($data)) {
                $limits->first = $data['first'];
                $limits->standard = $data['standard'];
                $limits->meala = $data['meala'];
                $limits->mealb = $data['mealb'];
                $limits->mealc = $data['mealc'];
                $limits->meald = $data['meald'];
                $limits->firstsingles = $data['firstsingles'];
                $limits->maxparty = $data['maxparty'];
                $limits->maxpartyfirst = $data['maxpartyfirst'];
                foreach ($destinations as $destination) {
                    $fieldname = 'destination_' . $destination->crs;
                    $destination->bookinglimit = $data[$fieldname];
                    $destination->save();
                }
                $limits->save();

                $this->redirect('service/show/' . $serviceid);
            } else {
                $errors = $this->gump->get_readable_errors(false);
            }
        }

        $this->View('limits/edit', array(
            'limits' => $limits,
            'count' => $count,
            'service' => $service,
            'destinationlimits' => $destinationlimits,
            'jsvalidate' => implode(', ', $jsvalidatelist),
            'errors' => $errors,
            'serviceid' => $serviceid,
        ));
    }

}
