<?php

namespace thepurpleblob\railtour\controller;

use thepurpleblob\core\coreController;
use thepurpleblob\core\Session;
use thepurpleblob\core\Form;
use thepurpleblob\railtour\library\Admin;

/**
 * Service controller.
 *
 */
class PricebandController extends coreController {

    /**
     * Lists all Priceband entities.
     * @param int $serviceid
     *
     */
    public function indexAction($serviceid)
    {
        $this->require_login('ROLE_ADMIN', 'priceband/index/' . $serviceid);

        $service = Admin::getService($serviceid);
        
        // Get the Pricebandgroup
        $pricebandgroups = Admin::getPricebandgroups($serviceid);
        
        // Get destinations mostly to check that there are some
        $destinations = Admin::getDestinations($serviceid);
        
        // Get the band info to go with bands
        foreach ($pricebandgroups as $group) {
            $group->bandtable = Admin::getPricebands($serviceid, $group->id);
            $group->used = Admin::isPricebandUsed($group);
        }

        $this->View('priceband/index',
            array(
                'pricebandgroups' => $pricebandgroups,
                'destinations' => $destinations,
                'service' => $service,
                'serviceid' => $serviceid,
                'setup' => Admin::isPricebandsConfigured($serviceid),
                'pricebandgroupsdefined' => !empty($pricebandgroups),
                'saved' => Session::read('save_priceband', 0),
                ));
    }

    /**
     * Create rules for javascript validate
     *
     */
    private function js_validate_rules($pricebands) {
        $count = 1;
        $rules = array();
        $rules[] = 'name: {required: true}';
        foreach ($pricebands as $priceband) {
            $rules[] = "first_$count: {required: true, number: true}";
            $rules[] = "standard_$count: {required: true, number: true}";
            $rules[] = "child_$count: {required: true, number: true}";
            $count++;
        }
        return implode(', ', $rules);
    }

    /**
     * Displays a form to edit a Priceband entity.
     */
    public function editAction($serviceid, $pricebandgroupid)
    {
        $this->require_login('ROLE_ADMIN', 'priceband/edit/' . $serviceid . '/' . $pricebandgroupid);

        // Get pricebandgroup and pricebands (new ones if no $id)
        if ($pricebandgroupid) {
            $pricebandgroup = Admin::getPricebandgroup($pricebandgroupid);
            $pricebands = Admin::getPricebands($serviceid, $pricebandgroupid);
        } else {
            $pricebandgroup = Admin::createPricebandgroup($serviceid);
            $pricebands = Admin::getPricebands($serviceid, $pricebandgroupid, false);
        }
        if (!$pricebandgroup) {
            throw new \Exception('Price band group not found for id ' . $pricebandgroupid);
        }

        // Service
        if ($serviceid != $pricebandgroup->serviceid) {
            throw new \Exception('Service id mismatch');
        }
        $service = Admin::getService($serviceid);

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
                $savedpricebandgroupid = $pricebandgroup->id();
                $count = 1;
                foreach ($pricebands as $priceband) {
                    unset($priceband->name);
                    $priceband->first = $data['first_'.$count];
                    $priceband->standard = $data['standard_'.$count];
                    $priceband->child = $data['child_'.$count];
                    $priceband->pricebandgroupid = $savedpricebandgroupid;
                    $priceband->save();
                    $count++;
                }
                Session::writeFlash('save_priceband', 1);
                $this->redirect('priceband/index/' . $serviceid);
            } else {
                $errors = $this->gump->get_readable_errors(false);
            }
        }

        // Create form
        $form = new \stdClass();
        $form->name = Form::text('name', 'Name', $pricebandgroup->name, true);
        $count = 1;
        $form->pricebands = array();
        foreach ($pricebands as $priceband) {
            $pbform = new \stdClass();
            $pbform->name = $priceband->name;
            $pbform->first = '<input type="text" name="first_' . $count . '" value="' . $priceband->first . '">';
            $pbform->standard = '<input type="text" name="standard_' . $count . '" value="' . $priceband->standard . '">';
            $pbform->child = '<input type="text" name="child_' . $count . '" value="' . $priceband->child . '">';
            $form->pricebands[] = $pbform;
            $count++;
        }

        //echo "<pre>"; var_dump($formpricebands); die;

        $this->View('priceband/edit', array(
            'new' => empty($pricebandgroupid),
            'form' => $form,
            'pricebandgroup' => $pricebandgroup,
            'pricebands' => $pricebands,
            'service' => $service,
            'serviceid' => $serviceid,
            'jsrules' => $this->js_validate_rules($pricebands),
            'errors' => $errors,
        ));        
    }

    /**
     * Deletes a priceband group.
     *
     */
    public function deleteAction($pricebandgroupid) {
        $this->require_login('ROLE_ADMIN', 'priceband/delete/' . $pricebandgroupid);
        
        $serviceid = Admin::deletePricebandgroup($pricebandgroupid);

        $this->redirect('priceband/index/' . $serviceid);
    }
}
