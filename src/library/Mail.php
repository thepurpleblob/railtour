<?php

namespace thepurpleblob\railtour\library;

use Exception;
use thepurpleblob\railtour\library\Admin;
use thepurpleblob\railtour\library\Booking;

/**
 * Class Mail
 * @package thepurpleblob\railtour\library
 */
class Mail {

	protected $mailer;

    protected $purchase;

    protected $service;

    protected $joining;

    protected $destination;

    protected $extrarecipients;

    /**
     * Initialise email service
     */
    public function initialise($purchase, $service = null) {
        global $CFG;

        // Set the purchase and service
        $this->purchase = $purchase;
        if ($service) {
            $this->service = $service;
        } else {
            if (!$this->service = \ORM::forTable('service')->findOne($purchase->serviceid)) {
                throw new Exception("Service not found in mailer id=" . $purchase->serviceid);
            }
        }

        // format service
        Admin::formatService($this->service);

        // format purchase
        $this->purchase->formattedclass = $purchase->class == 'F' ? 'First' : 'Standard';

        // Find joining and destination
        $this->joining = Booking::getJoiningCRS($this->service->id, $this->purchase->joining);
        $this->destination = Booking::getDestinationCRS($this->service->id, $this->purchase->destination);

        // Create transport
        $transport = new \Swift_SmtpTransport($CFG->smtpd_host);

        // Create mailer
        $this->mailer = new \Swift_Mailer($transport);

        // In case there are non
        $this->extrarecipients = array();
    }

    /**
     * Get list of people to send email to
     * @return array
     */
    private function getRecipients() {
        $recipients = $this->extrarecipients;
        if ($this->purchase->email) {
            $recipients[] = $this->purchase->email;
        }

        return $recipients; 
    }

    /**
     * Add extra recipients to send extra copies of mail
     * @param array $recipients
     */
    public function setExtrarecipients($recipients) {
        $this->extrarecipients = $recipients;
    }

    /**
     * Send notification of completion
     */
    public function confirm() {
        
        // Get messages
        $body = $this->controller->renderView('email/confirm', array(
            'service' => $this->service,
            'purchase' => $this->purchase,
            'joining' => $this->joining,
            'destination' => $this->destination,
        ));
        $bodytxt = $this->controller->renderView('email/confirm_txt', array(
            'service' => $this->service,
            'purchase' => $this->purchase,
            'joining' => $this->joining,
            'destination' => $this->destination,
        ));

        foreach ($this->getRecipients() as $recipient) {
            $message = (new \Swift_Message())
            ->setSubject('SRPS Railtours - Confirmation (' . $this->service->code . ' ' . $this->purchase->bookingref . ')')
            ->setFrom('noreply@srps.org.uk')
            ->setTo($recipient)
            ->setBody($bodytxt)
            ->addPart($body, 'text/html');

            $this->mailer->send($message);
            $this->controller->log('Sending confirm email to ' . $recipient . ' ' . $this->purchase->bookingref );
        }
    }

    /**
     * Send notification for decline/fail
     */
    public function decline() {

        // Get messages
        $body = $this->controller->renderView('email/fail', array(
            'service' => $this->service,
            'purchase' => $this->purchase,
        ));
        $bodytxt = $this->controller->renderView('email/fail_txt', array(
            'service' => $this->service,
            'purchase' => $this->purchase,
        ));

        foreach ($this->getRecipients() as $recipient) {
            $message = (new \Swift_Message())
            ->setSubject('SRPS Railtours - Payment Declined (' . $this->service->code . ' ' . $this->purchase->bookingref . ')')
            ->setFrom('noreply@srps.org.uk')
            ->setTo($recipient)
            ->setBody($bodytxt)
            ->addPart($body, 'text/html');

            $this->mailer->send($message);
            $this->controller->log('Sending decline email to ' . $recipient . ' ' . $this->purchase->bookingref );
        }
        
    }
}
