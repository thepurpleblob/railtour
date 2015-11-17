<?php

namespace SRPS\BookingBundle\Service;

class Reports {
    
    protected $em;
    
    public function _construct($em) {
        $this->em = $em;
    }
    
    private function clean($string, $length=255) {
        
        // sanitize the string
        $string = trim(filter_var($string, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW ));
        
        // make an empty string into a single space (see Roger!)
        if (''==$string) {
            $string=' ';
        }
        
        // restrict to required length
        $string = substr($string, 0, $length);
        
        return $string;
    }
    
    /**
     * Turn the purchases into a big string
     */
    public function getExport($purchases) {
        $lines = array();
        
        // create each line
        foreach ($purchases as $p) {
            $l = array();
            
            // Record type
            $l[] = 'O';
            
            // Tour ref
            $l[] = $this->clean($p->getCode());
            
            // Bkg ref
            $l[] = $this->clean($p->getBookingref());
            
            // Surname
            $l[] = $this->clean($p->getSurname(), 20);
            
            // Title
            $l[] = $this->clean($p->getTitle(), 12);
            
            // First names
            $l[] = $this->clean($p->getFirstname(), 20);
            
            // Address line 1
            $l[] = $this->clean($p->getAddress1(), 25);
            
            // Address line 2
            $l[] = $this->clean($p->getAddress2(), 25);
            
            // Address line 3
            $l[] = $this->clean($p->getCity(), 25);
            
            // Address line 4
            $l[] = $this->clean($p->getCounty(), 25);
            
            // Post code
            $l[] = $this->clean($p->getPostcode(), 8);
            
            // Phone No
            $l[] = $this->clean($p->getPhone(), 15);
            
            // Email
            $l[] = $this->clean($p->getEmail(), 50);
            
            // Start
            $l[] = $this->clean($p->getJoining());
            
            // Destination
            $l[] = $this->clean($p->getDestination());
            
            // Class
            $l[] = $this->clean($p->getClass(), 1);
            
            // Adults
            $l[] = $this->clean($p->getAdults());
            
            // Children
            $l[] = $this->clean($p->getChildren());
            
            // OAP (not used)
            $l[] = '0';
            
            // Family (not used)
            $l[] = '0';
            
            // Meal A
            $l[] = $this->clean($p->getMeala());
            
            // Meal B
            $l[] = $this->clean($p->getMealb());
            
            // Meal C
            $l[] = $this->clean($p->getMealc());
            
            // Meal D
            $l[] = $this->clean($p->getMeald());
            
            // Comment
            $l[] = $this->clean($p->getComment(), 39);
            
            // Payment
            $l[] = $this->clean(intval($p->getPayment() * 100));
            
            // Booking Date
            $bookingdate = $p->getDate();
            $l[] = $this->clean($bookingdate->format('Ymd'));
            
            // Seat supplement
            $l[] = $p->isSeatsupplement() ? 'Y' : 'N';
            
            // Card Payment
            $l[] = 'Y';
            
            // Action required
            $l[] = 'N';
            
            // make tab separated line
            $line = implode("\t", $l);
            $lines[] = $line;
        }
        
        // combine lines
        return implode("\n", $lines);
    }
}
