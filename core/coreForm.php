<?php

namespace thepurpleblob\core;

class coreForm {
    
    /*
     * Fill arrays for drop-downs
     */
    private function fill($low, $high) {
        $a = array();
        for ($i=$low; $i<=$high; $i++) {
            $a[$i] = $i;
        }
        return $a;
    }
    
    public function text($name, $label, $value, $required=false) {
        $id = $name . 'Text';
        $reqclass = $required ? 'has-feedback has-warning' : '';
        echo '<div class="form-group '.$reqclass.'">';
        echo '    <label for="'.$id.'" class="col-sm-4 control-label">'.$label.'</label>';
        echo '    <div class="col-sm-8">';
        if ($required) {
            echo '    <span class="glyphicon glyphicon-asterisk form-control-feedback"></span>';
        }
        echo '    <input type="text" class="form-control input-sm" name="'.$name.'" id="'.$id.'" value="'.$value.'" />';

        echo '</div></div>';
    }
    
    public function password($name, $label) {
        $id = $name . 'Password';
        echo '<div class="form-group">';
        echo '    <label for="'.$id.'" class="col-sm-4 control-label">'.$label.'</label>';
        echo '    <div class="col-sm-8">';
        echo '    <input type="password" class="form-control input-sm" name="'.$name.'" id="'.$id.'" />';
        echo '</div></div>';
    }   
    
    public function select($name, $label, $selected, $options, $choose='', $labelcol=4) {
        $id = $name . 'Select';
        $inputcol = 12 - $labelcol;
        echo '<div class="form-group">';
        echo '    <label for="'.$id.'" class="col-sm-' . $labelcol . ' control-label">'.$label.'</label>';
        echo '    <div class="col-sm-' . $inputcol .'">';
        echo '    <select class="form-control input-sm" name="'.$name.'">';
        if ($choose) {
        	echo '<option selected disabled="disabled">'.$choose.'</option>';
        }
        foreach ($options as $value => $option) {
            if ($value == $selected) {
                $strsel = 'selected';
            } else {
                $strsel = '';
            }
            echo '<option value="'.$value.'" '.$strsel.'>'.$option.'</option>';            
        }
        echo '    </select></div>';
        echo "</div>";
    }
    
    public function hidden($name, $value) {
        echo '<input type="hidden" name="'.$name.'" value="'.$value.'" />';
    }
    
    public function buttons($save='Save', $cancel='Cancel', $swap=false) {
        echo '<div class="form-group">';
        echo '<div class="col-sm-offset-4 col-sm-8">';
        if (!$swap) {
            echo '    <button type="submit" name="save" value="save" class="btn btn-primary">'.$save.'</button>';
            echo '    <button type="submit" name="cancel" value="cancel" class="btn btn-warning">'.$cancel.'</button>'; 
        } else {
        	echo '    <button type="submit" name="cancel" value="cancel" class="btn btn-warning">'.$cancel.'</button>';
        	echo '    <button type="submit" name="save" value="save" class="btn btn-primary">'.$save.'</button>';        	
        }       
        echo '</div></div>';
    }
}

