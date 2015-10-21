<?php

// Defines filter types used for a parameter in the cleanInput() function.
Define("CLEAN_INPUT_FILTER_ALPHABETIC", "alpha");
Define("CLEAN_INPUT_FILTER_ALPHABETIC_AND_ACCENTED", "alpha and accented");
Define("CLEAN_INPUT_FILTER_ALPHANUMERIC", "alphaNumeric");
Define("CLEAN_INPUT_FILTER_ALPHANUMERIC_AND_ACCENTED", "alphaNumeric and accented");
Define("CLEAN_INPUT_FILTER_NUMERIC", "numeric");
Define("CLEAN_INPUT_FILTER_TEXT", "text");
Define("CLEAN_INPUT_FILTER_WIDEST_ALLOWABLE_CHARACTER_RANGE", "text");

class sagelib {

/* The getToken function.                                                                                         **
** NOTE: A function of convenience that extracts the value from the "name=value&name2=value2..." reply string **
** Works even if one of the values is a URL containing the & or = signs.                                      	  */
    static function getToken($thisString) {
    
      // List the possible tokens
      $Tokens = array(
        "Status",
        "StatusDetail",
        "VendorTxCode",
        "VPSTxId",
        "TxAuthNo",
        "Amount",
        "AVSCV2", 
        "AddressResult", 
        "PostCodeResult", 
        "CV2Result", 
        "GiftAid", 
        "3DSecureStatus", 
        "CAVV",
    	"AddressStatus",
    	"CardType",
    	"Last4Digits",
    	"PayerStatus");
    
      // Initialise arrays
      $output = array();
      $resultArray = array();
      
      // Get the next token in the sequence
      for ($i = count($Tokens)-1; $i >= 0 ; $i--){
        $resultArray[$i] = new stdClass();
        // Find the position in the string
        $start = strpos($thisString, $Tokens[$i]);
    	// If it's present
        if ($start !== false){
          // Record position and token name
          $resultArray[$i]->start = $start;
          $resultArray[$i]->token = $Tokens[$i];
        }
      }
      
      // Sort in order of position
      sort($resultArray);
    	// Go through the result array, getting the token values
      for ($i = 0; $i<count($resultArray); $i++){
        // Get the start point of the value
        $valueStart = $resultArray[$i]->start + strlen($resultArray[$i]->token) + 1;
    	// Get the length of the value
        if ($i==(count($resultArray)-1)) {
          $output[$resultArray[$i]->token] = substr($thisString, $valueStart);
        } else {
          $valueLength = $resultArray[$i+1]->start - $resultArray[$i]->start - strlen($resultArray[$i]->token) - 2;
    	  $output[$resultArray[$i]->token] = substr($thisString, $valueStart, $valueLength);
        }      
    
      }
    
      // Return the ouput array
      return $output;
    }
    
    
    // Filters unwanted characters out of an input string based on type.  Useful for tidying up FORM field inputs
    //   Parameter strRawText is a value to clean.
    //   Parameter filterType is a value from one of the CLEAN_INPUT_FILTER_ constants.
    static function cleanInput($strRawText, $filterType)
    {
        $strAllowableChars = "";
        $blnAllowAccentedChars = FALSE;
        $strCleaned = "";
        $filterType = strtolower($filterType); //ensures filterType matches constant values
        
        if ($filterType == CLEAN_INPUT_FILTER_TEXT)
        { 
            $strAllowableChars = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789 .,'/\\{}@():?-_&�$=%~*+\"\n\r";
            $strCleaned = self::cleanInput2($strRawText, $strAllowableChars, TRUE);
    	}
        elseif ($filterType == CLEAN_INPUT_FILTER_NUMERIC) 
        {
            $strAllowableChars = "0123456789 .,";
            $strCleaned = self::cleanInput2($strRawText, $strAllowableChars, FALSE);
        }   
        elseif ($filterType == CLEAN_INPUT_FILTER_ALPHABETIC || $filterType == CLEAN_INPUT_FILTER_ALPHABETIC_AND_ACCENTED)
    	{
            $strAllowableChars = "ABCDEFGHIJKLMNOPQRSTUVWXYZ abcdefghijklmnopqrstuvwxyz";
            if ($filterType == CLEAN_INPUT_FILTER_ALPHABETIC_AND_ACCENTED) $blnAllowAccentedChars = TRUE;
            $strCleaned = self::cleanInput2($strRawText, $strAllowableChars, $blnAllowAccentedChars);
    	}
        elseif ($filterType == CLEAN_INPUT_FILTER_ALPHANUMERIC || $filterType == CLEAN_INPUT_FILTER_ALPHANUMERIC_AND_ACCENTED)
    	{
            $strAllowableChars = "0123456789 ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz";
            if ($filterType == CLEAN_INPUT_FILTER_ALPHANUMERIC_AND_ACCENTED) $blnAllowAccentedChars = TRUE;
            $strCleaned = self::cleanInput2($strRawText, $strAllowableChars, $blnAllowAccentedChars);
    	}
        else // Widest Allowable Character Range
        {
            $strAllowableChars = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789 .,'/\\{}@():?-_&�$=%~*+\"\n\r";
            $strCleaned = self::cleanInput2($strRawText, $strAllowableChars, TRUE);
        }
        
        return $strCleaned;
    }
    
    
    // Filters unwanted characters out of an input string based on an allowable character set.  Useful for tidying up FORM field inputs
    //   Parameter strRawText is a value to clean.
    //   Parameter "strAllowableChars" is a string of characters allowable in "strRawText" if its to be deemed valid.
    //   Parameter "blnAllowAccentedChars" accepts a boolean value which determines if "strRawText" can contain Accented or High-order characters.
    static function cleanInput2($strRawText, $strAllowableChars, $blnAllowAccentedChars)
    {
        $iCharPos = 0;
        $chrThisChar = "";
        $strCleanedText = "";
        
        //Compare each character based on list of acceptable characters
        while ($iCharPos < strlen($strRawText))
        {
            // Only include valid characters **
            $chrThisChar = substr($strRawText, $iCharPos, 1);
            if (strpos($strAllowableChars, $chrThisChar) !== FALSE)
            {
                $strCleanedText = $strCleanedText . $chrThisChar;
            }
            elseIf ($blnAllowAccentedChars == TRUE)
            {
                // Allow accented characters and most high order bit chars which are harmless **
                if (ord($chrThisChar) >= 191)
                {
                	$strCleanedText = $strCleanedText . $chrThisChar;
                }
            }
            
            $iCharPos = $iCharPos + 1;
        }
        
        return $strCleanedText;
    }
    
    /* Base 64 Encoding function **
    ** PHP does it natively but just for consistency and ease of maintenance, let's declare our own function **/
    static function base64Encode($plain) {
      // Initialise output variable
      $output = "";
      
      // Do encoding
      $output = base64_encode($plain);
      
      // Return the result
      return $output;
    }
    
    /* Base 64 decoding function **
    ** PHP does it natively but just for consistency and ease of maintenance, let's declare our own function **/
    static function base64Decode($scrambled) {
      // Initialise output variable
      $output = "";
      
      // Fix plus to space conversion issue
      $scrambled = str_replace(" ","+",$scrambled);
      
      // Do encoding
      $output = base64_decode($scrambled);
      
      // Return the result
      return $output;
    }
    
    
    /*  The SimpleXor encryption algorithm                                                                                **
    **  NOTE: This is a placeholder really.  Future releases of Form will use AES or TwoFish.  Proper encryption      **
    **  This simple function and the Base64 will deter script kiddies and prevent the "View Source" type tampering        **
    **  It won't stop a half decent hacker though, but the most they could do is change the amount field to something     **
    **  else, so provided the vendor checks the reports and compares amounts, there is no harm done.  It's still          **
    **  more secure than the other PSPs who don't both encrypting their forms at all                                      */
    
    static function simpleXor($InString, $Key) {
      // Initialise key array
      $KeyList = array();
      // Initialise out variable
      $output = "";
      
      // Convert $Key into array of ASCII values
      for($i = 0; $i < strlen($Key); $i++){
        $KeyList[$i] = ord(substr($Key, $i, 1));
      }
    
      // Step through string a character at a time
      for($i = 0; $i < strlen($InString); $i++) {
        // Get ASCII code from string, get ASCII code from key (loop through with MOD), XOR the two, get the character from the result
        // % is MOD (modulus), ^ is XOR
        $output.= chr(ord(substr($InString, $i, 1)) ^ ($KeyList[$i % strlen($Key)]));
      }
    
      // Return the result
      return $output;
    }
    
    
    //** Wrapper function do encrypt an encode based on strEncryptionType setting **
    static function encryptAndEncode($strIn, $strEncryptionPassword) {

		//** AES encryption, CBC blocking with PKCS5 padding then HEX encoding - DEFAULT **

		//** use initialization vector (IV) set from $strEncryptionPassword
    	$strIV = $strEncryptionPassword;
    	
    	//** add PKCS5 padding to the text to be encypted
    	$strIn = self::addPKCS5Padding($strIn);

    	//** perform encryption with PHP's MCRYPT module
		$strCrypt = mcrypt_encrypt(MCRYPT_RIJNDAEL_128, $strEncryptionPassword, $strIn, MCRYPT_MODE_CBC, $strIV);
		
		//** perform hex encoding and return
		return "@" . bin2hex($strCrypt);

    }
    
    
    //** Wrapper function do decode then decrypt based on header of the encrypted field **
    static function decodeAndDecrypt($strIn, $strEncryptionPassword) {
    	
    	if (substr($strIn,0,1)=="@") 
    	{
    		//** HEX decoding then AES decryption, CBC blocking with PKCS5 padding - DEFAULT **
    		
    		//** use initialization vector (IV) set from $strEncryptionPassword
        	$strIV = $strEncryptionPassword;
        	
        	//** remove the first char which is @ to flag this is AES encrypted
        	$strIn = substr($strIn,1); 
        	
        	//** HEX decoding
        	$strIn = pack('H*', $strIn);
        	
        	//** perform decryption with PHP's MCRYPT module
    		return self::removePKCS5Padding(
    			mcrypt_decrypt(MCRYPT_RIJNDAEL_128, $strEncryptionPassword, $strIn, MCRYPT_MODE_CBC, $strIV)); 
    	} 
    	else 
    	{
    		//** Base 64 decoding plus XOR decryption **
    		return self::simpleXor(base64Decode($strIn),$strEncryptionPassword);
    	}
    }
    
    // New function added 2011-12-29 
    // Need to remove padding bytes from end of decoded string
    static function removePKCS5Padding($decrypted) {
    	$padChar = ord($decrypted[strlen($decrypted) - 1]);
        return substr($decrypted, 0, -$padChar); 
    }
    
    //** PHP's mcrypt does not have built in PKCS5 Padding, so we use this
    static function addPKCS5Padding($input)
    {
       $blocksize = 16;
       $padding = "";
    
       // Pad input to an even block size boundary
       $padlength = $blocksize - (strlen($input) % $blocksize);
       for($i = 1; $i <= $padlength; $i++) {
          $padding .= chr($padlength);
       }
       
       return $input . $padding;
    }
    
}

