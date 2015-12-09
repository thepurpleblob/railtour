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

    /**
     * Create additional attributes
     */
    private function attributes($attrs) {
        if (!$attrs) {
            return ' ';
        }
        $squash = array();
        foreach ($attrs as $name => $value) {
            $squash[] = $name . '="' . htmlspecialchars($value) . '"';
        }
        return implode(' ', $squash);
    }
    
    public function text($name, $label, $value, $required=false, $attrs=null) {
        $id = $name . 'Text';
        $reqclass = $required ? 'form-required' : '';
        echo '<div class="form-group '.$reqclass.'">';
        if ($label) {
            echo '    <label for="' . $id . '" class="col-sm-4 control-label">' . $label . '</label>';
        }
        echo '    <div class="col-sm-8">';
        echo '    <input type="text" class="form-control input-sm" name="'.$name.'" id="'.$id.'" value="'.$value.'" '.
            $this->attributes($attrs).'/>';

        echo '</div></div>';
    }

    /**
     * @param $name
     * @param $label
     * @param $date Probably in MySQL yyyy-mm-dd format
     * @param bool|false $required
     * @param null $attrs
     */
    public function date($name, $label, $date, $required=false, $attrs=null) {
        $timestamp = strtotime($date);
        $localdate = date('d/m/Y', $timestamp);
        $id = $name . 'Date';
        $reqclass = $required ? 'form-required' : '';
        echo '<div class="form-group '.$reqclass.'">';
        if ($label) {
            echo '    <label for="' . $id . '" class="col-sm-4 control-label">' . $label . '</label>';
        }
        echo '    <div class="col-sm-8">';
        echo '    <input type="text" class="form-control input-sm datepicker" name="'.$name.'" id="'.$id.'" value="'.$localdate.'" '.
            $this->attributes($attrs).'/>';

        echo '</div></div>';
    }

    public function textarea($name, $label, $value, $required=false, $attrs=null) {
        $id = $name . 'Textarea';
        $reqclass = $required ? 'form-required' : '';
        echo '<div class="form-group '.$reqclass.'">';
        if ($label) {
            echo '    <label for="' . $id . '" class="col-sm-4 control-label">' . $label . '</label>';
        }
        echo '    <div class="col-sm-8">';
        echo '    <textarea class="form-control input-sm" name="'.$name.'" id="'.$id.'" '.$this->attributes($attrs).'/>';
        echo $value;
        echo '    </textarea>';

        echo '</div></div>';
    }
    
    public function password($name, $label) {
        $id = $name . 'Password';
        echo '<div class="form-group">';
        if ($label) {
            echo '    <label for="' . $id . '" class="col-sm-4 control-label">' . $label . '</label>';
        }
        echo '    <div class="col-sm-8">';
        echo '    <input type="password" class="form-control input-sm" name="'.$name.'" id="'.$id.'" />';
        echo '</div></div>';
    }   
    
    public function select($name, $label, $selected, $options, $choose='', $labelcol=4) {
        $id = $name . 'Select';
        $inputcol = 12 - $labelcol;
        echo '<div class="form-group">';
        if ($label) {
            echo '    <label for="' . $id . '" class="col-sm-' . $labelcol . ' control-label">' . $label . '</label>';
        }
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

    public function yesno($name, $label, $yes) {
        $options = array(
            0 => 'No',
            1 => 'Yes',
        );
        $selected = $yes ? 1 : 0;
        $this->select($name, $label, $selected, $options);
    }

    public function errors($errors) {
        if (!$errors) {
            return;
        }
        echo '<ul class="form-errors">';
        foreach ($errors as $error) {
            echo '<li class="form-error">' . $error . '</li>';
        }
        echo "</ul>";
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

