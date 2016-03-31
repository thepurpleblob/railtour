<?php

namespace thepurpleblob\railtour\controller;

use thepurpleblob\core\coreController;

/**
 * Service controller.
 *
 */
class ServiceController extends coreController
{
    /**
     * Lists all Service entities.
     *
     */
    public function indexAction() {
        global $CFG;

        $this->require_login('ROLE_ORGANISER');

        $allservices = \ORM::forTable('service')->order_by_asc('date')->findMany();

        // submitted year
        $thisyear = date('Y');
        $filteryear = $this->getParam('filter_year', 0);
        if ($filteryear) {
            $this->setSession('filteryear', $filteryear);
        } else {
            $filteryear = $this->getFromSession('filteryear', $thisyear);
        }
        
        // get possible years and filter results
        $services = array();
        $years = array();
        $years['All'] = 'All';        
        foreach ($allservices as $service) {
            $servicedate = $service->date;
            $year = substr($servicedate, 0, 4);
            $years[$year] = $year;
            if ($filteryear=='All' or $filteryear=='') {
                $services[] = $service;
            } else if ($year == $filteryear) {
                $services[] = $service;
            }
        }


        // get booking status
        $enablebooking = $CFG->enablebooking;

        $this->View('service/index.html.twig',
            array('services' => $services,
                  'enablebooking' => $enablebooking,
                  'years' => $years,
                  'filteryear' => $filteryear,
                ));
    }

    /**
     * flip service visibility
     * @param $id service id
     */
    public function visibleAction($id, $visible) {

        $this->require_login('ROLE_ORGANISER');

        $service = \ORM::forTable('service')->findOne($id);

        if (!$service) {
            throw new \Exception('Unable to find Service entity.');
        }

        if (($visible != 1) && ($visible != 0)) {
            throw $this->Exception('visible parameter must be 0 or 1');
        }
        $service->visible = $visible;
        $service->save();

        $this->redirect('service/index');
    }

    /**
     * Finds and displays a Service entity.
     *
     * @Route("/{id}/show", name="admin_service_show")
     * @Template()
     */
    public function showAction($id)
    {
        $this->require_login('ROLE_ORGANISER', 'service/show/' . $id);

        $booking = $this->getLibrary('Booking');
        $service = $booking->Service($id);

        if (!$service) {
            throw new \Exception('Unable to find Service entity.');
        }

        // Get the other information stored for this service
        $destinations = \ORM::forTable('destination')->where('serviceid', $id)->findMany();
        $pricebandgroups = \ORM::forTable('pricebandgroup')->where('serviceid', $id)->findMany();
        $joinings = \ORM::forTable('joining')->where('serviceid', $id)->findMany();
        $limits = \ORM::forTable('limits')->where('serviceid', $id)->findOne();

        // iterate over these and get destinations
        // (very inefficiently)
        foreach ($pricebandgroups as $band) {
            $pricebandgroupid = $band->id;
            $bandtable = $booking->getPricebands($id, $pricebandgroupid);
            $band->bandtable = $bandtable;
        }

        // add pricebandgroup names
        foreach ($joinings as $joining) {
            $pricebandgroup = \ORM::forTable('pricebandgroup')->findOne($joining->pricebandgroupid);
            $joining->pricebandname = $pricebandgroup->name;
        }

        $this->View('service/show.html.twig', array(
            'service' => $service,
            'destinations' => $destinations,
            'pricebandgroups' => $pricebandgroups,
            'joinings' => $joinings,
            'limits' => $limits,
            'serviceid' => $id,
        ));
    }

    /**
     * Displays a form to edit an existing Service entity or create a new one
     * @param int $id service id
     */
    public function editAction($id=null)
    {
        $this->require_login('ROLE_ADMIN', 'service/show/' . $id);

        if ($id) {
            $service = \ORM::forTable('service')->findOne($id);

            if (!$service) {
                throw new \Exception('Unable to find Service.');
            }
        } else {
            $booking = $this->getLibrary('Booking');
            $service = $booking->createService();
        }

        // hopefully no errors
        $errors = null;

        // anything submitted?
        if ($data = $this->getRequest()) {

            // Cancel?
            if (!empty($data['cancel'])) {
                if ($id) {
                    $this->redirect('service/show/' . $id);
                } else {
                    $this->redirect('service/index');
                }
            }

            // Validate
            $this->gump->validation_rules(array(
                'code' => 'required',
                'name' => 'required',
                'description' => 'required',
                'visible' => 'required|integer',
                'date' => 'required',
                'commentbox' => 'required|integer',
                'mealaname' => 'required',
                'mealavisible' => 'required|integer',
                'mealaprice' => 'required|numeric',
                'mealbname' => 'required',
                'mealbvisible' => 'required|integer',
                'mealbprice' => 'required|numeric',
                'mealcname' => 'required',
                'mealcvisible' => 'required|integer',
                'mealcprice' => 'required|numeric',
                'mealdname' => 'required',
                'mealdvisible' => 'required|integer',
                'mealdprice' => 'required|numeric',
            ));

            if ($data = $this->gump->run($data)) {
                $service->code = $data['code'];
                $service->name = $data['name'];
                $service->description = $data['description'];
                $service->visible = $data['visible'];
                $dp = date_parse_from_format('d/m/Y', $data['date']);
                $service->date = $dp['year'] . '-' . $dp['month'] . '-' . $dp['day'];
                $service->commentbox = $data['commentbox'];
                $service->mealaname = $data['mealaname'];
                $service->mealavisible = $data['mealavisible'];
                $service->mealaprice = $data['mealaprice'];
                $service->mealbname = $data['mealbname'];
                $service->mealbvisible = $data['mealbvisible'];
                $service->mealbprice = $data['mealbprice'];
                $service->mealcname = $data['mealcname'];
                $service->mealcvisible = $data['mealcvisible'];
                $service->mealcprice = $data['mealcprice'];
                $service->mealdname = $data['mealdname'];
                $service->mealdvisible = $data['mealdvisible'];
                $service->mealdprice = $data['mealdprice'];
                $service->save();

                $id = $service->id();
                $this->redirect('service/show/' . $id);
            } else {
                $errors = $this->gump->get_readable_errors(false);
            }
        }

        $this->View('service/edit.html.twig', array(
            'service'      => $service,
            'serviceid' => $id,
            'errors' => $errors
        ));
    }

    /**
     * Duplicate a complete service
     */
    public function duplicateAction($serviceid) {
        $this->require_login('ROLE_ADMIN', 'service/show/' . $serviceid);

        $booking = $this->getLibrary('Booking');
        $service = $booking->Service($serviceid);

        $newservice = $booking->duplicate($service);

        $this->redirect('service/edit/' . $newservice->id);
    }

    /**
     * Delete a service provided that there are no purchases
     */
    public function deleteAction($serviceid) {
        $this->require_login('ROLE_ADMIN', 'service/show/' . $serviceid);

        $booking = $this->getLibrary('Booking');
        $service = $booking->Service($serviceid);

        // If there are purchases, we're out of here
        if (\ORM::forTable('purchase')->where('serviceid', $serviceid)->count()) {
            $haspurchases = true;
        } else {
            $haspurchases = false;

            // anything submitted?
            if ($data = $this->getRequest()) {

                // Delete?
                if (!empty($data['delete'])) {
                    $booking->deleteService($service);
                }
                $this->redirect('service/index');
            }
        }

        $this->View('service/delete.html.twig', array(
            'service' => $service,
            'haspurchases' => $haspurchases,
        ));
    }

    /**
     * Calls routines to set the system up
     * (hidden)
     */
    public function installAction() {

        // Install the list of crs codes and stations
        $stations = $this->getLibrary('Stations');


    }
}
