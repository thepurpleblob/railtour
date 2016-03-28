<?php

namespace thepurpleblob\railtour\library;

use Exception;

class Sagepay {

    protected $container;

    protected $sageEncryptionPassword;

    protected $sageLogin;

    protected $sageUrl;

    protected $srpsemail;

    protected $controller;

    public function __construct($controller) {
        $this->controller = $controller;
    }

    /**
     * Bodge: controller needs to send $this->container
     * so we can get stuff from Parameters.yml
     * @param type $container
     */
    public function setParameters($container) {
        $this->container = $container;
        $this->sageLogin = $container->getParameter('sagelogin');
        $this->sageEncryptionPassword = $container->getParameter('sageencryptionpassword');
        $this->sageUrl = $container->getParameter('sageurl');
        $this->srpsemail = $container->getParameter('srpsemail');
    }

    private function encryptAndEncode($strIn) {

        //** use initialization vector (IV) set from $strEncryptionPassword
        $strIV = $this->sageEncryptionPassword;

        //** add PKCS5 padding to the text to be encypted
        $strIn = $this->addPKCS5Padding($strIn);

        //** perform encryption with PHP's MCRYPT module
        $strCrypt = mcrypt_encrypt(MCRYPT_RIJNDAEL_128, $this->sageEncryptionPassword, $strIn, MCRYPT_MODE_CBC, $strIV);

        //** perform hex encoding and return
        return "@" . bin2hex($strCrypt);
    }

    private function decodeAndDecrypt($strIn) {

	$strEncryptionPassword = $this->sageEncryptionPassword;

	if (substr($strIn,0,1)=="@")
	{
		//** HEX decoding then AES decryption, CBC blocking with PKCS5 padding - DEFAULT **

		//** use initialization vector (IV) set from $strEncryptionPassword
    	$strIV = $this->sageEncryptionPassword;

    	//** remove the first char which is @ to flag this is AES encrypted
    	$strIn = substr($strIn,1);

    	//** HEX decoding
    	$strIn = pack('H*', $strIn);

    	//** perform decryption with PHP's MCRYPT module
		return $this->removePKCS5Padding(
			mcrypt_decrypt(MCRYPT_RIJNDAEL_128, $strEncryptionPassword, $strIn, MCRYPT_MODE_CBC, $strIV));
	}
	else
	{
		//** Base 64 decoding plus XOR decryption **
		return simpleXor(base64Decode($strIn),$strEncryptionPassword);
	}
    }

    private function addPKCS5Padding($input) {
       $blocksize = 16;
       $padding = "";

       // Pad input to an even block size boundary
       $padlength = $blocksize - (strlen($input) % $blocksize);
       for($i = 1; $i <= $padlength; $i++) {
          $padding .= chr($padlength);
       }

       return $input . $padding;
    }

    private function removePKCS5Padding($decrypted) {
	$padChar = ord($decrypted[strlen($decrypted) - 1]);
        return substr($decrypted, 0, -$padChar);
    }

    /**
     * The data sent to sage, some basic checks
     * @param string $data
     */
    private function clean($data, $maxlength=255) {

        // Ampersand is used as a separator for records
        $data = str_replace('&', 'and', $data);

        // Equals is used as a name=value separator
        $data = str_replace('=', 'equals', $data);

        // trim
        $data = trim($data);

        // clip to length
        $data = substr($data, 0, $maxlength);

        return $data;
    }

    private function decolon($string) {

        return str_replace(':', '=', $string);
    }

    private function buildBasket($service, $purchase, $fare) {

        // booked class
        $class = $purchase->getClass()=='F' ? 'First' : 'Standard';

        // create the main booking line
        $numberoflines = 1;
        $aname = $purchase->getAdults()==1 ? 'Adult' : 'Adults';
        $main = $this->decolon("Railtour '" . $service->getName() . "' $aname in $class Class");
        $main .= ':' . $purchase->getAdults() . ':' . $fare->adultunit . ':::' . number_format($fare->adultfare, 2);
        if ($purchase->getChildren()) {
            $numberoflines = 2;
            $cname = $purchase->getChildren()==1 ? 'Child' : 'Children';
            $main .= ':' . $this->decolon("Railtour '" . $service->getName() . "' $cname in $class Class");
            $main .= ':' . $purchase->getChildren() . ':' . $fare->childunit . ':::' . number_format($fare->childfare, 2);
        }

        // meals
        $meals = array();;
        if ($service->isMealavisible() and $purchase->getMeala()) {
            $meala = $this->decolon($service->getMealaname()) . ':';
            $meala .= $purchase->getMeala() . ':' . $service->getMealaprice() . ':::';
            $meala .= number_format($purchase->getMeala() * $service->getMealaprice(), 2);
            $meals[] = $meala;
        }
        if ($service->isMealbvisible() and $purchase->getMealb()) {
            $mealb = $this->decolon($service->getMealbname()) . ':';
            $mealb .= $purchase->getMealb() . ':' . $service->getMealbprice() . ':::';
            $mealb .= number_format($purchase->getMealb() * $service->getMealbprice(), 2);
            $meals[] = $mealb;
        }
        if ($service->isMealcvisible() and $purchase->getMealc()) {
            $mealc = $this->decolon($service->getMealcname()) . ':';
            $mealc .= $purchase->getMealc() . ':' . $service->getMealcprice() . ':::';
            $mealc .= number_format($purchase->getMealc() * $service->getMealcprice(), 2);
            $meals[] = $mealc;
        }
        if ($service->isMealdvisible() and $purchase->getMeald()) {
            $meald = $this->decolon($service->getMealdname()) . ':';
            $meald .= $purchase->getMeald() . ':' . $service->getMealdprice() . ':::';
            $meald .= number_format($purchase->getMeald() * $service->getMealdprice(), 2);
            $meals[] = $meald;
        }
        
        // extra seats
        if ($fare->seatsupplement) {
            $passengers = $purchase->getAdults() + $purchase->getChildren();
            $supplement = ":Window seat supplement:$passengers:" . $service->getSinglesupplement() . ':::';
            $supplement .= number_format($fare->seatsupplement, 2);
            $numberoflines++;
        } else {
            $supplement = '';
        }

        // Put it all together
        $numberoflines += count($meals);
        $basket = $numberoflines . ':' . $main;
        if (count($meals)) {
            $basket .= ':' . implode(':', $meals);
        }
        $basket .= $supplement;
//echo "<pre>" . $basket; die;

        return $basket;
    }

    /**
     * Create the sagepay data array
     * @param object $purchase
     */
    private function buildSageData($service, $purchase, $destination, $joining, $callbackurl, $fare) {

        // Get the basket contents
        $basket = $this->buildBasket($service, $purchase, $fare);

        $data = "";
        $data .= "VendorTxCode=" . $purchase->getBookingref();
        $data .= "&Amount=" . number_format($purchase->getPayment(),2);
        $data .= "&Currency=GBP";
        $data .= "&Description=" . $this->clean("SRPS Railtour Booking - " . $service->getName(), 100);
        $data .= "&SuccessURL=$callbackurl";
        $data .= "&FailureURL=$callbackurl";
        $data .= "&CustomerName=" . $this->clean($purchase->getTitle() . ' ' .
            $purchase->getFirstname() . ' ' . $purchase->getSurname(), 100);
        $data .= "&CustomerEmail=" . $this->clean($purchase->getEmail(), 255);
        $data .= "&VendorEMail=" . $this->clean($this->srpsemail, 255);
        $data .= "&SendEmail=1";
        $data .= "&BillingSurname=" . $this->clean($purchase->getSurname(), 20);
        $data .= "&BillingFirstnames=" . $this->clean($purchase->getFirstname(), 20);
        $data .= "&BillingAddress1=" . $this->clean($purchase->getAddress1(), 100);
        $data .= "&BillingAddress2=" . $this->clean($purchase->getAddress2(), 100);
        $data .= "&BillingCity=" . $this->clean($purchase->getCity(), 40);
        $data .= "&BillingPostCode=" . $this->clean($purchase->getPostcode(), 10);
        $data .= "&BillingCountry=GB";
        $data .= "&BillingPhone=" . $this->clean($purchase->getPhone(), 20);
        $data .= "&DeliverySurname=" . $this->clean($purchase->getSurname(), 20);
        $data .= "&DeliveryFirstnames=" . $this->clean($purchase->getFirstname(), 20);
        $data .= "&DeliveryAddress1=" . $this->clean($purchase->getAddress1(), 100);
        $data .= "&DeliveryAddress2=" . $this->clean($purchase->getAddress2(), 100);
        $data .= "&DeliveryCity=" . $this->clean($purchase->getCity(), 40);
        $data .= "&DeliveryPostCode=" . $this->clean($purchase->getPostcode(), 10);
        $data .= "&DeliveryCountry=GB";
        $data .= "&DeliveryPhone=" . $this->clean($purchase->getPhone(), 20);
        $data .= "&Basket=$basket";
        $data .= "&AllowGiftAid=1";
        $data .= "&VendorData=" . $this->clean('Tour code ' . $service->getCode(), 200);

        // encrypt the data
        $encrypted = $this->encryptAndEncode($data);

        return $encrypted;
    }

    public function getSage($service, $purchase, $destination, $joining, $callbackurl, $fare) {

        $sagedata = $this->buildSageData($service, $purchase, $destination, $joining, $callbackurl, $fare);

        // Create object for returned data
        $sage = new \stdClass();

        $sage->submissionurl = $this->sageUrl;
        $sage->login = $this->sageLogin;
        $sage->crypt = $sagedata;

        return $sage;
    }

    private function response($fields, $name, $required=false) {
        if ($required and empty($fields[$name])) {
            throw new \Exception("Field '$name' not returned from SagePay");
        }
        if (empty($fields[$name])) {
            return '';
        } else {
            return $fields[$name];
        }
    }

    public function decrypt($crypt) {
        $data = $this->decodeAndDecrypt($crypt);

        // this is (again) all & and =, split & first
        $groups = explode('&', $data);

        // run through groups and split pairs
        $fields = array();
        foreach ($groups as $group) {
            $pair = explode('=', $group);
            $fields[$pair[0]] = $pair[1];
        }

        // check and turn into objects
        $sage = new \stdClass();
        $sage->Status = $this->response($fields, 'Status', true);
        $sage->StatusDetail = $this->response($fields, 'StatusDetail');
        $sage->VendorTxCode = $this->response($fields, 'VendorTxCode', true);
        $sage->VPSTxId = $this->response($fields, 'VPSTxId');
        $sage->TxAuthNo = $this->response($fields, 'TxAuthNo');
        $sage->Amount = $this->response($fields, 'Amount');
        $sage->AVSCV2 = $this->response($fields, 'AVSCV2');
        $sage->AddressResult = $this->response($fields, 'AddressResult');
        $sage->PostCodeResult = $this->response($fields, 'PostCodeResult');
        $sage->CV2Result = $this->response($fields, 'CV2Result');
        $sage->GiftAid = $this->response($fields, 'GiftAid');
        $sage->CAVV = $this->response($fields, 'CAVV');
        $sage->AddressStatus = $this->response($fields, 'AddressStatus');
        $sage->PayerStatus = $this->response($fields, 'PayerStatus');
        $sage->CardType = $this->response($fields, 'CardType');
        $sage->Last4Digits = $this->response($fields, 'Last4Digits');
        $sage->FraudResponse = $this->response($fields, 'FraudResponse');
        $sage->Surcharge = $this->response($fields, 'Surcharge');
        $sage->BankAuthCode = $this->response($fields, 'BankAuthCode');
        $sage->DeclineCode = $this->response($fields, 'DeclineCode');
        
        // cleanup status detail
        $colonpos = stripos($sage->StatusDetail, ':');
        if ($colonpos !== false) {
            $sage->StatusDetail = substr($sage->StatusDetail, $colonpos+1);
        }

        return $sage;
    }


}
