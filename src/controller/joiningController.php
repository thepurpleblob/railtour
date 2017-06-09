<?php

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
                'setup' => $this->adminlib->isPricebandsConfigured($serviceid),
                ));
    }

    /**
     * Edits an existing Joining entity.
     */
    public function editAction($serviceid, $joiningid)
    {
        $this->require_login('ROLE_ADMIN', 'joining/index/' . $serviceid);

        $booking = $this->getLibrary('Booking');

        // Fetch basic data
        $service = $booking->Service($serviceid);

        // Price bands
        $pricebandgroups = \ORM::forTable('pricebandgroup')->where('serviceid', $serviceid)->findMany();
        if (!$pricebandgroups) {
            throw new \Exception('No pricebandgroups found for serviceid = ' . $serviceid);
        }

        // Find/create joining to edit
        if ($joiningid) {
            $joining = \ORM::forTable('joining')->findOne($joiningid);
            if (!$joining) {
                throw new \Exception('Unable to find joining, id = ' . $joiningid);
            }
            if ($joining->serviceid != $serviceid) {
                throw new \Exception('Service ID mismatch for joining id = ' . $joiningid . ', service id = ' . $serviceid);
            }
        } else {
            $joining = $booking->createJoining($serviceid, $pricebandgroups);
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
                'station' => 'required',
            ));
            if ($data = $this->gump->run($data)) {
                $joining->crs = $data['crs'];
                $joining->station = $data['station'];
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

        $this->View('joining/edit.html.twig', array(
            'joining' => $joining,
            'service' => $service,
            'serviceid' => $serviceid,
            'errors' => $errors,
            'pricebandgroupoptions' => $booking->pricebandgroupOptions($pricebandgroups),
        ));
    }

    /**
     * Deletes a Service entity.
     *
     */
    public function deleteAction($joiningid)
    {
        $this->require_login('ROLE_ADMIN', 'joining/index/' . $serviceid);

        $joining = \ORM::forTable('joining')->findOne($joiningid);
        if (!$joining) {
            throw new \Exception('Unable to find joining, id = ' . $joiningid);
        }

        $serviceid = $joining->serviceid;
        $joining->delete();

        $this->redirect('joining/index/' . $serviceid);
    }
}
