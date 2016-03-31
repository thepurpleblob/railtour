<?php

namespace thepurpleblob\railtour\controller;

use thepurpleblob\core\coreController;

/**
 * Limits controller.
 *
 */
class LimitsController extends coreController
{

    /**
     * Edits the existing Limits entity.
     */
    public function editAction($serviceid)
    {
        $booking = $this->getLibrary('Booking');
        $service = $booking->Service($serviceid);

        $limits = $booking->getLimits($serviceid);

        // Get destinations (for destination limits)
        $destinations = \ORM::forTable('destination')->where('serviceid', $serviceid)->findMany();

        // Create array of destinations limits
        $destinationlimits = array();
        $jsvalidatelist = array();
        $gump_rules = array();
        foreach ($destinations as $destination) {
            $fieldname = 'destination_' . $destination->crs;
            $destinationlimits[$destination->crs] = $destination->bookinglimit;
            $jsvalidatelist[] = "$fieldname: {required:true, number:true}";
            $gump_rules[$fieldname] = 'required|integer';
        }

        // Get the current counts of everything
        $count = $booking->countStuff($serviceid);

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

        $this->View('limits/edit.html.twig', array(
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
