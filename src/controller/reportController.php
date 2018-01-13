<?php

namespace thepurpleblob\railtour\controller;

use thepurpleblob\core\coreController;

class reportController extends coreController {

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
     * List all purchases
     * @param $serviceid
     * @param string $sort
     */
    public function listAction($serviceid, $sort = '')
    {
        $this->require_login('ROLE_ORGANISER', 'service/show/' . $serviceid);

        $service = $this->adminlib->getService($serviceid);;

        // Clear session and delete expired purchases
        $this->adminlib->cleanPurchases();

        // get the purchases for this service
        $purchases = $this->adminlib->getPurchases($serviceid);

        $this->View('report/list', array(
            'service' => $service,
            'purchases' => $this->adminlib->formatPurchases($purchases),
        ));
    }

    public function viewAction($purchaseid) {

        $this->require_login('ROLE_ORGANISER');

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

        $this->require_login('ROLE_ORGANISER');
        
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
}

