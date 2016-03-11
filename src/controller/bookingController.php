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
        if ($crs) {
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

    public function additionalAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $booking = $this->get('srps_booking');

        // Grab current purchase
        $purchase = $booking->getPurchase();

        // Get the service object
        $code = $purchase->getCode();
        $service = $em->getRepository('SRPSBookingBundle:Service')
            ->findOneByCode($code);
        if (!$service) {
            throw $this->createNotFoundException('Unable to find code ' . $code);
        }

        // current counts
        $numbers = $booking->countStuff($service->getId(), $purchase);

        // Get the passenger count
        $passengercount = $purchase->getAdults() + $purchase->getChildren();

        // This page will only be shown if we are going to ask about firstsingle
        // option, or ask for comments.
        $iscomments = $service->isCommentbox();
        $issupplement = ($numbers->getRemainingfirstsingles() >= $passengercount)
                && ($purchase->getClass()=='F')
                && (($passengercount==1) || ($passengercount==2))
                ;
        if (!($iscomments or $issupplement)) {
                return $this->redirect($this->generateUrl('booking_personal'));
        }

        // create form
        $classtype = new AdditionalType($iscomments, $issupplement);
        $form   = $this->createForm($classtype, $purchase);

        // submitted?
        $form->handleRequest($request);
        if ($form->isValid()) {

            // 'save' the purchase
            $em->persist($purchase);
            $em->flush();

            return $this->redirect($this->generateUrl('booking_personal'));
        }

        // display form
        return $this->render('SRPSBookingBundle:Booking:additional.html.twig', array(
            'purchase' => $purchase,
            'code' => $code,
            'service' => $service,
            'iscomments' => $iscomments,
            'issupplement' => $issupplement,
            'form'   => $form->createView(),
        ));
    }

    public function personalAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $booking = $this->get('srps_booking');

        // Grab current purchase
        $purchase = $booking->getPurchase();

        // Get the service object
        $code = $purchase->getCode();
        $service = $em->getRepository('SRPSBookingBundle:Service')
            ->findOneByCode($code);
        if (!$service) {
            throw $this->createNotFoundException('Unable to find code ' . $code);
        }

        // create form
        $personaltype = new PersonalType();
        $form   = $this->createForm($personaltype, $purchase);

        // submitted?
        $form->handleRequest($request);
        if ($form->isValid()) {

            // need to check fields the hard way
            $error = false;
            if (!$purchase->getSurname()) {
                $form->get('surname')->addError(new FormError('Surname is required'));
                $error = true;
            }
            if (!$purchase->getFirstname()) {
                $form->get('firstname')->addError(new FormError('First name is required'));
                $error = true;
            }
            if (!$purchase->getAddress1()) {
                $form->get('address1')->addError(new FormError('Address line 1 is required'));
                $error = true;
            }
            if (!$purchase->getCity()) {
                $form->get('city')->addError(new FormError('Post town/city is required'));
                $error = true;
            }
            if (!$purchase->getPostcode()) {
                $form->get('postcode')->addError(new FormError('Postcode is required'));
                $error = true;
            }
            if (!$purchase->getEmail()) {
                $form->get('email')->addError(new FormError('Email is required'));
                $error = true;
            }

            // Now need to 'normalise' some of the fields
            $purchase->setTitle(ucwords($purchase->getTitle()));
            $purchase->setSurname(ucwords($purchase->getSurname()));
            $purchase->setFirstname(ucwords($purchase->getFirstname()));
            $purchase->setAddress1(ucwords($purchase->getAddress1()));
            $purchase->setAddress2(ucwords($purchase->getAddress2()));
            $purchase->setCity(ucwords($purchase->getCity()));
            $purchase->setCounty(ucwords($purchase->getCounty()));
            $purchase->setPostcode(strtoupper($purchase->getPostcode()));
            $purchase->setEmail(strtolower($purchase->getEmail()));

            if (!$error) {
                $em->persist($purchase);
                $em->flush();

                return $this->redirect($this->generateUrl('booking_review'));
            }
        }

        // display form
        return $this->render('SRPSBookingBundle:Booking:personal.html.twig', array(
            'purchase' => $purchase,
            'code' => $code,
            'service' => $service,
            'form'   => $form->createView(),
        ));
    }

   public function reviewAction()
    {
        $em = $this->getDoctrine()->getManager();
        $booking = $this->get('srps_booking');
        $sagepay = $this->get('srps_sagepay');

        // initialise sagepay thingy
        // (needs to access a bunch of params)
        $sagepay->setParameters($this->container);

        // Grab current purchase
        $purchase = $booking->getPurchase();

        // We have to mark done here, there isn't another chance
        // before passing control to SagePay (and it will get deleted if
        // something goes wrong
        $purchase->setCompleted(true);
        $em->persist($purchase);
        $em->flush();

        // Get the service object
        $code = $purchase->getCode();
        $service = $em->getRepository('SRPSBookingBundle:Service')
            ->findOneByCode($code);
        if (!$service) {
            throw $this->createNotFoundException('Unable to find code ' . $code);
        }

        // work out final fare
        $fare = $booking->calculateFare($service, $purchase, $purchase->getClass());
        $purchase->setPayment($fare->total);
        $em->persist($purchase);
        $em->flush();

        // get the destination
        $destination = $em->getRepository('SRPSBookingBundle:Destination')
            ->findOneBy(array('serviceid'=>$service->getId(), 'crs'=>$purchase->getDestination()));

        // get the joining station
        $joining = $em->getRepository('SRPSBookingBundle:Joining')
            ->findOneBy(array('serviceid'=>$service->getId(), 'crs'=>$purchase->getJoining()));

        // get stuff for sagepay (must be absolute)
        $callbackurl = $this->generateUrl('booking_callback', array(), true);
        $sage = $sagepay->getSage($service, $purchase, $destination, $joining, $callbackurl, $fare);

        // display form
        return $this->render('SRPSBookingBundle:Booking:review.html.twig', array(
            'purchase' => $purchase,
            'code' => $code,
            'service' => $service,
            'destination' => $destination,
            'joining' => $joining,
            'sage' => $sage,
            'fare' => $fare,
        ));
    }

    public function callbackAction()
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

