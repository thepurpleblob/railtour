<?php

$sapi_type = php_sapi_name();
if (substr($sapi_type, 0, 3) != 'cli') {
    die('Can only run under CLI');
}

// Array dumped to debug here
$data = array (
  'VPSProtocol' => '3.00',
  'TxType' => 'PAYMENT',
  'VendorTxCode' => 'OL24169',
  'VPSTXId' => '',
  'Status' => 'OK',
  'StatusDetail' => '0000 : The Authorisation was Successful.',
  'TxAuthNo' => '1908215675',
  'AVSCV2' => 'ALL MATCH',
  'AddressResult' => 'MATCHED',
  'PostCodeResult' => 'MATCHED',
  'CV2Result' => 'MATCHED',
  'GiftAid' => '0',
  '3DSecureStatus' => 'NOTCHECKED',
  'CardType' => 'MC',
  'Last4Digits' => '3759',
  'VPSSignature' => 'E49B48329A796CF0462CCFE9660C869F',
  'DeclineCode' => '00',
  'ExpiryDate' => '0521',
  'BankAuthCode' => 'T41054',
);


$url = 'http://railtourbooking.srps.org.uk/index.php/booking/notification';

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_POST, count($data));
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));

$output = curl_exec($ch);

echo $output;
