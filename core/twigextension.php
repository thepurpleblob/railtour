<?php
/**
 * Created by PhpStorm.
 * User: howard
 * Date: 14/11/2015
 * Time: 17:50
 */

namespace thepurpleblob\core;


class twigextension extends \Twig_Extension
{
    public function getName() {
        return "pbtwigextension";
    }

    public function getFunctions()
    {
        return array(
            'path' => new \Twig_Function_Function('\thepurpleblob\core\twigextension::path'),
            'asset' => new \Twig_Function_Function('\thepurpleblob\core\twigextension::asset'),
            'form_text' => new \Twig_Function_Function('\thepurpleblob\core\twigextension::form_text', array('needs_context' => true)),
            'form_date' => new \Twig_Function_Function('\thepurpleblob\core\twigextension::form_date', array('needs_context' => true)),
            'form_textarea' => new \Twig_Function_Function('\thepurpleblob\core\twigextension::form_textarea', array('needs_context' => true)),
            'form_yesno' => new \Twig_Function_Function('\thepurpleblob\core\twigextension::form_yesno', array('needs_context' => true)),
        );
    }

    public static function path($path, $params=null) {
        global $CFG;

        if ($params) {
            return $CFG->www . '/index.php/' . $path . '/' . $params;
        } else {
            return $CFG->www . '/index.php/' . $path;
        }
    }

    public static function asset($asset) {
        global $CFG;

        return $CFG->www . '/src/assets/' . $asset;
    }

    public static function form_text($context, $name, $label, $value, $required=false, $attrs=null) {
        $form = $context['form'];
        $form->text($name, $label, $value, $required, $attrs);
    }

    public static function form_date($context, $name, $label, $value, $required=false, $attrs=null) {
        $form = $context['form'];
        $form->date($name, $label, $value, $required, $attrs);
    }

    public static function form_textarea($context, $name, $label, $value, $required=false, $attrs=null) {
        $form = $context['form'];
        $form->textarea($name, $label, $value, $required, $attrs);
    }

    public static function form_yesno($context, $name, $label, $yes) {
        $form = $context['form'];
        $form->yesno($name, $label, $yes);
    }

}





