<?php

namespace thepurpleblob\railtour\library;

class Reports {
    
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
            $l[] = $this->clean($p->code);
            
            // Bkg ref
            $l[] = $this->clean($p->bookingref);
            
            // Surname
            $l[] = $this->clean($p->surname, 20);
            
            // Title
            $l[] = $this->clean($p->title, 12);
            
            // First names
            $l[] = $this->clean($p->firstname, 20);
            
            // Address line 1
            $l[] = $this->clean($p->address1, 25);
            
            // Address line 2
            $l[] = $this->clean($p->address2, 25);
            
            // Address line 3
            $l[] = $this->clean($p->city, 25);
            
            // Address line 4
            $l[] = $this->clean($p->county, 25);
            
            // Post code
            $l[] = $this->clean($p->postcode, 8);
            
            // Phone No
            $l[] = $this->clean($p->phone, 15);
            
            // Email
            $l[] = $this->clean($p->email, 50);
            
            // Start
            $l[] = $this->clean($p->joining);
            
            // Destination
            $l[] = $this->clean($p->destination);
            
            // Class
            $l[] = $this->clean($p->class, 1);
            
            // Adults
            $l[] = $this->clean($p->adults);
            
            // Children
            $l[] = $this->clean($p->children);
            
            // OAP (not used)
            $l[] = '0';
            
            // Family (not used)
            $l[] = '0';
            
            // Meal A
            $l[] = $this->clean($p->meala);
            
            // Meal B
            $l[] = $this->clean($p->mealb);
            
            // Meal C
            $l[] = $this->clean($p->mealc);
            
            // Meal D
            $l[] = $this->clean($p->meald);
            
            // Comment
            $l[] = $this->clean($p->comment, 39);
            
            // Payment
            $l[] = $this->clean(intval($p->payment * 100));
            
            // Booking Date
            $l[] = $this->clean($p->date);
            
            // Seat supplement
            $l[] = $p->seatsupplement ? 'Y' : 'N';
            
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
