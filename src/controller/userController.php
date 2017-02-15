<?php

namespace thepurpleblob\railtour\controller;

use thepurpleblob\core\coreController;

class UserController extends coreController
{

    protected $userlib;

    /**
     * Constructor
     */
    public function __construct($exception = false)
    {
        parent::__construct($exception);

        // Library
        $this->userlib = $this->getLibrary('User');
    }

    /**
     * Not an action really
     */
    public function installAction() {

        // Create new admin user if required
        $this->userlib->installAdmin();
    }
    
    public function indexAction()
    {
        $this->require_login('ROLE_ADMIN', 'user/index');

        $users = $this->userlib->getUsers();

        $this->View('user/index', array(
            'users' => $users,
        ));
    }

    public function loginAction() {

        // hopefully no errors
        $errors = null;

        // initial username is empty
        $username = '';

        // anything submitted?
        if ($data = $this->getRequest()) {

            // Validate
            $this->gump->validation_rules(array(
                'username' => 'required',
                'password' => 'required',
            ));

            if ($data = $this->gump->run($data)) {
                $username = $data['username'];
                $password = $data['password'];

                // Validate user
                $user = $this->userlib->validate($username, $password);

                if ($user) {
                    $_SESSION['user'] = $user;
                    if (!empty($_SESSION['wantsurl'])) {
                        $redirect = $_SESSION['wantsurl'];
                    } else {
                        $redirect = 'service/index';
                    }
                    $this->redirect($redirect);
                } else {
                    $errors[] = 'Username or password incorrect';
                }
            }
        }

        $this->View('user/login', array(
            'action' => $this->Url('user/login'),
            'haserrors' => !empty($errors),
            'errors' => $errors,
            'last_username' => $username,
        ));
    }

    public function logoutAction() {
        global $CFG;

        // Just remove the user session
        unset($_SESSION['user']);

        // TODO: we might want to be cleverer about this...
        $this->redirect($CFG->defaultroute);
    }
    
    public function editAction($username = '')
    {

        $this->require_login('ROLE_ADMIN', 'user/index');

        // possible roles
        $rolechoice = array(
            'ROLE_ADMIN' => 'ROLE_ADMIN',
            'ROLE_ORGANISER' => 'ROLE_ORGANISER',
        );

        // find/create the user
        $user = $this->userlib->getUser($username);

        // hopefully no errors
        $errors = null;

        // anything submitted?
        if ($data = $this->getRequest()) {

            // Cancel?
            if (!empty($data['cancel'])) {
                $this->redirect('user/index');
            }

            // Validate
            $rules = array(
                'firstname' => 'required',
                'lastname' => 'required',
                'role' => 'required',
            );
            if (!$username) {
                $rules['username'] = 'required';
            }
            $this->gump->validation_rules($rules);

            if ($data = $this->gump->run($data)) {
                if (empty($user->username)) {
                    $user->username = $data['username'];
                }
                $user->firstname = $data['firstname'];
                $user->lastname = $data['lastname'];
                $user->role = $data['role'];
                $user->is_active = $data['is_active'];
                if (!empty($data['password'])) {
                    $user->password = md5($data['password']);
                }
                $user->save();
                $this->redirect('user/index');
            } else {
                $errors = $this->gump->get_readable_errors(false);
            }
        }

        // Create form
        $form = new \stdClass();
        $form->username = $this->form->text('username', 'Username', $user->username);
        $form->firstname = $this->form->text('firstname', 'First name', $user->firstname);
        $form->lastname = $this->form->text('lastname', 'Last name', $user->lastname);
        $form->password = $this->form->password('password', 'Password');
        $form->role = $this->form->select('role', 'Role', $user->role, $rolechoice);
        $form->is_active = $this->form->yesno('is_active', 'Active account?', $user->is_active);
        
        // display form
        $this->View('user/edit', array(
            'username' => $username,
            'haserrors' => !empty($errors),
            'user' => $user,
            'rolechoice' => $rolechoice,
            'form' => $form,
            'errors' => $errors,
        ));        
    }
    
    public function deleteAction($username) {
        
        // check it isn't admin
        if ('admin' == $username) {
            throw new \Exception("may not delete primary admin");
        }
        
        // find the user
        $user = \ORM::forTable('srps_users')->where('username', $username)->findOne();
        if (!$user) {
            throw new \Exception("User $username not found in db");
        }
        
        // Delete the user
        $user->delete();

        $this->redirect('user/index');
    }    
}

