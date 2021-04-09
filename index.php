<?php

require_once(dirname(__FILE__) . '/vendor/autoload.php');
require_once(dirname(__FILE__) . '/core/version.php');

// Debugging helper
function dd($message, ...$values) {
    echo "<pre>$message";
    foreach ($values as $value) {
        var_dump($value);
    }
    echo "</pre>";
    die;
}

// Error handling
//$whoops = new \Whoops\Run;
//$whoops->pushHandler(new \Whoops\Handler\PrettyPageHandler);
//$whoops->register();

// Use classes
use thepurpleblob\core\setup;
use thepurpleblob\core\install;

// Dotenv
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Basic setup and checks
setup::database();
install::action($version);

// Start sessions
$db = ORM::get_db();
$session = new Zebra_Session($db, '8zgHypW38HjSt');

error_reporting(E_ALL);
ini_set('display_errors', 'stdout');
ini_set('log_errors', 1);
ini_set('html_errors', 1);

// see if the database has been installed at all
require_once(dirname(__FILE__) . '/core/install.php');

// If no path is given, use the default
if (!empty($_SERVER['PATH_INFO'])) {
    $info = $_SERVER['PATH_INFO'];
} else {
    $info = '/' . $CFG->defaultroute;
}

if ($info) {
    $paths = explode('/', $info);
} else {
    throw new Exception("No path specified");
}

// get controller and action
$controller_name = $paths[1];
if (!$controller_name) {
    throw new Exception("No controller specified for '$controller_name'");
}
if (!$action_name = $paths[2]) {
    throw new Exception("No action specified for controller '$controller_name'");
}

// try to load controller
$controller_name = '\\thepurpleblob\\' . $CFG->projectname . '\\controller\\' . $controller_name . 'Controller';
if (!class_exists($controller_name)) {
    throw new Exception("Controller class does not exist - $controller_name");
}
$controller = new $controller_name;

// execute specified action
$action_name .= 'Action';
array_shift($paths);
array_shift($paths);
array_shift($paths);
$controller->setPaths($paths);
call_user_func_array(array($controller, $action_name), $paths);
