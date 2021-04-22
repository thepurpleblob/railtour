<?php

namespace thepurpleblob\railtour\controller;

use thepurpleblob\core\coreController;

class reportController extends coreController {

    protected $adminlib;

    /**
     * Constructor
     */
    public function __construct($exception = false) {
        parent::__construct($exception);

        // Library
        $this->adminlib = $this->getLibrary('Admin');
    }

    /**
     * List all purchases
     * @param $serviceid
     * @param string $sort
     */
    public function listAction($serviceid, $sort = '') {
        $this->require_login('ROLE_ORGANISER', 'report/list/' . $serviceid);

        $service = $this->adminlib->getService($serviceid);;

        // Clear session and delete expired purchases
        if (Admin::cleanPurchases()) {
            $this->View('booking/timeout');
        }

        // get the purchases for this service
        $purchases = $this->adminlib->getPurchases($serviceid, true, true);

        $this->View('report/list', array(
            'service' => $service,
            'purchases' => $this->adminlib->formatPurchases($purchases),
        ));
    }

    public function viewAction($purchaseid) {

        $this->require_login('ROLE_ORGANISER', 'report/view/' . $purchaseid);

        // Get the purchase record
        $purchase = $this->adminlib->getPurchase($purchaseid);

        // ...and the service record
        $service = $this->adminlib->getService($purchase->serviceid);

        $this->View('report/view', array(
            'service' => $service,
            'purchase' => $this->adminlib->formatPurchase($purchase),
        ));
    }
    
    /**
     * The service id can also be the code
     */
    public function exportAction($serviceid) {

        $this->require_login('ROLE_ORGANISER', 'report/export/' . $serviceid);
        
        // Get the service object
        $service = $this->adminlib->getService($serviceid);
        
        // Get the purchases
        $purchases = $this->adminlib->getPurchases($serviceid, true);
        
        // if there are none, then nothing to do
        if (!$purchases) {
            $this->redirect('service/index/' . $serviceid);
        }
        
        // Create a filename
        $filename = "rt-bkg-".$service->code . '.csv';
        
        // download
        header('Content-Type: application/octet-stream');
        header("Content-Transfer-Encoding: Binary");
        header("Content-disposition: attachment; filename=\"$filename\"");
        echo $this->adminlib->getExport($purchases);
        die;
    }

    /**
     * Re-send confirm/error/decline email to customer
     * @param int $purchaseid
     */
    public function resendAction($purchaseid) {

        $this->require_login('ROLE_ORGANISER', 'report/resend/' . $purchaseid);

        // Get the purchase
        $purchase = $this->adminlib->getPurchase($purchaseid);

        // ...and the service record
        $service = $this->adminlib->getService($purchase->serviceid);

        // Set up mailer
        $mail = $this->getLibrary('Mail');
        $mail->initialise($purchase);

        // Double check that there's an email to send to
        if ($purchase->email && $purchase->status) {
            if (($purchase->status == 'OK') || ($purchase->status == 'OK REPEATED')) {
                $mail->confirm();
                $resend = "Confirmation email re-sent";
            } else {
                $mail->error();
                $resend = "Error/Declined email re-sent";
            }
        } else {
            $resend = '';
        }

        $this->View('report/view', array(
            'service' => $service,
            'purchase' => $this->adminlib->formatPurchase($purchase),
            'resend' => $resend,
        ));
    }
}

