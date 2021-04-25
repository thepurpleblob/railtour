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
class ServiceController extends coreController {

    /**
     * Lists all Service entities.
     *
     * @throws \Exception
     */
    public function indexAction() {
        $this->require_login('ROLE_ORGANISER', 'service/index');

        $allservices = Admin::getServices();

        // submitted year
        $maxyear = Admin::getFilteryear();
        $filteryear = $this->getParam('filter_year', 0);
        if ($filteryear) {
            Session::write('filteryear', $filteryear);
        } else {
            $filteryear = Session::read('filteryear', $maxyear);
        }
        
        // get possible years and filter results
        $services = array();
        $years = array();
        $years['All'] = 'All';        
        foreach ($allservices as $service) {

            // munge some data for template while here
            $service->showbookingbutton = $service->visible and $_ENV['enablebooking'];

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
        $enablebooking = $_ENV['enablebooking'];

        // Create form
        $form = new \stdClass();
        $form->filter_year = Form::select('filter_year', 'Tour season', $filteryear, $years, '', array(
            '@change' => 'datechange()'
        ));

        $this->View('service/index', array(
            'services' => Admin::formatServices($services),
            'is_services' => !empty($services),
            'enablebooking' => $enablebooking,
            'form' => $form,
            'years' => $years,
            'filteryear' => $filteryear,
        ));
    }

    /**
     * flip service visibility
     * @param int $id service id
     * @param bool $visible
     * @throws \Exception
     */
    public function visibleAction($id, $visible) {

        $this->require_login('ROLE_ORGANISER', 'service/visible/' . $id . '/' . $visible);

        $service = Admin::getService($id);

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
     * @param int $id
     */
    public function showAction($id)
    {
        $this->require_login('ROLE_ORGANISER', 'service/show/' . $id);

        $service = Admin::getService($id);

        // Get the other information stored for this service
        $destinations = Admin::getDestinations($id);
        $pricebandgroups = Admin::getPricebandgroups($id);
        $joinings = Admin::getJoinings($id);
        $limits = Admin::getLimits($id);

        $this->View('service/show', array(
            'service' => Admin::formatService($service),
            'destinations' => $destinations,
            'isdestinations' => !empty($destinations),
            'pricebandgroups' => Admin::mungePricebandgroups($pricebandgroups),
            'ispricebandgroups' => !empty($pricebandgroups),
            'isjoinings' => !empty($joinings),
            'joinings' => $joinings,
            'limits' => $limits,
            'serviceid' => $id,
            'saved' => Session::read('saved_service', 0),
        ));
    }

    /**
     * Displays a form to edit an existing Service entity or create a new one
     * @param int $id service id
     * @throws \Exception
     */
    public function editAction($id=null)
    {
        $this->require_login('ROLE_ADMIN', 'service/edit/' . $id);

        // Get or create service
        if ($id) {
            $service = Admin::getService($id);
        } else {
            $service = Admin::createService();
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

        // Create form
        $form = new \stdClass;
        $form->code = Form::text('code', 'Code', $service->code, FORM_REQUIRED );
        $form->name = Form::text('name', 'Name', $service->name, FORM_REQUIRED );
        $form->description = Form::textarea('description', 'Description', $service->description, FORM_REQUIRED, ['v-model' => 'description'] );
        $form->visible = Form::yesno('visible', 'Visible', $service->visible);
        $form->date = Form::date('date', 'Date', $service->date, FORM_REQUIRED);
        $form->singlesupplement = Form::text('singlesupplement', 'Single supplement', $service->singlesupplement);
        $form->commentbox = Form::yesno('commentbox', 'Comment box', $service->commentbox);
        $form->eticket = Form::select('eticket', 'ETicket mode', $service->eticket, $etoptions);
        $form->mealsinfirst = Form::yesno('mealsinfirst', 'Meals available in First', $service->mealsinfirst);
        $form->mealsinstandard = Form::yesno('mealsinstandard', 'Meals available in Standard', $service->mealsinstandard);
        $form->mealaname = Form::text('mealaname', '', $service->mealaname, FORM_REQUIRED);
        $form->mealavisible = Form::yesno('mealavisible', '', $service->mealavisible);
        $form->mealaprice = Form::text('mealaprice', '', $service->mealaprice);
        $form->mealbname = Form::text('mealbname', '', $service->mealbname, FORM_REQUIRED);
        $form->mealbvisible = Form::yesno('mealbvisible', '', $service->mealbvisible);
        $form->mealbprice = Form::text('mealbprice', '', $service->mealbprice);
        $form->mealcname = Form::text('mealcname', '', $service->mealcname, FORM_REQUIRED);
        $form->mealcvisible = Form::yesno('mealcvisible', '', $service->mealcvisible);
        $form->mealcprice = Form::text('mealcprice', '', $service->mealcprice);
        $form->mealdname = Form::text('mealdname', '', $service->mealdname, FORM_REQUIRED);
        $form->mealdvisible = Form::yesno('mealdvisible', '', $service->mealdvisible);
        $form->mealdprice = Form::text('mealdprice', '', $service->mealdprice);

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
                'mealsinfirst' => 'required|integer',
                'mealsinstandard' => 'required|integer',
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
                $dp = date_parse_from_format('Y-m-d', $data['date']);
                $service->date = $dp['year'] . '-' . $dp['month'] . '-' . $dp['day'];
                $service->commentbox = $data['commentbox'];
                $service->mealsinfirst = $data['mealsinfirst'];
                $service->mealsinstandard = $data['mealsinstandard'];
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
                Session::writeFlash('saved_service', 1);

                $id = $service->id();
                $this->redirect('service/show/' . $id);
            } else {
                $errors = $this->gump->get_readable_errors(false);
            }
        }

        $this->View('service/edit', array(
            'service'  => $service,
            'serviceid' => $id,
            'form' => $form,
            'etoptions' => $etoptions,
            'etselected' => $etselected,
            'errors' => $errors,
            'apicall' => $this->Url('/service/jsonservice/' . $id),
        ));
    }

    /**
     * Duplicate a complete service
     * @param int $serviceid
     */
    public function duplicateAction($serviceid) {
        $this->require_login('ROLE_ADMIN', 'service/duplicate/' . $serviceid);

        $service = Admin::getService($serviceid);

        $newservice = Admin::duplicate($service);

        $this->redirect('service/edit/' . $newservice->id);
    }

    /**
     * Delete a service provided that there are no purchases
     * @param int $serviceid
     */
    public function deleteAction($serviceid) {
        $this->require_login('ROLE_ADMIN', 'service/delete/' . $serviceid);

        $service = Admin::getService($serviceid);

        // If there are purchases, we're out of here
        if (Admin::is_purchases($serviceid)) {
            $haspurchases = true;
        } else {
            $haspurchases = false;

            // anything submitted?
            if ($data = $this->getRequest()) {

                // Delete?
                if (!empty($data['delete'])) {
                    Admin::deleteService($service);
                }
                $this->redirect('service/index');
            }
        }

        $this->View('service/delete', array(
            'service' => $service,
            'haspurchases' => $haspurchases,
        ));
    }

    /**
     * API to get service as JSON
     * @param service id
     */
    public function jsonserviceAction($serviceid) {
        $this->require_login('ROLE_ADMIN');
        $service = Admin::getService($serviceid);

        header('Content-type:application/json;charset=utf-8');
        echo json_encode($service->as_array());
    }

}
