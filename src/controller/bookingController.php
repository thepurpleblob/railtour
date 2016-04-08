<?php

namespace thepurpleblob\railtour\controller;

use thepurpleblob\core\coreController;

class BookingController extends coreController
{
    /**
     * Opening page for booking.
     * @param $code unique (hopefully) tour code
     */
    public function indexAction($code)
    {
        // Basics
        $booking = $this->getLibrary('Booking');

        // Log
        $this->log('Booking started ' . $code);

        // Clear session and delete expired purchases
        $booking->cleanPurchases();

        // Get the service object
        $service = $booking->serviceFromCode($code);
        $serviceid = $service->id;

        // count the seats left
        $count = $booking->countStuff($serviceid);

        // Get the limits for this service
        $limits = $booking->getLimits($serviceid);

        // get acting maxparty (best estimate to display to punter)
        $maxparty = $booking->getMaxparty($limits);

        if ($booking->canProceedWithBooking($service, $count)) {
            $this->View('booking/index.html.twig', array(
                'code' => $code,
                'maxparty' => $maxparty,
                'service' => $service
            ));
        } else {
             $this->View('booking/closed.html.twig', array(
                'code' => $code,
                'service' => $service
            ));
        }
    }

    /**
     * First 'proper' booking page.
     * Ask for numbers of travellers. Also sets up purchase record
     * and session data.
     * @param $serviceid
     */
    public function numbersAction($serviceid)
    {
        // Basics
        $booking = $this->getLibrary('Booking');
        $service = $booking->Service($serviceid);

        // Get the limits for this service:
        $limits = $booking->getLimits($serviceid);

        // Grab current purchase
        $purchase = $booking->getPurchase($serviceid);

        // get acting maxparty
        $maxparty = $booking->getMaxparty($limits);

        // Choices
        $choices_adult = $booking->choices($maxparty, false);
        $choices_children = $booking->choices($maxparty, true);

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

        // display form
        $this->View('booking/numbers.html.twig', array(
            'purchase' => $purchase,
            'service' => $service,
            'limits' => $limits,
            'maxparty' => $maxparty,
            'choices_adult' => $choices_adult,
            'choices_children' => $choices_children,
            'errors' => $errors,
        ));
    }

    /**
     * Joining station
     */
    public function joiningAction()
    {
        // Basics
        $booking = $this->getLibrary('Booking');
        $purchase = $booking->getPurchase();
        $serviceid = $purchase->serviceid;
        $service = $booking->Service($serviceid);

        // get the joining stations
        $stations = $booking->getJoiningStations($serviceid);

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

        // display form
        $this->View('booking/joining.html.twig', array(
            'purchase' => $purchase,
            'stations' => $stations,
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
        $booking = $this->getLibrary('Booking');
        $purchase = $booking->getPurchase();
        $serviceid = $purchase->serviceid;
        $service = $booking->Service($serviceid);

        // get the destinations
        $stations = $booking->getDestinationStations($serviceid);

        // If there is only one then there is nothing to do
        if (count($stations)==1) {
            reset($stations);
            $purchase->destination = key($stations);
            $purchase->save();

            $this->redirect('booking/meals');
        }

        // Get destinations with extra pricing information
        $destinations = $booking->getDestinationsExtra($purchase, $service);

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
        $this->View('booking/destination.html.twig', array(
            'purchase' => $purchase,
            'destinations' => $destinations,
            'service' => $service,
        ));
    }

    /**
     * @throws \Exception
     */
   public function mealsAction()
    {
        // Basics
        $booking = $this->getLibrary('Booking');
        $purchase = $booking->getPurchase();
        $serviceid = $purchase->serviceid;
        $service = $booking->Service($serviceid);

        // If there are no meals on this service just bail
        if (!$booking->mealsAvailable($service)) {
            $this->redirect('booking/class');
        }

        // Array of meal options for forms
        $meals = $booking->mealsForm($service, $purchase);

        // Create validation
        $gumprules = array();
        $fieldnames = array();
        $jqvrules = array();
        foreach ($meals as $meal) {
            $gumprules[$meal->formname] = 'required|integer|min_numeric,0|max_numeric,' . $meal->maxmeals;
            $fieldnames[$meal->formname] = $meal->name;
            $jqvrules[] = $meal->formname . '{required: true, range: [0, ' . $meal->maxmeals . ']}';
        }
        $jqv = implode(',', $jqvrules);

        // hopefully no errors
        $errors = null;

        // anything submitted?
        if ($data = $this->getRequest()) {

            // Cancel?
            if (!empty($data['back'])) {

                // We need to know if Destinations would have been displayed
                $stations = $booking->getDestinationStations($serviceid);
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
        $this->View('booking/meals.html.twig', array(
            'purchase' => $purchase,
            'service' => $service,
            'meals' => $meals,
            'jqv' => $jqv,
            'errors' => $errors,
        ));
    }

   public function classAction($class = '')
    {
        // Basics
        $booking = $this->getLibrary('Booking');
        $purchase = $booking->getPurchase();
        $serviceid = $purchase->serviceid;
        $service = $booking->Service($serviceid);

        // Get the limits for this service:
        $limits = $booking->getLimits($serviceid);

        // get acting maxparty
        $maxpartystandard = $booking->getMaxparty($limits);

        // get first and standard maximum parties
        $maxpartyfirst = $limits->maxpartyfirst ? $limits->maxpartyfirst : $maxpartystandard;

        // Get the passenger count
        $passengercount = $purchase->adults + $purchase->children;

        // get first and standard fares
        $farestandard = $booking->calculateFare($service, $purchase, 'S');
        $farefirst = $booking->calculateFare($service, $purchase, 'F');

        // we need to know about the number
        // it's a bodge - but if the choice is made then skip this check
        $numbers = $booking->countStuff($serviceid, $purchase);
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
        $this->View('booking/class.html.twig', array(
            'purchase' => $purchase,
            'service' => $service,
            'farefirst' => $farefirst,
            'farestandard' => $farestandard,
            'availablefirst' => $availablefirst,
            'availablestandard' => $availablestandard,
        ));
    }

    public function additionalAction() {

        // Basics
        $booking = $this->getLibrary('Booking');
        $purchase = $booking->getPurchase();
        $serviceid = $purchase->serviceid;
        $service = $booking->Service($serviceid);

        // current counts
        $numbers = $booking->countStuff($serviceid, $purchase);

        // Get the passenger count
        $passengercount = $purchase->adults + $purchase->children;

        // This page will only be shown if we are going to ask about firstsingle
        // option, or ask for comments.
        $iscomments = $service->commentbox;
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

            if (!empty($data['comment'])) {
                $purchase->comment = $data['comment'];
            }
            if (!empty($data['seatsupplement'])) {
                $purchase->seatsupplement = $data['seatsupplement'];
            }
            $purchase->save();

            $this->redirect('booking/personal');
        }

        // display form
        $this->View('booking/additional.html.twig', array(
            'purchase' => $purchase,
            'service' => $service,
            'iscomments' => $iscomments,
            'issupplement' => $issupplement,
        ));
    }

    public function personalAction()
    {
        // Basics
        $booking = $this->getLibrary('Booking');
        $purchase = $booking->getPurchase();
        $serviceid = $purchase->serviceid;
        $service = $booking->Service($serviceid);

        // hopefully no errors
        $errors = null;

        // anything submitted?
        if ($data = $this->getRequest()) {

            // Cancel?
            if (!empty($data['back'])) {
                $this->redirect('booking/additional', true);
            }

            // Validate
            $this->gump->validation_rules(array(
                'surname' => 'required',
                'firstname' => 'required',
                'address1' => 'required',
                'city' => 'required',
                'postcode' => 'required',
                'email' => 'required',
            ));
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

                $purchase->save();
                $this->redirect('booking/review');
            }
        }

        // display form
        $this->View('booking/personal.html.twig', array(
            'purchase' => $purchase,
            'service' => $service,
        ));
    }

   public function reviewAction() {

        // Basics
        $booking = $this->getLibrary('Booking');
        $purchase = $booking->getPurchase();
        $serviceid = $purchase->serviceid;
        $service = $booking->Service($serviceid);

        // work out final fare
        $fare = $booking->calculateFare($service, $purchase, $purchase->class);
        $purchase->payment = $fare->total;
        $purchase->save();

        // get the destination
        $destination = $booking->getDestination($serviceid, $purchase->destination);

        // get the joining station
        $joining = $booking->getJoining($serviceid, $purchase->joining);

        // display form
        $this->View('booking/review.html.twig', array(
            'purchase' => $purchase,
            'service' => $service,
            'destination' => $destination,
            'joining' => $joining,
            'fare' => $fare,
        ));
    }

    /**
     * This is a bit different - we get here from the
     * review page, only if the form is submitted.
     * This action sends the payment registration to SagePay
     */
    public function paymentAction() {

        // Basics
        $booking = $this->getLibrary('Booking');
        $purchase = $booking->getPurchase();
        $serviceid = $purchase->serviceid;
        $service = $booking->Service($serviceid);

        // work out final fare
        $fare = $booking->calculateFare($service, $purchase, $purchase->class);

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
                $this->View('booking/fail.html.twig', array(
                    'status' => 'N/A',
                    'diagnostic' => $sagepay->error,
                ));
            }

            // check status of registration from SagePay
            $status = $sr['Status'];
            if (($status != 'OK') && ($status != 'OK REPEATED')) {
                $this->View('booking/fail.html.twig', array(
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

        // Library stuff
        $booking = $this->getLibrary('Booking');
        $sagepay = $this->getLibrary('SagepayServer');

        // Post data
        $data = $this->getRequest();

        $this->log(var_export($data, true));

        // Get the VendorTxCode and use it to look up the purchase
        $VendorTxCode = $data['VendorTxCode'];

        // The URL for SagePay to redirect to
        $url = $this->Url('booking/complete') . '/' . $VendorTxCode;

        if (!$purchase = \ORM::forTable('purchase')->where('bookingref', $VendorTxCode)->findOne()) {
            $sagepay->notificationreceipt('INVALID', $url . '/notfound', 'Purchase record not found');
        }

        // Check VPSSignature for validity
        die;
    }

    public function oldnotificationAction()
    {
        $em = $this->getDoctrine()->getManager();
        $sagepay = $this->get('srps_sagepay');

        // initialise sagepay thingy
        // (needs to access a bunch of params)
        $sagepay->setParameters($this->container);

        // get the SagePay response object
        if (empty($_REQUEST['crypt'])) {
            // it's gone wrong... what do we do?
            throw new \Exception( 'No or empty crypt field on callback from SagePay');
        }
        $crypt = $_REQUEST['crypt'];

        // unscramble the data
        $sage = $sagepay->decrypt( $crypt );

        // get the important data
        $bookingref = $sage->VendorTxCode;

        // get the purchase
        $purchase = $em->getRepository('SRPSBookingBundle:Purchase')
            ->findOneBy(array('bookingref'=>$bookingref));

        if (!$purchase) {
            throw new \Exception( 'Unable to find record of booking '.$bookingref);
        }

        // Get the service object
        $code = $purchase->getCode();
        $service = $em->getRepository('SRPSBookingBundle:Service')
            ->findOneByCode($code);
        if (!$service) {
            throw $this->createNotFoundException('Unable to find code ' . $code);
        }

        // get the destination
        $destination = $em->getRepository('SRPSBookingBundle:Destination')
            ->findOneBy(array('crs'=>$purchase->getDestination(), 'serviceid'=>$service->getId()));

        // get the joining station
         $joining = $em->getRepository('SRPSBookingBundle:Joining')
            ->findOneBy(array('crs'=>$purchase->getJoining(), 'serviceid'=>$service->getId()));

        // Regardless, record the bookingdata
        $purchase->setStatus($sage->Status);
        $purchase->setStatusdetail($sage->StatusDetail);
        $purchase->setCardtype($sage->CardType);
        $purchase->setLast4digits($sage->Last4Digits);
        $purchase->setBankauthcode($sage->BankAuthCode);
        $purchase->setDeclinecode($sage->DeclineCode);
        $purchase->setCompleted(true);
        $em->persist($purchase);
        $em->flush();

        // send emails IF not already (guard against refresh)
        if (!$purchase->isEmailsent()) {
            $message = \Swift_Message::newInstance();
            $message->setFrom('noreply@srps.org.uk');
            $message->setTo($purchase->getEmail());
            $message->setContentType('text/html');

            if ($sage->Status=='OK') {
                $message->setSubject('Confirmation of SRPS Railtour Booking - ' . $service->getName())
                    ->setBody(
                        $this->renderView(
                            'SRPSBookingBundle:Email:confirm.html.twig',
                            array(
                                'purchase' => $purchase,
                                'service' => $service,
                                'joining' => $joining,
                                'destination' => $destination,
                                ),
                            'text/html'
                        )
                    )
                    ->addPart(
                        $this->renderView(
                            'SRPSBookingBundle:Email:confirm.txt.twig',
                            array(
                                'purchase' => $purchase,
                                'service' => $service,
                                'joining' => $joining,
                                'destination' => $destination,
                                ),
                            'text/plain'
                        )
                    );
            } else {

                // Status != OK, so the payment failed
                 $message->setSubject('Failure Notice: SRPS Railtour Booking - ' . $service->getName())
                    ->setBody(
                        $this->renderView(
                            'SRPSBookingBundle:Email:fail.html.twig',
                            array(
                                'purchase' => $purchase,
                                'service' => $service,
                                'joining' => $joining,
                                'destination' => $destination,
                                ),
                            'text/html'
                        )
                    )
                    ->addPart(
                        $this->renderView(
                            'SRPSBookingBundle:Email:fail.txt.twig',
                            array(
                                'purchase' => $purchase,
                                'service' => $service,
                                'joining' => $joining,
                                'destination' => $destination,
                                ),
                            'text/plain'
                        )
                    );
            }
            $this->get('mailer')->send($message);

            // also send to backup (if defined)
            if ($this->container->hasParameter('srpsbackupemail')) {

                // where we send the backup email
                $srpsbackupemail = $this->container->getParameter('srpsbackupemail');

                $message->setTo($srpsbackupemail);
                $message->setSubject($service->getCode().'-'.$purchase->getBookingref());
                $this->get('mailer')->send($message);
            }
        }

        // email must have been sent
        $purchase->setEmailsent(true);
        $em->persist($purchase);
        $em->flush();

        // display form
        if ($sage->Status == 'OK') {
            return $this->render('SRPSBookingBundle:Booking:callback.html.twig', array(
                'purchase' => $purchase,
                'service' => $service,
                'sage' => $sage,
            ));
        } else {
            return $this->render('SRPSBookingBundle:Booking:decline.html.twig', array(
                'purchase' => $purchase,
                'service' => $service,
                'sage' => $sage,
            ));
        }
    }
}

