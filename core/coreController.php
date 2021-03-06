<?php

namespace thepurpleblob\core;

use GUMP;

class coreController {

    /** @var GUMP  */
    protected $gump;

    protected $paths;

    protected $back;

    /**
     * Sets the additional pathinfo array in the calling URL
     * so that the controller can access it directly if required.
     */
    public function setPaths($paths) {
        $this->paths = $paths;
    }

    private function extendGump() {

        // valid time
        GUMP::add_validator("time", function($field, $input, $param=null) {
            return strtotime($input[$field]) !== false;
        });

        // valid role
        GUMP::add_validator('role', function($field, $input, $param=null) {
            $role = $input[$field];
            return ($role=='admin') || ($role=='organiser');
        });

        // valid password
        GUMP::add_validator('password', function($field, $input, $param=null) {
            $password = $input['password'];
            $username = $input['username'];
            $user = \ORM::for_table('user')->where(array(
                'username' => $username,
                'password' => md5($password),
                ))->find_one();
            return !($user === false);
        });
    }

    public function __construct($exception=false) {
        
        // if exception handler, don't bother with this stuff
        if (!$exception) {
            $this->extendGump();
            $this->gump = new GUMP();
            if (isset($_SESSION['back'])) {
                $this->back = $_SESSION['back'];
            } else {
                $this->back = false;
            }
        }
    }

    /**
     * Get GUMP (form verification software)
     */
    public function getGump() {
        return $this->gump;
    }

    /**
     * Get request data
     */
    public function getRequest() {
        if (empty($_POST)) {
            return false;
        } else {
            return $_POST;
            //return $this->gump->sanitize($_POST);
        }
    }

    /**
     * Get parameter
     */
    public function getParam($name, $default=null) {
        $params = $this->getRequest();
        if (!isset($params[$name])) {
            return $default;
        } else {
            return $params[$name];
        }
    }

    /**
     * Specify required modules
     * These are added to an array to be included by require.js
     * The
     * @param string $name module name (sans js)
     * @param string $method method name to execute
     * @param array $params array of params to pass to method
     */
    public function requirejs($name, $method, $params = null) {
        $module = new stdClass();
        $module->name = $name;
        $module->method = $method;
        $module->params = $params;
        $this->js[] = $module;
    }

    /**
     * Get jsenv JSON
     * @param array $variables
     * @return string
     */
    private function getJsenv($variables) {

        // Is there a service defined
        if (isset($variables['service']->id)) {
            $serviceid = $variables['service']->id;
        } else {
            $serviceid = 0;
        }

        // Is there a destination defined
        if (isset($variables['destination']->id)) {
            $destinationid = $variables['destination']->id;
        } else {
            $destinationid = 0;
        }

        // Is there a joining station defined
        if (isset($variables['joining']->id)) {
            $joiningid = $variables['joining']->id;
        } else {
            $joiningid = 0;
        }       

        $jsenv = [
            'www' => $_ENV['www'],
            'dirroot' => $_ENV['dirroot'],
            'serviceid' => $serviceid,
            'destinationid' => $destinationid,
            'joiningid' => $joiningid,
        ];

        return json_encode($jsenv);
    }

    /**
     * render a view
     * @param string $viewname name of view (minus extension)
     * @param array $variables array of variables to be passed
     * @return string
     */
    public function renderView($viewname, $variables=array())
    {

        // No spaces
        $viewname = trim($viewname);

        // Check cache exists
        $cachedir = $_ENV['dirroot'] . '/cache';
        if (!is_writable($cachedir)) {
            throw new \Exception('Cache dir is not writeable ' . $cachedir);
        }

        // get/setup Mustache.
        $mustache = new \Mustache_Engine(array(
            'loader' => new \Mustache_Loader_FilesystemLoader($_ENV['dirroot'] . '/src/view'),
            'helpers' => array(
                'yesno' => function($bool) {
                    return $bool ? 'Yes' : 'No';
                },
                'path' => function($path) {
                    return $_ENV['www'] . '/index.php/' . $path;
                }
            ),
            'cache' => $cachedir,
        ));

        // Add some extra variables to array
        $user = $this->getUser();
        $system = new \stdClass();
        if ($user) {
            $system->userrole = $user->role;
            $system->admin = $user->role == 'ROLE_ADMIN';
            $system->organiser = $user->role == 'ROLE_ORGANISER';
            $system->adminpages = $system->admin || $system->organiser;
            $system->fullname = $user->firstname . ' ' . $user->lastname;
            $system->loggedin = true;
        } else {
            $system->userrole = '';
            $system->admin = false;
            $system->fullname = '';
            $system->loggedin = false;
        }
        $system->sessionid = session_id();
        $variables['system'] = $system;
        $variables['config'] = (object)$_ENV;
        $variables['showlogin'] = (($viewname != 'user/login') && (strpos($viewname, 'booking') !== 0));
        $variables['haserrors'] = !empty($variables['errors']);
        $variables['jsenv'] = $this->getJsenv($variables);

        // Get template
        $template = $mustache->loadTemplate($viewname . '.mustache');

        // and render.
        return $template->render($variables);
    }

    /**
     * Display a view
     * Use this to display the view directly
     * @param string $viewname name of view (minus extension)
     * @param array $variables array of variables to be passed
     */
    public function View($viewname, $variables=array()) {
        echo $this->renderView($viewname, $variables);
        die;
    }

    /**
     * Display form errors
     *
     */
    public function formErrors($errors) {
        echo '<div class="alert alert-danger">';
        echo "<ul>";
        foreach ($errors as $error) {
            echo "<li>$error</li>";
        }
        echo "</ul></div>";
    }

    /**
     * Create a url from path
     */
    public function Url($path) {

        return $_ENV['www'] . '/index.php/' . $path;
    }

    /**
     * Redirect to some other location
     * @param string $path relative path
     * @parm bool $back flag rearwards links so they (can be) handled appropriately
     */
    public function redirect($path, $back = 0) {

        Session::write('back', $back);
        $url = $this->Url($path);
        header("Location: $url");
        die;
    }

    /**
     * Get the user from the session if it exists
     * @return mixed user object or false
     */
    public static function getSessionUser() {
        if (!Session::exists('user')) {
            return false;
        }
        $userid = (int)Session::read('user');
        $user = \ORM::forTable('srps_users')->findOne($userid);
        if (!$user) {
            throw new \Exception("User id $userid not found in db");
        }

        return $user;
    }

    /**
     * Check for login/security
     * Current role posibilities are ROLE_ADMIN, ROLE_ORGANISER
     * and ROLE_TELEPHONE (in that order)
     *
     */
    public function require_login($role, $wantsurl = '') {

        // If someone is logged in, check their role
        if ($user = $this->getSessionUser()) {
            if ($role == 'ROLE_ADMIN' && ($user->role == 'ROLE_ADMIN')) {
                return true;
            }
            if ($role == 'ROLE_ORGANISER' && (($user->role == 'ROLE_ORGANISER') || ($user->role == 'ROLE_ADMIN'))) {
                return true;
            }

            // Everybody (with a login) can do the Telephone role
            if ($role == 'ROLE_TELEPHONE') {
                return true;
            }
            $this->redirect('user/roleerror');
        }

        // Not logged in
        Session::write('wantsurl', $wantsurl);
        $this->redirect('user/login');
    }

    /**
     * Get logged in user
     */
    public function getUser() {
        $user = $this->getSessionUser();

        return $user;
    }

    /**
     * Get library (Business rules?) class (from src/library directory)
     */
    public function getLibrary($name) {

        $classname = '\\thepurpleblob\\' . $_ENV['projectname'] . '\\library\\' . $name;
        $lib = new $classname;

        // So class can reference the controller
        $lib->controller = $this;

        return $lib;
    }

    /**
     * Write to log file (only write if logging is actually enabled
     */
    public function log($message) {

        // End of line character (in case it's wrong)
        $eol = "\r\n";

        // Forget if if debugging not enabled.
        if (empty($_ENV['debugging'])) {
            return;
        }

        $filename = $_ENV['dirroot'] . '/log/debug';
        $preamble = date('Y-m-d H:i | ') . $_SERVER['REMOTE_ADDR'];
        if (Session::exists('purchaseid')) {
            $purchaseid = Session::read('purchaseid');
            $preamble .= '| ID:' . $purchaseid . $eol;
        }
        file_put_contents($filename, $preamble . $message . $eol, LOCK_EX | FILE_APPEND);
    }

}
