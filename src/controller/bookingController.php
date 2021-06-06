<?php

namespace thepurpleblob\railtour\controller;

use thepurpleblob\core\coreController;
use thepurpleblob\railtour\library\Admin;
use thepurpleblob\railtour\library\Booking;
use thepurpleblob\core\Form;

class BookingController extends coreController {

    public $controller;

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
        if (Admin::cleanPurchases()) {
            $this->View('booking/timeout');
        }

        // Get the service object
        $service = Booking::serviceFromCode($code);
        $serviceid = $service->id;

        // count the seats left
        $count = Booking::countStuff($serviceid);

        // Get the limits for this service
        $limits = Admin::getLimits($serviceid);

        // get acting maxparty (best estimate to display to punter)
        $maxparty = Booking::getMaxparty($limits);

        if (Booking::canProceedWithBooking($service, $count)) {
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
        if (Admin::cleanPurchases()) {
            $this->View('booking/timeout');
        }

        // Get the service object
        $service = Booking::serviceFromCode($code);
        $serviceid = $service->id;

        // count the seats left
        $count = Booking::countStuff($serviceid);

        // Get the limits for this service
        $limits = Admin::getLimits($serviceid);

        // get acting maxparty (best estimate to display to punter)
        $maxparty = Booking::getMaxparty($limits);

        // Bail out if this service is unavailable
        if (!Booking::canProceedWithBooking($service, $count)) {
            $this->View('booking/closed', array(
                'code' => $code,
                'service' => $service
            ));
        }

        // Grab current purchase
        $purchase = Booking::getSessionPurchase($this, $serviceid);

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
        $form->title = Form::text('title', 'Title', $purchase->title);
        $form->firstname = Form::text('firstname', 'First name', $purchase->firstname, FORM_REQUIRED);
        $form->surname = Form::text('surname', 'Surname', $purchase->surname, FORM_REQUIRED);
        $form->postcode = Form::text('postcode', 'Post code', $purchase->postcode, FORM_REQUIRED);

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
        $purchase = Booking::getSessionPurchase($this);
        $serviceid = $purchase->serviceid;
        $service = Admin::getService($serviceid);
        $this->require_login('ROLE_TELEPHONE', 'booking/telephone2');

        // Search for old purchases
        $oldpurchases = Booking::findOldPurchase($purchase);

        // If purchase id provided, make sure it is valid
        if ($purchaseid) {
            $oldpurchase = Booking::checkPurchaseID($purchaseid, $oldpurchases);

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
            $this->redirect('booking/single/' . $serviceid);
        }

        // Should have a postcode
        if ($oldpurchases = Booking::findOldPurchase($purchase)) {
            $this->View('booking/telephone2', array(
                'purchases' => $oldpurchases,
                'service' => $service,
            ));
        } else {
            $this->redirect('booking/single/' . $serviceid);
        }

    }

    /**
     * Ask, if appropriate/enabled, for comments
     * and/or first class supplements
     */
    public function additionalAction() {

        // Basics
        $purchase = Booking::getSessionPurchase($this);
        $serviceid = $purchase->serviceid;
        $service = Admin::getService($serviceid);

        if ($purchase->bookedby) {
            $this->require_login('ROLE_TELEPHONE', 'booking/additional');
        }

        // current counts
        $numbers = Booking::countStuff($serviceid, $purchase);

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
                $this->redirect('booking/single/' . $serviceid, true);
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
                $this->redirect('booking/single/' . $serviceid, true);
            }

            $purchase->comment = empty($data['comment']) ? '' : $data['comment'];
            $purchase->comment = substr($purchase->comment, 0, 37);
            $purchase->seatsupplement = empty($data['seatsupplement']) ? 0 : 1;
            $purchase->save();

            $this->redirect('booking/personal');
        }

        // Create form
        $form = new \stdClass;
        $form->comment = Form::text('comment', '', $purchase->comment, false, ['maxlength' => 37]);
        $form->seatsupplement = Form::yesno('seatsupplement', 'Window seats in first class', $purchase->seatsupplement);

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
        $purchase = Booking::getSessionPurchase($this);
        $serviceid = $purchase->serviceid;
        $service = Admin::getService($serviceid);

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
                if (isset($data['email'])) {
                    $purchase->email = strtolower($data['email']);
                }    

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
        $form->title = Form::text('title', 'Title', $purchase->title);
        $form->firstname = Form::text('firstname', 'First name', $purchase->firstname, FORM_REQUIRED);
        $form->surname = Form::text('surname', 'Surname', $purchase->surname, FORM_REQUIRED);
        $form->address1 = Form::text('address1', 'Address line 1', $purchase->address1, FORM_REQUIRED);
        $form->address2 = Form::text('address2', 'Address line 2', $purchase->address2);
        $form->city = Form::text('city', 'Town / city', $purchase->city, FORM_REQUIRED);
        $form->county = Form::text('county', 'County', $purchase->county);
        $form->postcode = Form::text('postcode', 'Post code', $purchase->postcode, FORM_REQUIRED);
        $form->phone = Form::text('phone', 'Telephone', $purchase->phone, FORM_OPTIONAL, null, 'tel');
        $form->email = Form::text('email', 'Email', $purchase->email, $purchase->bookedby ? FORM_OPTIONAL : FORM_REQUIRED, null, 'email');

        // Do not show email field for telephone bookings if it is empty
        $showemail = (!$purchase->bookedby) || (!empty($purchase->email)); 

        // display form
        $this->View('booking/personal', array(
            'purchase' => $purchase,
            'form' => $form,
            'service' => $service,
            'showemail' => $showemail,
        ));
    }

    /**
     * Last chance to check details
     */
    public function reviewAction() {

        // Basics
        $purchase = Booking::getSessionPurchase($this);
        $serviceid = $purchase->serviceid;
        $service = Admin::getService($serviceid);

        if ($purchase->bookedby) {
            $this->require_login('ROLE_TELEPHONE', 'booking/review');
        }

        // work out final fare
        $fare = Booking::calculateFare($service, $purchase, $purchase->class);
        $purchase->payment = $fare->total;
        $purchase->save();

        // get the destination
        $destination = Booking::getDestinationCRS($serviceid, $purchase->destination);

        // get the joining station
        $joining = Booking::getJoiningCRS($serviceid, $purchase->joining);

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
        $purchase = Booking::getSessionPurchase($this);
        $serviceid = $purchase->serviceid;
        $service = Admin::getService($serviceid);

        if ($purchase->bookedby) {
           $this->require_login('ROLE_TELEPHONE', 'booking/payment');
        }

        // work out final fare
        $fare = Booking::calculateFare($service, $purchase, $purchase->class);

        // Line up Sagepay class
        $sagepay = $this->getLibrary('SagepayServer');
        $sagepay->setService($service);
        $sagepay->setPurchase($purchase);
        $sagepay->setFare($fare);

        // anything submitted?
        if ($data = $this->getRequest()) {

            // Anything other than 'next' jumps back
            if (empty($data['next'])) {
                $this->redirect('booking/single/' . $serviceid, true);
            }

            // If we get here we can process SagePay stuff
            // Register payment with Sagepay
            $sr = $sagepay->register();

            // If false is returned then it went wrong
            if ($sr === false) {
                $this->View('booking/fail', array(
                    'status' => 'N/A',
                    'diagnostic' => $sagepay->getError(),
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

        // Full strength error logging
        error_reporting(E_ALL);
        ini_set('display_errors', 0);
        ini_set('log_errors', 1);

        // Library stuff
        $sagepay = $this->getLibrary('SagepayServer');

        // POST data from SagePay
        $data = $sagepay->getNotification();

        // Log the notification data to debug file (in case it's interesting)
        $this->log(var_export($data, true));

        // Get the VendorTxCode and use it to look up the purchase
        $VendorTxCode = $data['VendorTxCode'];
        if (!$purchase = Booking::getPurchaseFromVendorTxCode($VendorTxCode)) {
            $url = $this->Url('booking/fail') . '/' . $VendorTxCode . '/' . urlencode('Purchase record not found');
            $this->log('SagePay notification: Purchase not found - ' . $url);
            $sagepay->notificationreceipt('INVALID', $url, 'Purchase record not found');
            die;
        }

        // Now that we have the purchase object, we can save whatever we got back in it
        $purchase = Booking::updatePurchase($purchase, $data);

        // Mailer
        $mail = $this->getLibrary('Mail');
        $mailpurchase = clone $purchase;
        $mail->initialise($mailpurchase);
        $mail->setExtrarecipients(explode(',', $_ENV['backup_email']));

        // Check VPSSignature for validity
        if (!$sagepay->checkVPSSignature($purchase, $data)) {
            $purchase->status = 'VPSFAIL';
            $purchase->save();
            $url = $this->Url('booking/fail') . '/' . $VendorTxCode . '/' . urlencode('VPSSignature not matched');
            $this->log('SagePay notification: VPS sig no match - ' . $url);
            $sagepay->notificationreceipt('INVALID', $url, 'VPSSignature not matched');
            die;
        }

        // Check Status.
        // Work out what next action should be
        $status = $purchase->status;
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
        if (!$purchase = Booking::getPurchaseFromVendorTxCode($VendorTxCode)) {
            $this->View('booking/fail', array(
                'status' => 'N/A',
                'diagnostic' => 'Purchase record could not be found for ' . $VendorTxCode . ' Plus ' . $message,
                'servicename' => '',
            ));
        } else {
            $service = Admin::getService($purchase->serviceid);
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
        if (!$purchase = Booking::getPurchaseFromVendorTxCode($VendorTxCode)) {
            $this->View('booking/fail', array(
                'status' => 'N/A',
                'diagnostic' => 'Purchase record could not be found for ' . $VendorTxCode,
                'servicename' => '',
            ));
        } else {
            $path = $purchase->bookedby ? 'booking/telephonecomplete' : 'booking/complete';
            $service = Admin::getService($purchase->serviceid);
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
        if (!$purchase = Booking::getPurchaseFromVendorTxCode($VendorTxCode)) {
            $this->View('booking/fail', array(
                'status' => 'N/A',
                'diagnostic' => 'Purchase record could not be found for ' . $VendorTxCode,
            ));
        } else {
            $service = Admin::getService($purchase->serviceid);
            $this->View('booking/decline', array(
                'purchase' => $purchase,
                'service' => $service,
            ));
        }
    }

    /**
     * "Singlepage" take on booking
     * @param int $serviceid
     * @param string $submit == 'submit' on page submission
     */
    public function singleAction($serviceid, $submit = '') {
        $service = Admin::getService($serviceid);
        $purchase = Booking::getSessionPurchase($this, $serviceid);
        $numbers = Booking::countStuff($serviceid, $purchase);

        // get the destinations
        $stations = Booking::getDestinationStations($serviceid);

        // If there is only one then there is nothing to do
        if (count($stations)==1) {
            $purchase->destination = key($stations);
            $purchase->save();
            $destinations = [];
            $isdestinations = false;   
        } else {
            $destinations = Admin::getDestinations($serviceid);
            $isdestinations = true;
        }

        // get the joining stations
        $joining = Booking::getJoiningStations($serviceid);

        // If there is only one then there is nothing to do
        if (count($joining)==1) {
            reset($joining);
            $purchase->joining = key($joining);
            $purchase->save();
            $joinings = [];
            $isjoinings = false;   
        } else {
            $joinings = Admin::getJoinings($serviceid);
            $isjoinings = true;
        }
        
        // Starting step
        $step = 1;

        // we can't show meals unless we already know some stuff
        $displaymeals = $purchase->class && $purchase->joining && $purchase->destination;

        // ...in which case we can create the form data
        if ($displaymeals) {
            $mealsform = Booking::mealsForm($service, $purchase);
        } else {
            $mealsform = [];
        }

        // has the page been submitted
        if ($submit == 'submit') {
            $this->redirect('booking/additional');
        }

        $this->View('booking/single', [
            'service' => $service,
            'purchase' => $purchase,
            'isdestinations' => $isdestinations,
            'destinations' => $destinations,
            'isjoinings' => $isjoinings,
            'joinings' => $joinings,
            'displaymeals' => $displaymeals,
            'ismeals' => Booking::mealsAvailable($service, $purchase),
            'meals' => $mealsform,
        ]);
    }

}

