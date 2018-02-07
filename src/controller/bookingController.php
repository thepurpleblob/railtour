<?php

namespace thepurpleblob\railtour\controller;

use thepurpleblob\core\coreController;

class BookingController extends coreController {

    public $controller;

    private $bookinglib;

    /**
     * Constructor
     * @param bool
     */
    public function __construct($exception = false) {
        parent::__construct($exception);

        // Library
        $this->bookinglib = $this->getLibrary('Booking');
    }

    /**
     * Show terms and conditions page
     */
    public function termsAction() {
        $this->view('booking/terms');
    }

    /**
     * Opening page for booking.
     * @param $code string unique (hopefully) tour code
     */
    public function indexAction($code) {

        // Log
        $this->log('Booking started ' . $code);

        // Clear session and delete expired purchases
        $this->bookinglib->cleanPurchases();

        // Get the service object
        $service = $this->bookinglib->serviceFromCode($code);
        $serviceid = $service->id;

        // count the seats left
        $count = $this->bookinglib->countStuff($serviceid);

        // Get the limits for this service
        $limits = $this->bookinglib->getLimits($serviceid);

        // get acting maxparty (best estimate to display to punter)
        $maxparty = $this->bookinglib->getMaxparty($limits);

        if ($this->bookinglib->canProceedWithBooking($service, $count)) {
            $this->View('booking/index', array(
                'code' => $code,
                'maxparty' => $maxparty,
                'service' => $service
            ));
        } else {
             $this->View('booking/closed', array(
                'code' => $code,
                'service' => $service
            ));
        }
    }

    /**
     * Opening page for *telephone* bookings.
     * @param $code string unique (hopefully) tour code
     * @throws \Exception
     */
    public function telephoneAction($code)
    {
        // Security
        $this->require_login('ROLE_TELEPHONE', 'booking/telephone/' . $code);

        // Log
        $this->log('Booking started ' . $code);

        // Clear session and delete expired purchases
        $this->bookinglib->cleanPurchases();

        // Get the service object
        $service = $this->bookinglib->serviceFromCode($code);
        $serviceid = $service->id;

        // count the seats left
        $count = $this->bookinglib->countStuff($serviceid);

        // Get the limits for this service
        $limits = $this->bookinglib->getLimits($serviceid);

        // get acting maxparty (best estimate to display to punter)
        $maxparty = $this->bookinglib->getMaxparty($limits);

        // Bail out if this service is unavailable
        if (!$this->bookinglib->canProceedWithBooking($service, $count)) {
            $this->View('booking/closed', array(
                'code' => $code,
                'service' => $service
            ));
        }

        // Grab current purchase
        $purchase = $this->bookinglib->getSessionPurchase($serviceid);

        // hopefully no errors
        $errors = null;

        // anything submitted?
        if ($data = $this->getRequest()) {

            // Cancel?
            if (!empty($data['cancel'])) {
                $this->redirect('admin/main', true);
            }

            // Validate
            $this->gump->validation_rules(array(
                'firstname' => 'required',
                'surname' => 'required',
                'postcode' => 'required',
            ));
            $this->gump->set_field_names(array(
                'firstname' => 'First name',
                'surname' => 'Last name',
                'postcode' => 'Post code',
            ));
            if ($data = $this->gump->run($data)) {

                // Now need to 'normalise' some of the fields
                $purchase->title = ucwords($data['title']);
                $purchase->surname = ucwords($data['surname']);
                $purchase->firstname = ucwords($data['firstname']);
                $purchase->postcode = strtoupper($data['postcode']);

                // Get the booker's username and mark the purchase
                // as a "customer not present" transaction.
                if (!$user = $this->getUser()) {
                    throw new \Exception('User record unexpectedly not found in session!');
                }
                $username = $user->username;

                // This means it's a telephone booking
                $purchase->bookedby = $username;
                $purchase->save();

                $this->redirect('booking/telephone2');
            }
        }

        // Create form
        $form = new \stdClass;
        $form->title = $this->form->text('title', 'Title', $purchase->title);
        $form->firstname = $this->form->text('firstname', 'First name', $purchase->firstname, FORM_REQUIRED);
        $form->surname = $this->form->text('surname', 'Surname', $purchase->surname, FORM_REQUIRED);
        $form->postcode = $this->form->text('postcode', 'Post code', $purchase->postcode, FORM_REQUIRED);

        $this->View('booking/telephone', array(
            'code' => $code,
            'maxparty' => $maxparty,
            'service' => $service,
            'purchase' => $purchase,
            'form' => $form,
            'errors' => $errors,
        ));
    }

    /**
     * Check if old purchase exists for telephone bookings
     * @param int $id of old purchase
     */
    public function telephone2Action($purchaseid = 0) {

        // Basics
        $purchase = $this->bookinglib->getSessionPurchase();
        $serviceid = $purchase->serviceid;
        $service = $this->bookinglib->getService($serviceid);
        $this->require_login('ROLE_TELEPHONE', 'booking/joining');

        // Search for old purchases
        $oldpurchases = $this->bookinglib->findOldPurchase($purchase);

        // If purchase id provided, make sure it is valid
        if ($purchaseid) {
            $oldpurchase = $this->bookinglib->checkPurchaseID($purchaseid, $oldpurchases);

            // Copy address data
            if (empty($purchase->title)) {
                $purchase->title = $oldpurchase->title;
            }
            $purchase->address1 = $oldpurchase->address1;
            $purchase->address2 = $oldpurchase->address2;
            $purchase->city = $oldpurchase->city;
            $purchase->county = $oldpurchase->county;
            $purchase->phone = $oldpurchase->phone;
            $purchase->email = $oldpurchase->email;
            $purchase->save();
            $this->redirect('booking/numbers/' . $serviceid);
        }

        // Should have a postcode
        if ($oldpurchases = $this->bookinglib->findOldPurchase($purchase)) {
            $this->View('booking/telephone2', array(
                'purchases' => $oldpurchases,
                'service' => $service,
            ));
        } else {
            $this->redirect('booking/numbers/' . $serviceid);
        }

    }

    /**
     * First 'proper' booking page.
     * Ask for numbers of travellers. Also sets up purchase record
     * and session data.
     * @param int $serviceid
     * @throws \Exception
     */
    public function numbersAction($serviceid) {
        // Basics
        $service = $this->bookinglib->getService($serviceid);

        // Get the limits for this service:
        $limits = $this->bookinglib->getLimits($serviceid);

        // Grab current purchase
        $purchase = $this->bookinglib->getSessionPurchase($serviceid);
        if ($purchase->bookedby) {
            $this->require_login('ROLE_TELEPHONE', 'booking/numbers/' . $serviceid);
        }

        // get acting maxparty
        $maxparty = $this->bookinglib->getMaxparty($limits);

        // Choices
        $choices_adult = $this->bookinglib->choices($maxparty, false);
        $choices_children = $this->bookinglib->choices($maxparty, true);

        // hopefully no errors
        $errors = null;

        // anything submitted?
        if ($data = $this->getRequest()) {

            // Cancel?
            if (!empty($data['cancel'])) {
                $this->redirect('admin/main', true);
            }

            // Validate
            $this->gump->validation_rules(array(
                'adults' => 'required|integer|min_numeric,1|max_numeric,' . $maxparty,
                'children' => 'required|integer|min_numeric,0|max_numeric,' . $maxparty,
            ));
            $this->gump->set_field_names(array(
                'adults' => 'Number of adults',
                'children' => 'Number of children',
            ));
            if ($data = $this->gump->run($data)) {

                // check numbers
                $adults = $data['adults'];
                $children = $data['children'];
                if (($adults + $children) > $maxparty) {
                    $errors[] = 'Total number of travellers must be ' . $maxparty . ' or fewer';
                } else if (($adults<1) or ($adults>$maxparty) or ($children<0) or ($children>$maxparty)) {
                    $errors[] = 'Value supplied out of range.';
                } else {
                    $purchase->adults = $adults;
                    $purchase->children = $children;
                    $purchase->save();

                    $this->redirect('booking/joining');
                }

            }  else {
                $errors = array_merge($errors, $this->gump->get_readable_errors());
            }
        }

        // Create form
        $form = new \stdClass();
        $form->adults = $this->form->select('adults', 'Number of adults', $purchase->adults, $choices_adult);
        $form->children = $this->form->select('children', 'Number of children (14 and under)', $purchase->children, $choices_children);


        // display form
        $this->View('booking/numbers', array(
            'purchase' => $purchase,
            'service' => $service,
            'form' => $form,
            'limits' => $limits,
            'maxparty' => $maxparty,
            'errors' => $errors,
        ));
    }

    /**
     * Joining station
     */
    public function joiningAction() {

        // Basics
        $purchase = $this->bookinglib->getSessionPurchase();
        $serviceid = $purchase->serviceid;
        $service = $this->bookinglib->getService($serviceid);

        if ($purchase->bookedby) {
            $this->require_login('ROLE_TELEPHONE', 'booking/joining');
        }

        // get the joining stations
        $stations = $this->bookinglib->getJoiningStations($serviceid);

        // If there is only one then there is nothing to do
        if (count($stations)==1) {
            reset($stations);
            $purchase->joining = key($stations);
            $purchase->save();

            $this->redirect('booking/destination');
        }

        // hopefully no errors
        $errors = null;

        // anything submitted?
        if ($data = $this->getRequest()) {

            // Cancel?
            if (!empty($data['back'])) {
                $this->redirect('booking/numbers/' . $serviceid, true);
            }

            // Validate
            $this->gump->validation_rules(array(
                'joining' => 'required',
            ));
            $this->gump->set_field_names(array(
                'joining' => 'Joining station',
            ));
            if ($data = $this->gump->run($data)) {

                // check crs is valid
                $joining = trim($data['joining']);
                if (empty($stations[$joining])) {
                    throw new \Exception('No CRS code returned from form');
                }
                $purchase->joining = $joining;
                $purchase->save();
                $this->redirect('booking/destination');
            }
        }

        // Create form
        $form = new \stdClass();
        $form->joining = $this->form->radio('joining', '', $purchase->joining, $stations);

        // display form
        $this->View('booking/joining', array(
            'purchase' => $purchase,
            'form' => $form,
            'code' => $purchase->code,
            'service' => $service,
            'errors' => $errors,
        ));
    }

    /**
     * @param string $crs
     * @throws \Exception
     */
    public function destinationAction($crs = '')
    {
        // Basics
        $purchase = $this->bookinglib->getSessionPurchase();
        $serviceid = $purchase->serviceid;
        $service = $this->bookinglib->getService($serviceid);

        if ($purchase->bookedby) {
            $this->require_login('ROLE_TELEPHONE', 'booking/destination');
        }

        // get the destinations
        $stations = $this->bookinglib->getDestinationStations($serviceid);

        // If there is only one then there is nothing to do
        if (count($stations)==1) {
            reset($stations);
            $purchase->destination = key($stations);
            $purchase->save();

            $this->redirect('booking/meals');
        }

        // Get destinations with extra pricing information
        $destinations = $this->bookinglib->getDestinationsExtra($purchase, $service);

        // anything submitted?
        // Will only apply to back in this case
        if ($data = $this->getRequest()) {

            // Cancel?
            if (!empty($data['back'])) {
                $this->redirect('booking/joining/' . $serviceid, true);
            }
        }

        // Just links this time, CRS will be in the URL path.
        if ($crs) {

            // check crs is valid
            if (empty($stations[$crs])) {
                throw new \Exception('No valid CRS code returned from destination form  (supplied was ' . $crs . ')');
            }
            $purchase->destination = $crs;
            $purchase->save();
            $this->redirect('booking/meals');
        }

        // display form
        $this->View('booking/destination', array(
            'purchase' => $purchase,
            'destinations' => $destinations,
            'service' => $service,
        ));
    }

    /**
     * @throws \Exception
     */
    public function mealsAction() {

        // Basics
        $purchase = $this->bookinglib->getSessionPurchase();
        $serviceid = $purchase->serviceid;
        $service = $this->bookinglib->getService($serviceid);

        if ($purchase->bookedby) {
            $this->require_login('ROLE_TELEPHONE', 'booking/meals');
        }

        // If there are no meals on this service just bail
        if (!$this->bookinglib->mealsAvailable($service)) {
            $this->redirect('booking/class');
        }

        // Array of meal options for forms
        $meals = $this->bookinglib->mealsForm($service, $purchase);

        // Create validation
        $gumprules = array();
        $fieldnames = array();
        foreach ($meals as $meal) {
            $gumprules[$meal->formname] = 'required|integer|min_numeric,0|max_numeric,' . $meal->maxmeals;
            $fieldnames[$meal->formname] = $meal->name;
            $meal->formselect = $this->form->select($meal->formname, $meal->label, $meal->purchase, $meal->choices);
        }

        // hopefully no errors
        $errors = null;

        // anything submitted?
        if ($data = $this->getRequest()) {

            // Cancel?
            if (!empty($data['back'])) {

                // We need to know if Destinations would have been displayed
                $stations = $this->bookinglib->getDestinationStations($serviceid);
                if (count($stations) > 1) {
                    $this->redirect('booking/destination', true);
                } else {
                    $this->redirect('booking/joining', true);
                }
            }

            // Validate
            $this->gump->validation_rules($gumprules);
            $this->gump->set_field_names($fieldnames);
            if ($data = $this->gump->run($data)) {
                foreach ($meals as $meal) {
                    $name = $meal->formname;
                    $purchase->$name = $data[$name];
                }
                $purchase->save();
                $this->redirect('booking/class');
            }
        }

        // display form
        $this->View('booking/meals', array(
            'purchase' => $purchase,
            'service' => $service,
            'meals' => $meals,
            'errors' => $errors,
        ));
    }

   /**
    * Book class (first/standard)
    */
   public function classAction($class = '') {

        // Basics
        $purchase = $this->bookinglib->getSessionPurchase();
        $serviceid = $purchase->serviceid;
        $service = $this->bookinglib->getService($serviceid);

        if ($purchase->bookedby) {
            $this->require_login('ROLE_TELEPHONE', 'booking/class');
        }

        // Get the limits for this service:
        $limits = $this->bookinglib->getLimits($serviceid);

        // get acting maxparty
        $maxpartystandard = $this->bookinglib->getMaxparty($limits);

        // get first and standard maximum parties
        $maxpartyfirst = $limits->maxpartyfirst ? $limits->maxpartyfirst : $maxpartystandard;

        // Get the passenger count
        $passengercount = $purchase->adults + $purchase->children;

        // get first and standard fares
        $farestandard = $this->bookinglib->calculateFare($service, $purchase, 'S');
        $farefirst = $this->bookinglib->calculateFare($service, $purchase, 'F');

        // we need to know about the number
        // it's a bodge - but if the choice is made then skip this check
        $numbers = $this->bookinglib->countStuff($serviceid, $purchase);
        $availablefirst = $numbers->remainingfirst >= $passengercount;
        $availablestandard = $numbers->remainingstandard >= $passengercount;

        // still might not be available if passengercount exceeds ruling maxparty
        if ($passengercount > $maxpartyfirst) {
            $availablefirst = false;
        }
        if ($passengercount > $maxpartystandard) {
            $availablestandard = false;
        }

        // anything submitted?
        // Will only apply to back in this case
        if ($data = $this->getRequest()) {

            // Cancel?
            if (!empty($data['back'])) {
                $this->redirect('booking/meals', true);
            }
        }

        // Data will come from link
        if ($class) {
            if (($class=='F' and $availablefirst) or ($class=='S' and $availablestandard)) {
                $purchase->class = $class;
                $purchase->save();
                $this->redirect('booking/additional');
            }
        }

        // display form
        $this->View('booking/class', array(
            'purchase' => $purchase,
            'service' => $service,
            'farefirst' => $farefirst,
            'farestandard' => $farestandard,
            'availablefirst' => $availablefirst,
            'availablestandard' => $availablestandard,
            'childname' => $purchase->children == 1 ? 'child' : 'children',
            'adultname' => $purchase->adults == 1 ? 'adult' : 'adults',
        ));
    }

    /**
     * Ask, if appropriate/enabled, for comments
     * and/or first class supplements
     */
    public function additionalAction() {

        // Basics
        $purchase = $this->bookinglib->getSessionPurchase();
        $serviceid = $purchase->serviceid;
        $service = $this->bookinglib->getService($serviceid);

        if ($purchase->bookedby) {
            $this->require_login('ROLE_TELEPHONE', 'booking/additional');
        }

        // current counts
        $numbers = $this->bookinglib->countStuff($serviceid, $purchase);

        // Get the passenger count
        $passengercount = $purchase->adults + $purchase->children;

        // This page will only be shown if we are going to ask about firstsingle
        // option, or ask for comments. Telephone bookings always allow comments.
        $iscomments = $service->commentbox || $purchase->bookedby;
        $issupplement = ($numbers->remainingfirstsingles >= $passengercount)
                && ($purchase->class == 'F')
                && (($passengercount == 1) || ($passengercount==2))
                ;
        if (!($iscomments or $issupplement)) {
            if ($this->back) {
                redirect('booking/class');
            } else {
                $this->redirect('booking/personal');
            }
        }

        // hopefully no errors
        $errors = null;

        // anything submitted?
        if ($data = $this->getRequest()) {

            // Cancel?
            if (!empty($data['back'])) {
                $this->redirect('booking/class', true);
            }

            $purchase->comment = empty($data['comment']) ? '' : $data['comment'];
            $purchase->seatsupplement = empty($data['seatsupplement']) ? 0 : 1;
            $purchase->save();

            $this->redirect('booking/personal');
        }

        // Create form
        $form = new \stdClass;
        $form->comment = $this->form->text('comment', '', $purchase->comment);
        $form->seatsupplement = $this->form->yesno('seatsupplement', 'Window seats in first class', $purchase->seatsupplement);

        // display form
        $this->View('booking/additional', array(
            'purchase' => $purchase,
            'service' => $service,
            'form' => $form,
            'iscomments' => $iscomments,
            'issupplement' => $issupplement,
        ));
    }

    /**
     * Get contact and ticket deliver details
    */
    public function personalAction() {
        // Basics
        $purchase = $this->bookinglib->getSessionPurchase();
        $serviceid = $purchase->serviceid;
        $service = $this->bookinglib->getService($serviceid);

        if ($purchase->bookedby) {
            $this->require_login('ROLE_TELEPHONE', 'booking/personal');
        }

        // hopefully no errors
        $errors = null;

        // anything submitted?
        if ($data = $this->getRequest()) {

            // Cancel?
            if (!empty($data['back'])) {
                $this->redirect('booking/additional', true);
            }

            // Validate
            $gumprules = array(
                'surname' => 'required',
                'firstname' => 'required',
                'address1' => 'required',
                'city' => 'required',
                'postcode' => 'required',
            );
            if (!$purchase->bookedby) {
                $gumprules['email'] = 'required';
            }
            $this->gump->validation_rules($gumprules);
            $this->gump->set_field_names(array(
                'surname' => 'Surname',
                'firstname' => 'First name',
                'address1' => 'Address line 1',
                'city' => 'City',
                'postcode' => 'Post code',
                'email' => 'Email',
            ));
            if ($data = $this->gump->run($data)) {

                // Now need to 'normalise' some of the fields
                $purchase->title = ucwords($data['title']);
                $purchase->surname = ucwords($data['surname']);
                $purchase->firstname = ucwords($data['firstname']);
                $purchase->address1 = ucwords($data['address1']);
                $purchase->address2 = ucwords($data['address2']);
                $purchase->city = ucwords($data['city']);
                $purchase->county = ucwords($data['county']);
                $purchase->postcode = strtoupper($data['postcode']);
                $purchase->phone = $data['phone'];
                $purchase->email = strtolower($data['email']);

                // eticket is optional
                if (!$service->eticketenabled) {
                    $purchase->eticket = 0;
                } else {
                    $purchase->eticket = $service->eticketoptional ? $data['eticket'] : 1;
                }

                $purchase->save();
                $this->redirect('booking/review');
            }
        }

        // Create form
        $form = new \stdClass;
        $form->title = $this->form->text('title', 'Title', $purchase->title);
        $form->firstname = $this->form->text('firstname', 'First name', $purchase->firstname, FORM_REQUIRED);
        $form->surname = $this->form->text('surname', 'Surname', $purchase->surname, FORM_REQUIRED);
        $form->address1 = $this->form->text('address1', 'Address line 1', $purchase->address1, FORM_REQUIRED);
        $form->address2 = $this->form->text('address2', 'Address line 2', $purchase->address2);
        $form->city = $this->form->text('city', 'Town / city', $purchase->city, FORM_REQUIRED);
        $form->county = $this->form->text('county', 'County', $purchase->county);
        $form->postcode = $this->form->text('postcode', 'Post code', $purchase->postcode, FORM_REQUIRED);
        $form->phone = $this->form->text('phone', 'Telephone', $purchase->phone, FORM_OPTIONAL, null, 'tel');
        $form->email = $this->form->text('email', 'Email', $purchase->email, $purchase->bookedby ? FORM_OPTIONAL : FORM_REQUIRED, null, 'email');

        // display form
        $this->View('booking/personal', array(
            'purchase' => $purchase,
            'form' => $form,
            'service' => $service,
        ));
    }

    /**
     * Last chance to check details
     */
    public function reviewAction() {

        // Basics
        $purchase = $this->bookinglib->getSessionPurchase();
        $serviceid = $purchase->serviceid;
        $service = $this->bookinglib->getService($serviceid);

       if ($purchase->bookedby) {
           $this->require_login('ROLE_TELEPHONE', 'booking/review');
       }

        // work out final fare
        $fare = $this->bookinglib->calculateFare($service, $purchase, $purchase->class);
        $purchase->payment = $fare->total;
        $purchase->save();

        // get the destination
        $destination = $this->bookinglib->getDestinationCRS($serviceid, $purchase->destination);

        // get the joining station
        $joining = $this->bookinglib->getJoiningCRS($serviceid, $purchase->joining);

        // display form
        $this->View('booking/review', array(
            'purchase' => $purchase,
            'service' => $service,
            'destination' => $destination,
            'joining' => $joining,
            'class' => $purchase->class == 'F' ? 'First' : 'Standard',
            'fare' => $fare,
            'formatteddate'=> date('d/m/Y', strtotime($service->date)),
        ));
    }

    /**
     * This is a bit different - we get here from the
     * review page, only if the form is submitted.
     * This action sends the payment registration to SagePay
     */
    public function paymentAction() {

        // Basics
        $purchase = $this->bookinglib->getSessionPurchase();
        $serviceid = $purchase->serviceid;
        $service = $this->bookinglib->getService($serviceid);

        if ($purchase->bookedby) {
           $this->require_login('ROLE_TELEPHONE', 'booking/payment');
        }

        // work out final fare
        $fare = $this->bookinglib->calculateFare($service, $purchase, $purchase->class);

        // Line up Sagepay class
        $sagepay = $this->getLibrary('SagepayServer');
        $sagepay->setService($service);
        $sagepay->setPurchase($purchase);
        $sagepay->setFare($fare);

        // anything submitted?
        if ($data = $this->getRequest()) {

            // Anything other than 'next' jumps back
            if (empty($data['next'])) {
                $this->redirect('booking/personal', true);
            }

            // If we get here we can process SagePay stuff
            // Register payment with Sagepay
            $sr = $sagepay->register();

            // If false is returned then it went wrong
            if ($sr === false) {
                $this->View('booking/fail', array(
                    'status' => 'N/A',
                    'diagnostic' => $sagepay->error,
                ));
            }

            // check status of registration from SagePay
            $status = $sr['Status'];
            if (($status != 'OK') && ($status != 'OK REPEATED')) {
                $this->View('booking/fail', array(
                    'status' => $status,
                    'diagnostic' => $sr['StatusDetail'],
                ));
            }

            // update purchase
            $purchase->securitykey = $sr['SecurityKey'];
            $purchase->regstatus = $status;
            $purchase->VPSTxId = $sr['VPSTxId'];
            $purchase->save();

            // redirect to Sage
            $url = $sr['NextURL'];
            header("Location: $url");
            die;
        }
    }

    /**
     * Sagepay sends a POST notification when the payment is complete
     * NB: We *cannot* assume anything about our payment timeout anymore
     * Bear in mind that we can't interact with the user either (server-2-server)
     * @return mixed
     * @throws \Exception
     */
    public function notificationAction() {
        global $CFG;

        // Library stuff
        $sagepay = $this->getLibrary('SagepayServer');

        // POST data from SagePay
        $data = $sagepay->getNotification();

        // Log the notification data to debug file (in case it's interesting)
        $this->log(var_export($data, true));

        // Get the VendorTxCode and use it to look up the purchase
        $VendorTxCode = $data['VendorTxCode'];
        if (!$purchase = $this->bookinglib->getPurchaseFromVendorTxCode($VendorTxCode)) {
            $url = $this->Url('booking/fail') . '/' . $VendorTxCode . '/' . urlencode('Purchase record not found');
            $this->log('SagePay notification: Purchase not found - ' . $url);
            $sagepay->notificationreceipt('INVALID', $url, 'Purchase record not found');
            die;
        }

        // Mailer
        $this->log('get mail library');
        $mail = $this->getLibrary('Mail');
        $this->log('mail initialise');
        $mail->initialise($purchase);
        $this->log('mail extra recipients');
        $mail->setExtrarecipients($CFG->backup_email);

        // Now that we have the purchase object, we can save whatever we got back in it
        $this->bookinglib->updatePurchase($purchase, $data);
        $this->log('Purchase updated');

        // Check VPSSignature for validity
        if (!$sagepay->checkVPSSignature($purchase, $data)) {
            $url = $this->Url('booking/fail') . '/' . $VendorTxCode . '/' . urlencode('VPSSignature not matched');
            $this->log('SagePay notification: VPS sig no match - ' . $url);
            $sagepay->notificationreceipt('INVALID', $url, 'VPSSignature not matched');
            die;
        }

        // Check Status.
        // Work out what next action should be
        $status = $purchase->status;
        $this->log('Status code checking - ' . $status)
        if ($status == 'OK') {

            // Send confirmation email
            $url = $this->Url('booking/complete') . '/' . $VendorTxCode;
            $mail->confirm();
            $this->log('SagePay notification: Confirm sent - ' . $url);
            $sagepay->notificationreceipt('OK', $url, '');
        } else if ($status == 'ERROR') {
            $url = $this->Url('booking/fail') . '/' . $VendorTxCode . '/' . urlencode($purchase->statusdetail);
            $this->log('SagePay notification: Booking fail - ' . $url);
            $mail->decline();
            $sagepay->notificationreceipt('OK', $url, $purchase->statusdetail);
        } else {
            $url = $this->Url('booking/decline') . '/' . $VendorTxCode;
            $this->log('SagePay notification: Booking decline - ' . $url);
            $mail->decline();
            $sagepay->notificationreceipt('OK', $url, $purchase->statusdetail);
        }

        die;
    }

    /**
     * Fail action - we get here if we return error to SagePay
     * SagePay then redirects here
     * @param string $VendorTxCode
     * @param string $message
     */
    public function failAction($VendorTxCode, $message) {
        $message = urldecode($message);
        if (!$purchase = $this->bookinglib->getPurchaseFromVendorTxCode($VendorTxCode)) {
            $this->View('booking/fail', array(
                'status' => 'N/A',
                'diagnostic' => 'Purchase record could not be found for ' . $VendorTxCode . ' Plus ' . $message,
                'servicename' => '',
            ));
        } else {
            $service = $this->bookinglib->getService($purchase->serviceid);
            $this->View('booking/fail', array(
                'status' => 'N/A',
                'diagnostic' => $message,
                'servicename' => $service->name,
            ));
        }
    }

    /**
     * Complete action - SagePay returns here when all successful
     * SagePay then redirects here
     * @param string $VendorTxCode
     */
    public function completeAction($VendorTxCode) {
        if (!$purchase = $this->bookinglib->getPurchaseFromVendorTxCode($VendorTxCode)) {
            $this->View('booking/fail', array(
                'status' => 'N/A',
                'diagnostic' => 'Purchase record could not be found for ' . $VendorTxCode,
                'servicename' => '',
            ));
        } else {
            $path = $purchase->bookedby ? 'booking/telephonecomplete' : 'booking/complete';
            $service = $this->bookinglib->getService($purchase->serviceid);
            $this->View($path, array(
                'purchase' => $purchase,
                'service' => $service,
            ));
        }
    }

    /**
     * Decline action - SagePay returns here when payment declined
     * SagePay then redirects here
     * @param string $VendorTxCode
     */
    public function declineAction($VendorTxCode) {
        if (!$purchase = $this->bookinglib->getPurchaseFromVendorTxCode($VendorTxCode)) {
            $this->View('booking/fail', array(
                'status' => 'N/A',
                'diagnostic' => 'Purchase record could not be found for ' . $VendorTxCode,
            ));
        } else {
            $service = $booking->getService($purchase->serviceid);
            $this->View('booking/decline', array(
                'purchase' => $purchase,
                'service' => $service,
            ));
        }
    }

}

