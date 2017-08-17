<?php

/**
 * @copyright 2017 Howard Miller - howardsmiller@gmail.com
 */

namespace thepurpleblob\railtour\controller;

use thepurpleblob\core\coreController;

/**
 * Joining controller.
 *
 */
class JoiningController extends coreController {

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
     * Lists all Joining entities.
     * @param int $serviceid
     */
    public function indexAction($serviceid)
    {
        $this->require_login('ROLE_ADMIN', 'joining/index/' . $serviceid);

        // Fetch basic data
        $service = $this->adminlib->getService($serviceid);
        $joinings = $this->adminlib->getJoinings($serviceid);

        $this->View('joining/index',
            array(
                'joinings' => $this->adminlib->mungeJoinings($joinings),
                'service' => $service,
                'serviceid' => $serviceid,
                'setup' => $this->adminlib->isPricebandsConfigured($serviceid) && !empty($joinings),
                ));
    }

    /**
     * Edits an existing Joining entity.
     */
    public function editAction($serviceid, $joiningid)
    {
        $this->require_login('ROLE_ADMIN', 'joining/index/' . $serviceid);

        // Fetch basic data
        $service = $this->adminlib->getService($serviceid);
        $pricebandgroups = $this->adminlib->getPricebandgroups($serviceid);
        if (!$pricebandgroups) {
            throw new \Exception('No pricebandgroups found for serviceid = ' . $serviceid);
        }

        // Find/create joining to edit
        if ($joiningid) {
            $joining = $this->adminlib->getJoining($joiningid);
            if ($joining->serviceid != $serviceid) {
                throw new \Exception('Service ID mismatch for joining id = ' . $joiningid . ', service id = ' . $serviceid);
            }
        } else {
            $joining = $this->adminlib->createJoining($serviceid, $pricebandgroups);
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
                $this->redirect('joining/index/' . $serviceid);
            }  else {
                $errors = $this->gump->get_readable_errors();
            }
        }

        // Create form
        $form = new \stdClass();

        // name='name' so CRS lookup works.
        $form->crs = $this->form->text('crs', 'CRS', $joining->crs, true);
        $form->station = $this->form->text('name', 'Station name', $joining->station, true);
        $pricebandgroupoptions = $this->adminlib->pricebandgroupOptions($pricebandgroups);
        $form->pricebandgroupid = $this->form->select('pricebandgroupid', 'Priceband', $joining->pricebandgroupid, $pricebandgroupoptions);
        $form->meala = $this->form->yesno('meala', $service->mealaname . ' available from this station', $joining->meala);
        $form->mealb = $this->form->yesno('mealb', $service->mealbname . ' available from this station', $joining->mealb);
        $form->mealc = $this->form->yesno('mealc', $service->mealcname . ' available from this station', $joining->mealc);
        $form->meald = $this->form->yesno('meald', $service->mealdname . ' available from this station', $joining->meald);
        $form->ajaxpath = $this->form->hidden('ajaxpath', $this->Url('destination/ajax'));

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
    public function deleteAction($joiningid)
    {
        $serviceid = $this->adminlib->deleteJoining($joiningid);

        $this->redirect('joining/index/' . $serviceid);
    }
}
