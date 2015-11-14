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
            'path' => new \Twig_Function_Function('\thepurpleblob\core\twig_path'),
            'asset' => new \Twig_Function_Function('\thepurpleblob\core\twig_asset'),
        );
    }

}

function twig_path($path, $params=null) {
    global $CFG;

    if ($params) {
        return $CFG->www . '/index.php/' . $path . '/' . $params;
    } else {
        return $CFG->www . '/index.php/' . $path;
    }
}

function twig_asset($asset) {
    global $CFG;

    return $CFG->www . '/src/assets/' . $asset;
}