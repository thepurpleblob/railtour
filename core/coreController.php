<?php

namespace thepurpleblob\core;

class coreController {

    protected $gump;

    protected $form;

    protected $twig;

    protected $paths;

    protected $back;

    /**
     * Sets the additional pathinfo array in the calling URL
     * so that the controller can access it directly if required.
     */
    public function setPaths($paths) {
        $this->paths = $paths;
    }

    public function getHeaderAssets() {
        global $CFG;

        $css = new AssetCollection(array(
            new HttpAsset('//maxcdn.bootstrapcdn.com/bootswatch/3.2.0/cerulean/bootstrap.min.css'),
            //new HttpAsset('//maxcdn.bootstrapcdn.com/font-awesome/4.1.0/css/font-awesome.min.css'),
            new GlobAsset($CFG->dirroot . '/assets/css/*'),
        ));

        return $css->dump();
    }

    public function getFooterAssets() {
        global $CFG;

        $js = new AssetCollection(array(
            new HttpAsset('//ajax.googleapis.com/ajax/libs/jquery/1.11.1/jquery.min.js'),
            new HttpAsset('//maxcdn.bootstrapcdn.com/bootstrap/3.2.0/js/bootstrap.min.js'),
            new GlobAsset($CFG->dirroot . '/assets/js/*'),
        ));

        return $js->dump();
    }

    private function extendGump() {

        // valid time
        \GUMP::add_validator("time", function($field, $input, $param=null) {
            return strtotime($input[$field]) !== false;
        });

        // valid role
        \GUMP::add_validator('role', function($field, $input, $param=null) {
            $role = $input[$field];
            return ($role=='admin') || ($role=='organiser');
        });

        // valid password
        \GUMP::add_validator('password', function($field, $input, $param=null) {
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
     * Get twig resource
     */
    private function getTwig() {
        global $CFG;

        $twigloader = new \Twig_Loader_Filesystem($CFG->dirroot . '/src/view');
        $this->twig = new \Twig_Environment($twigloader);
    }

    /**
     * Instantiate class in library
     * @param string $name
     */
    public function getLib($name) {
        $namespace = 'lib';
        $classname = $namespace . '\\' . $name;
        return new $classname;
    }

    public function __construct($exception=false) {
        
        // if exception handler, don't bother with this stuff
        if (!$exception) {
            $this->form = new coreForm();
            $this->extendGump();
            $this->gump = new \GUMP();
            $this->getTwig();
            $this->twig->addExtension(new twigextension());
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
     * render a view
     */
    public function View($viewname, $variables=null)
    {
        global $CFG;

        // also need the form class in the variables
        if ($variables) {
            $variables['form'] = $this->form;
        } else {
            $variables = array($this->form);
        }

        // TODO
        // Some global stuff gets passed in system variable
        $user = $this->getUser();
        $system = new \stdClass();
        if ($user) {
            $system->userrole = $user->role;
            $system->fullname = $user->firstname . ' ' . $user->lastname;
            $system->loggedin = true;
        } else {
            $system->userrole = '';
            $system->fullname = '';
            $system->loggedin = false;
        }
        $variables['system'] = $system;

        // Render
        echo $this->twig->render($viewname, $variables);

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
     * Check for login/security
     * Current role posibilities are ROLE_ADMIN and ROLE_ORGANISER
     *
     */
    public function require_login($role, $wantsurl = '') {
        if (!empty($_SESSION['user'])) {
            $user = $_SESSION['user'];
            if ($role =='ROLE_ADMIN') {
                if ($user->role == 'ROLE_ADMIN') {
                    return true;
                } else {
                    $this->redirect($this->Url('user/roleerror'));
                }
            } else {
                return true;
            }
        }

        $_SESSION['wantsurl'] = $wantsurl;
        $this->redirect('user/login');
    }

    /**
     * Get logged in user
     */
    public function getUser() {
        if (!empty($_SESSION['user'])) {
            return $_SESSION['user'];
        } else {
            return false;
        }
    }

    /**
     * get session
     */
    public function getFromSession($name, $default=null) {
    	if (isset($_SESSION[$name])) {
    		return $_SESSION[$name];
    	} else {
    		if ($default) {
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
        return new $classname($this);
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
