<?php

$sapi_type = php_sapi_name();
if (substr($sapi_type, 0, 3) != 'cli') {
    die('Can only run under CLI');
}

// Array dumped to debug here
$data = array (
  'VPSProtocol' => '3.00',
  'TxType' => 'PAYMENT',
  'VendorTxCode' => 'OL24096',
  'VPSTXId' => '',
  'Status' => 'OK',
  'StatusDetail' => '0000 : The Authorisation was Successful.',
  'TxAuthNo' => '1904452010',
  'AVSCV2' => 'ALL MATCH',
  'AddressResult' => 'MATCHED',
  'PostCodeResult' => 'MATCHED',
  'CV2Result' => 'MATCHED',
  'GiftAid' => '0',
  '3DSecureStatus' => 'NOTCHECKED',
  'CardType' => 'MC',
  'Last4Digits' => '9956',
  'VPSSignature' => '3CB3F25684CBDE57E216F5EA70711A6C',
  'DeclineCode' => '00',
  'ExpiryDate' => '0919',
  'BankAuthCode' => 'T85868',
);

$url = 'http://railtourbooking.srps.org.uk/index.php/booking/notification';

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_POST, count($data));
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));

$output = curl_exec($ch);

echo $output;
