<?php

namespace thepurpleblob\core;

use GUMP;

class coreController {

    /** @var GUMP  */
    protected $gump;

    protected $form;

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

    /**
     * Instantiate class in library
     * @param string $name
     * @return mixed
     */
    public function getLib($name) {
        $namespace = 'lib';
        $classname = $namespace . '\\' . $name;
        $lib = new $classname;

        $lib->controller = $this;

        return $lib;
    }

    public function __construct($exception=false) {
        
        // if exception handler, don't bother with this stuff
        if (!$exception) {
            $this->form = new coreForm();
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
            return $this->gump->sanitize($_POST);
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
     * render a view
     * @param string $viewname name of view (minus extension)
     * @param array $variables array of variables to be passed
     */
    public function View($viewname, $variables=array())
    {
        global $CFG;

        // No spaces
        $viewname = trim($viewname);

        // Check cache exists
        $cachedir = $CFG->dirroot . '/cache';
        if (!is_writable($cachedir)) {
            throw new \Exception('Cache dir is not writeable ' . $cachedir);
        }

        // get/setup Mustache.
        $mustache = new \Mustache_Engine(array(
            'loader' => new \Mustache_Loader_FilesystemLoader($CFG->dirroot . '/src/view'),
            'helpers' => array(
                'yesno' => function($bool) {
                    return $bool ? 'Yes' : 'No';
                },
                'path' => function($path) {
                    global $CFG;
                    return $CFG->www . '/index.php/' . $path;
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
        $variables['config'] = $CFG;
        $variables['showlogin'] = (($viewname != 'user/login') && (strpos($viewname, 'booking') !== 0))
            || ($viewname == 'booking/index');
        $variables['haserrors'] = !empty($variables['errors']);


        // Get template
        $template = $mustache->loadTemplate($viewname . '.mustache');

        // and render.
        echo $template->render($variables);

        // The view is always the last thing we do, so just in case...
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
        global $CFG;

        return $CFG->www . '/index.php/' . $path;
    }

    /**
     * Redirect to some other location
     * @param string $path relative path
     * @parm bool $back flag rearwards links so they (can be) handled appropriately
     */
    public function redirect($path, $back = false) {
        global $CFG;

        $_SESSION['back'] = $back;
        $url = $this->Url($path);
        header("Location: $url");
        die;
    }

    /**
     * Get the user from the session if it exists
     * @return mixed user object or false
     */
    public static function getSessionUser() {
        if (empty($_SESSION['user'])) {
            return false;
        }
        $userid = $_SESSION['user'];
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
        $_SESSION['wantsurl'] = $wantsurl;
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
     * get session
     */
    public function getFromSession($name, $default=null) {
        if (isset($_SESSION[$name])) {
            return $_SESSION[$name];
        } else {
            if ($default) {
                $_SESSION[$name] = $default;
                return $default;
            } else
                throw new \Exception("Session data for '$name' was not found");
        }
    }

    /**
     * Set a value in session
     * @param $name
     * @param $value
     */
    public function setSession($name, $value) {
        $_SESSION[$name] = $value;
    }

    /**
     * Get library (Business rules?) class (from src/library directory)
     */
    public function getLibrary($name) {
        global $CFG;

        $classname = '\\thepurpleblob\\' . $CFG->projectname . '\\library\\' . $name;
        $lib = new $classname;

        // So class can reference the controller
        $lib->controller = $this;

        return $lib;
    }

    /**
     * Write to log file (only write if logging is actually enabled
     */
    public function log($message) {
        global $CFG;

        // End of line character (in case it's wrong)
        $eol = "\r\n";

        // Forget if if debugging not enabled.
        if (empty($CFG->debugging)) {
            return;
        }

        $filename = $CFG->dirroot . '/log/debug';
        $preamble = date('Y-m-d H:i | ') . $_SERVER['REMOTE_ADDR'];
        if (isset($_SESSION['purchaseid'])) {
            $purchaseid = $_SESSION['purchaseid'];
            $preamble .= '| ID:' . $purchaseid . $eol;
        }
        file_put_contents($filename, $preamble . $message . $eol, LOCK_EX | FILE_APPEND);
    }

}
