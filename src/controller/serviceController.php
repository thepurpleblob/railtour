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

        $allservices = \ORM::forTable('Service')->order_by_asc('date')->findMany();

        // submitted year
        $thisyear = date('Y');
        $filteryear = $this->getParam('filter_year', 0);
        if ($filteryear) {
            $this->setSession('filteryear', $filteryear);
        } else {
            $filteryear = $this->getFromSession('filteryear', $thisyear);
        }
        
        // get possible years and filter results
        // shouldn't have to do this in PHP but Doctrine sucks badly!
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
        $service = \ORM::forTable('Service')->findOne($id);

        if (!$service) {
            throw $this->Exception('Unable to find Service entity.');
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
        $service = \ORM::forTable('Service')->findOne($id);

        if (!$service) {
            throw $this->Exception('Unable to find Service entity.');
        }

        // Get the other information stored for this service
        $destinations = \ORM::forTable('Destination')->where('serviceid', $id)->findMany();
        $pricebandgroups = \ORM::forTable('Pricebandgroup')->where('serviceid', $id)->findMany();
        $joinings = \ORM::forTable('Joining')->where('serviceid', $id)->findMany();

        // iterate over these and get destinations
        // (very inefficiently)
        $booking = $this->getService('Booking');
        foreach ($pricebandgroups as $band) {
            $pricebandgroupid = $band->id;
            $bandtable = $booking->createPricebandTable($pricebandgroupid);
            $band->bandtable = $bandtable;
        }

        // add pricebandgroup names
        foreach ($joinings as $joining) {
            $pricebandgroup = \ORM::forTable('Pricebandgroup')->findOne($joining->pricebandgroupid);
            $joining->pricebandname = $pricebandgroup->name;
        }

        $this->View('service/show.html.twig', array(
            'service' => $service,
            'destinations' => $destinations,
            'pricebandgroups' => $pricebandgroups,
            'joinings' => $joinings,
            'serviceid' => $id,
        ));
    }

    /**
     * Displays a form to edit an existing Service entity or create a new one
     * @param int $id service id
     */
    public function editAction($id=null)
    {
        if ($id) {
            $service = \ORM::forTable('Service')->findOne($id);

            if (!$service) {
                throw $this->Exception('Unable to find Service.');
            }
        } else {
            $booking = $this->getService('Booking');
            $service = $booking->createService();
        }

        // hopefully no errors
        $errors = null;

        // anything submitted?
        if ($data = $this->getRequest()) {
            //echo "<pre>"; var_dump($data); die;

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
                'mealbvisible' => 'required|boolean',
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
                $errors = $this->gump->get_readable_errors(true);
            }

            //echo "<pre>"; var_dump($errors); die;
        }

        $this->View('service/edit.html.twig', array(
            'service'      => $service,
            'serviceid' => $id,
            'errors' => $errors
        ));
    }

    /**
     * Calls routines to set the system up
     * (hidden)
     */
    public function installAction() {

        // Install the list of crs codes and stations
        $stations = $this->getService('Stations');


    }
}
