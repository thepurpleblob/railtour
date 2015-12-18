<?php

namespace thepurpleblob\railtour\controller;

use thepurpleblob\core\coreController;

/**
 * Service controller.
 *
 */
class PricebandController extends coreController
{
    
    /**
     * Lists all Priceband entities.
     *
     */
    public function indexAction($serviceid)
    {
        $booking = $this->getService('Booking');
        $service = $booking->Service($serviceid);
        
        // Get the Pricebandgroup
        $pricebandgroups = \ORM::forTable('Pricebandgroup')->where('serviceid', $serviceid)->findMany();
        
        // Get destinations mostly to check that there are some
        $destinations = \ORM::forTable('Destination')->where('serviceid', $serviceid)->findMany();
        
        // Get the band info to go with bands
        foreach ($pricebandgroups as $group) {
            $group->bandtable = $booking->getPricebands($serviceid, $group->id);
            $group->used = $booking->isPricebandUsed($group);
        }

        return $this->View('priceband/index.html.twig',
            array(
                'pricebandgroups' => $pricebandgroups,
                'destinations' => $destinations,
                'service' => $service,
                'serviceid' => $serviceid
                ));
    }

    /**
     * Displays a form to edit a Priceband entity.
     */
    public function editAction($serviceid, $id)
    {
        $booking = $this->getService('Booking');

        // Get pricebandgroup and pricebands (new ones if no $id)
        if ($id) {
            $pricebandgroup = \ORM::forTable('Pricebandgroup')->findOne($id);
            $pricebands = $booking->getPricebands($serviceid, $id);
        } else {
            $pricebandgroup = $booking->createPricebandgroup($serviceid);
            $pricebands = $booking->getPricebands($serviceid, $id, false);
        }
        if (!$pricebandgroup) {
            throw new \Exception('Price band group not found for id ' . $id);
        }

        // Service
        if ($serviceid != $pricebandgroup->serviceid) {
            throw new \Exception('Service id mismatch');
        }
        $service = $booking->Service($serviceid);

        // hopefully no errors
        $errors = null;

        // anything submitted?
        if ($data = $this->getRequest()) {

            // Cancel?
            if (!empty($data['cancel'])) {
                $this->redirect('priceband/index/' . $serviceid);
            }

            // Validate
            $count = 1;
            $rules = array(
                'name' => 'required',
            );
            foreach ($pricebands as $priceband) {
                $rules['first_'.$count] = 'required|numeric';
                $rules['standard_'.$count] = 'required|numeric';
                $rules['child_'.$count] = 'required|numeric';
                $count++;
            }
            $this->gump->validation_rules($rules);
            if ($data = $this->gump->run($data)) {
                $pricebandgroup->name = $data['name'];
                $pricebandgroup->save();
                $id = $pricebandgroup->id();
                $count = 1;
                foreach ($pricebands as $priceband) {
                    unset($priceband->name);
                    $priceband->first = $data['first_'.$count];
                    $priceband->standard = $data['standard_'.$count];
                    $priceband->child = $data['child_'.$count];
                    $priceband->pricebandgroupid = $id;
                    $priceband->save();
                    $count++;
                }
                $this->redirect('priceband/index/' . $serviceid);
            } else {
                $errors = $this->gump->get_readable_errors(false);
            }
        }

        return $this->View('priceband/edit.html.twig', array(
            'pricebandgroup' => $pricebandgroup,
            'pricebands' => $pricebands,
            'service' => $service,
            'serviceid' => $serviceid,
            'errors' => $errors,
        ));        
    }

    /**
     * Deletes a Service entity.
     *
     */
    public function deleteAction($pricebandgroupid)
    {
        
        // Remove pricebands associated with this group
        \ORM::forTable('Priceband')->where('pricebandgroupid', $pricebandgroupid)->deleteMany();
        
        // Remove pricebandgroup
        $pricebandgroup = \ORM::forTable('Pricebandgroup')->findOne($pricebandgroupid);
        if (!$pricebandgroup) {
            throw new \Exception('No price band group found for id = ' . $pricebandgroupid);
        }
        $serviceid = $pricebandgroup->serviceid;
        $pricebandgroup->delete();
        $this->redirect('priceband/index/' . $serviceid);
    }
}
