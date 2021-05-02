<?php

/**
 * @copyright 2017 Howard Miller - howardsmiller@gmail.com
 */

namespace thepurpleblob\railtour\controller;

use thepurpleblob\core\coreController;
use thepurpleblob\core\Session;
use thepurpleblob\railtour\library\Admin;
use thepurpleblob\core\Form;

/**
 * Joining controller.
 *
 */
class JoiningController extends coreController {

    /**
     * Lists all Joining entities.
     * @param int $serviceid
     */
    public function indexAction($serviceid)
    {
        $this->require_login('ROLE_ADMIN', 'joining/index/' . $serviceid);

        // Fetch basic data
        $service = Admin::getService($serviceid);
        $joinings = Admin::getJoinings($serviceid);

        $this->View('joining/index',
            [
                'nojoinings' => empty($joinings),
                'joinings' => Admin::mungeJoinings($joinings),
                'service' => $service,
                'serviceid' => $serviceid,
                //'setup' => Admin::isPricebandsConfigured($serviceid) && !empty($joinings),
                'setup' => Admin::isPricebandsConfigured($serviceid),
                'saved' => Session::read('save_joining', 0),
            ]);
    }

    /**
     * Edits an existing Joining entity.
     */
    public function editAction($serviceid, $joiningid)
    {
        $this->require_login('ROLE_ADMIN', 'joining/index/' . $serviceid . '/' . $joiningid);

        // Fetch basic data
        $service = Admin::getService($serviceid);
        $pricebandgroups = Admin::getPricebandgroups($serviceid);
        if (!$pricebandgroups) {
            throw new \Exception('No pricebandgroups found for serviceid = ' . $serviceid);
        }

        // Find/create joining to edit
        if ($joiningid) {
            $joining = Admin::getJoining($joiningid);
            if ($joining->serviceid != $serviceid) {
                throw new \Exception('Service ID mismatch for joining id = ' . $joiningid . ', service id = ' . $serviceid);
            }
        } else {
            $joining = Admin::createJoining($serviceid, $pricebandgroups);
        }

        // hopefully no errors
        $errors = null;

        // anything submitted?
        if ($data = $this->getRequest()) {

            // Cancel?
            if (!empty($data['cancel'])) {
                $this->redirect('joining/index/' . $serviceid);
            }

            // Validate
            $this->gump->validation_rules(array(
                'crs' => 'required',
                'name' => 'required',
            ));
            if ($data = $this->gump->run($data)) {
                $joining->crs = $data['crs'];
                $joining->station = $data['name'];
                $joining->pricebandgroupid = $data['pricebandgroupid'];
                if (isset($data['meala'])) {
                    $joining->meala = $data['meala'];
                }
                if (isset($data['mealb'])) {
                    $joining->mealb = $data['mealb'];
                }
                if (isset($data['mealc'])) {
                    $joining->mealc = $data['mealc'];
                }
                if (isset($data['meald'])) {
                    $joining->meald = $data['meald'];
                }
                $joining->save();
                Session::writeFlash('save_joining', 1);
                $this->redirect('joining/index/' . $serviceid);
            }  else {
                $errors = $this->gump->get_readable_errors();
            }
        }

        // Create form
        $form = new \stdClass();

        // name='name' so CRS lookup works.
        $form->crs = Form::text('crs', 'CRS', $joining->crs, true, [
            'v-model' => 'crs',
            '@change' => 'crschange'
        ]);
        $form->station = Form::text('name', 'Station name', $joining->station, true, [
            'v-model' => 'name'
        ]);
        $pricebandgroupoptions = Admin::pricebandgroupOptions($pricebandgroups);
        $form->pricebandgroupid = Form::select('pricebandgroupid', 'Priceband', $joining->pricebandgroupid, $pricebandgroupoptions);
        $form->meala = Form::yesno('meala', $service->mealaname . ' available from this station', $joining->meala);
        $form->mealb = Form::yesno('mealb', $service->mealbname . ' available from this station', $joining->mealb);
        $form->mealc = Form::yesno('mealc', $service->mealcname . ' available from this station', $joining->mealc);
        $form->meald = Form::yesno('meald', $service->mealdname . ' available from this station', $joining->meald);
        $form->ajaxpath = Form::hidden('ajaxpath', $this->Url('destination/ajax'));

        $this->View('joining/edit', array(
            'new' => empty($joiningid),
            'joiningid' => $joiningid,
            'joining' => $joining,
            'service' => $service,
            'serviceid' => $serviceid,
            'errors' => $errors,
            'form' => $form,
        ));
    }

    /**
     * Deletes a Service entity.
     *
     */
    public function deleteAction($joiningid) {

        $this->require_login('ROLE_ADMIN', 'joining/delete/' . $joiningid);

        $serviceid = Admin::deleteJoining($joiningid);

        $this->redirect('joining/index/' . $serviceid);
    }
}
