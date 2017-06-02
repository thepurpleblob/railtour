<?php

namespace thepurpleblob\railtour\controller;

use thepurpleblob\core\coreController;

/**
 * Service controller.
 *
 */
class ServiceController extends coreController
{
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
     * Lists all Service entities.
     *
     */
    public function indexAction() {
        global $CFG;

        $this->require_login('ROLE_ORGANISER');

        $allservices = $this->adminlib->getServices();

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

            // munge some data for template while here
            $service->showbookingbutton = $service->visible and $CFG->enablebooking;

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

        // Create form
        $form = new \stdClass();
        $form->filter_year = $this->form->select('filter_year', 'Tour season', $filteryear, $years, '', 4, array(
            'class' => 'select_autosubmit'
        ));

        $this->View('service/index', array(
            'services' => $services,
            'is_services' => !empty($services),
            'enablebooking' => $enablebooking,
            'form' => $form,
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

        $service = $this->adminlib->getService($id);

        if (!$service) {
            throw new \Exception('Unable to find Service entity.');
        }

        if (($visible != 1) && ($visible != 0)) {
            throw new \Exception('visible parameter must be 0 or 1');
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

        $service = $this->adminlib->getService($id);

        if (!$service) {
            throw new \Exception('Unable to find Service entity.');
        }

        // Get the other information stored for this service
        $destinations = $this->adminlib->getDestinations($id);
        $pricebandgroups = $this->adminlib->getPricebandgroups($id);
        $joinings = $this->adminlib->getJoinings($id);
        $limits = $this->adminlib->getLimits($id);

        $this->View('service/show', array(
            'service' => $service,
            'destinations' => $destinations,
            'isdestinations' => !empty($destinations),
            'pricebandgroups' => $pricebandgroups,
            'ispricebandgroups' => !empty($pricebandgroups),
            'isjoinings' => !empty($joinings),
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

        // ETicket options
        $etoptions = array(
            0 => 'Disabled',
            1 => 'Enabled - optional',
            2 => 'Enabled - forced',
        );

        // ETicket selected
        if ($service->eticketenabled) {
            $etselected = $service->eticketforce ? 2 : 1;
        } else {
            $etselected = 0;
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
                'eticket' => 'required|integer',
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

                // eticket
                if ($data['eticket'] == 0) {
                    $service->eticketenabled = 0;
                    $service->eticketforce = 0;
                } else {
                    $service->eticketenabled = 1;
                    $service->eticketforce = $data['eticket'] == 1 ? 0 : 1;
                }

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
            'etoptions' => $etoptions,
            'etselected' => $etselected,
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
