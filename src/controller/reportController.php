<?php

namespace thepurpleblob\railtour\controller;

use thepurpleblob\core\coreController;

class reportController extends coreController
{
    public function listAction($serviceid)
    {
        $this->require_login('ROLE_ORGANISER', 'service/show/' . $serviceid);

        $booking = $this->getLibrary('Booking');
        $service = $booking->Service($serviceid);

        // Clear session and delete expired purchases
        $booking->cleanPurchases();

        // get the purchases for this service
        $purchases = \ORM::forTable('purchase')
            ->where(array(
                'serviceid' => $serviceid,
                'completed' => 1,
            ))
            ->order_by_asc('timestamp')
            ->findMany();

        $this->View('report/list.html.twig', array(
            'service' => $service,
            'purchases' => $purchases,
        ));
    }

    public function viewAction($purchaseid)
    {
        $this->require_login('ROLE_ORGANISER');

        $booking = $this->getLibrary('Booking');

        // Get the purchase record
        $purchase = \ORM::forTable('purchase')->findOne($purchaseid);
        if (!$purchase) {
            throw new \Exception('purchase item could not be found, id = ' . $purchaseid);
        }

        // ...and the service record
        $service = $booking->Service($purchase->serviceid);

        $this->View('report/view.html.twig', array(
            'service' => $service,
            'purchase' => $purchase,
        ));
    }
    
    /**
     * The service id can also be the code
     */
    public function exportAction($serviceid) {

        $reports = $this->getLibrary('Reports');
        $booking = $this->getLibrary('Booking');
        
        // Get the service object
        $service = $booking->Service($serviceid);
        
        // Get the purchases
        $purchases = \ORM::forTable('purchase')
            ->where(array(
                'serviceid' => $serviceid,
                'completed' => 1,
                'status' => 'OK',
            ))
            ->order_by_asc('timestamp')
            ->findMany();
        
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
        echo $reports->getExport($purchases);
        die;
    }
}

