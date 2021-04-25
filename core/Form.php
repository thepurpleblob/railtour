<?php


namespace thepurpleblob\core;

define('FORM_REQUIRED', true);
define('FORM_OPTIONAL', false);

class Form {
    
    /*
     * Fill arrays for drop-downs
     */
    private static function fill($low, $high) {
        $a = array();
        for ($i=$low; $i<=$high; $i++) {
            $a[$i] = $i;
        }
        return $a;
    }

    /**
     * Create additional attributes
     */
    private static function attributes($attrs) {
        if (!$attrs) {
            return ' ';
        }
        $squash = array();
        foreach ($attrs as $name => $value) {
            $squash[] = $name . '="' . htmlspecialchars($value) . '"';
        }
        return implode(' ', $squash);
    }

    /**
     * @param $name
     * @param $label
     * @param $value
     * @param bool $required
     * @param null $attrs
     * @param string $type option HTML5 type
     * @return string
     */
    public static function text($name, $label, $value, $required=false, $attrs=null, $type='text') {
        $id = $name . 'Text';
        $reqstr = $required ? 'required="true"' : '';
        $validation = $required && !$value ? '&nbsp;<small class="text-danger">(required)</small>' : '';
        $html = '<div class="mb-3">';
        if ($label) {
            $html .= '    <label for="' . $id . '" class="form-label">' . $label . ' ' . $validation . '</label>';
        }
        $html .= '    <input type="' . $type . '" class="form-control input-sm" name="'.$name.'" id="'.$id.'" value="'.$value.'" '.
            Form::attributes($attrs) . ' ' . $reqstr . '/>';  
        $html .= '</div>';

        return $html;
    }

    /**
     * @param $name
     * @param $label
     * @param $date Probably in MySQL yyyy-mm-dd format
     * @param bool|false $required
     * @param null $attrs
     */
    public static function date($name, $label, $date, $required=false, $attrs=null) {
        $timestamp = strtotime($date);
        $localdate = date('Y-m-d', $timestamp);
        $id = $name . 'Date';
        $reqstr = $required ? 'required' : '';
        $html = '<div class="mb-3">';
        if ($label) {
            $html .= '    <label for="' . $id . '" class="col-sm-4 control-label">' . $label . '</label>';
        }
        $html .= '    <div class="col-sm-8">';
        $html .= '    <input type="date" class="form-control input-sm datepicker" name="'.$name.'" id="'.$id.'" value="'.$localdate.'" '.
            Form::attributes($attrs) . ' ' . $reqstr . '/>';

        $html .= '</div></div>';

        return $html;
    }

    /**
     * @param $name
     * @param $label
     * @param $value
     * @param bool $required
     * @param null $attrs
     * @return string
     */
    public static function textarea($name, $label, $value, $required=false, $attrs=null) {
        $id = $name . 'Textarea';
        $reqstr = $required ? 'required="true"' : '';
        $validation = $required && !$value ? '&nbsp;<small class="text-danger">(required)</small>' : '';
        $html = '<div class="mb-3">';
        if ($label) {
            $html .= '    <label for="' . $id . '" class="form-label">' . $label . $validation . '</label>';
        }
        $html .= '    <textarea class="form-control input-sm" name="'.$name.'" id="'.$id.'" '. Form::attributes($attrs) . ' ' . $reqstr . '/>';
        $html .= $value;
        $html .= '    </textarea>';
        $html .= '</div>';

        return $html;
    }
    
    public static function password($name, $label) {
        $id = $name . 'Password';
        $html = '<div class="form-group">';
        if ($label) {
            $html .= '    <label for="' . $id . '" class="col-sm-4 control-label">' . $label . '</label>';
        }
        $html .= '    <div class="col-sm-8">';
        $html .= '    <input type="password" class="form-control input-sm" name="'.$name.'" id="'.$id.'" />';
        $html .= '</div></div>';

        return $html;
    }   
    
    public static function select($name, $label, $selected, $options, $choose='', $attrs=[]) {
        $id = $name . 'Select';
        //$inputcol = 12 - $labelcol;
        $inputcol = 4;
        if (empty($attrs['class'])) {
            $attrs['class'] = '';
        }
        $attrs['class'] .= ' form-control input-sm';
        $html = '<div class="mb-3">';
        if ($label) {
            $html .= '    <label for="' . $id . '" class="control-label">' . $label . '</label>';
        }
        $html .= '    <select class="form-select" name="'.$name.'" id="' . $id . '" ' . Form::attributes($attrs) . ' >';
        if ($choose) {
        	$html .= '<option selected disabled="disabled">'.$choose.'</option>';
        }
        foreach ($options as $value => $option) {
            if ($value == $selected) {
                $strsel = 'selected';
            } else {
                $strsel = '';
            }
            $html .= '<option value="'.$value.'" '.$strsel.'>'.$option.'</option>';
        }
        $html .= '    </select>';
        $html .= "</div>";

        return $html;
    }

    /**
     * NOTE: Label currently doesn't do anything (it used to)
     */
    public static function radio($name, $label, $selected, $options, $labelcol=4) {
        $id = $name . 'Radio';
        $inputcol = 12 - $labelcol;
        $html = '<div class="form-group">';
        foreach ($options as $value => $option) {
            $id = 'radio_' . $name . '_' . $value;
            if ($value == $selected) {
                $checked = 'checked';
            } else {
                $checked = '';
            }
            $html .= '<div class="form-check">';
            $html .= '<input class="form-check-input" type="radio" name="' . $name .'"  value="' . $value . '" id="' . $id . '" ' . $checked . '>';
            $html .= '<label class="form-check-label" for="' . $id . '" >';
            $html .= $option;
            $html .= '</label>';
            $html .= '</div>';
        }
        $html .= '</div>';

        return $html;
    }

    public static function yesno($name, $label, $yes) {
        $options = array(
            0 => 'No',
            1 => 'Yes',
        );
        $selected = $yes ? 1 : 0;
        return Form::select($name, $label, $selected, $options);
    }

    public static function switch($name, $label, $on) {
        $id = $name . 'Switch';
        $checked = $on ? 'checked' : '';
        $html = '<div class="form-check form-switch">';
        $html .= '  <input class="form-check-input" type="checkbox" id="' . $id . '" ' . $checked . '>';
        $html .= '  <label class="form-check-label" for="' . $id . '">' . $label . '</label>';
        $html .= '</div>';

        return $html;
    }

    public static function errors($errors) {
        if (!$errors) {
            return;
        }
        echo '<ul class="form-errors">';
        foreach ($errors as $error) {
            echo '<li class="form-error">' . $error . '</li>';
        }
        echo "</ul>";
    }
    
    public static function hidden($name, $value) {
        $id = $name . 'Hidden';
        return '<input type="hidden" name="'.$name.'" value="'.$value.'" id="' . $id . '"/>';
    }
    
    public static function buttons($save='Save', $cancel='Cancel', $swap=false) {
        $html = '<div class="form-group">';
        $html .= '<div class="col-sm-offset-4 col-sm-8">';
        if (!$swap) {
            $html .= '    <button type="submit" name="save" value="save" class="btn btn-primary">'.$save.'</button>';
            $html .= '    <button type="submit" name="cancel" value="cancel" class="btn btn-warning">'.$cancel.'</button>';
        } else {
        	$html .= '    <button type="submit" name="cancel" value="cancel" class="btn btn-warning">'.$cancel.'</button>';
        	$html .= '    <button type="submit" name="save" value="save" class="btn btn-primary">'.$save.'</button>';
        }       
        $html .= '</div></div>';

        return $html;
    }
}

