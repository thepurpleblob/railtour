<?php

namespace thepurpleblob\railtour\controller;

use thepurpleblob\core\coreController;
use thepurpleblob\railtour\library\User;

class userController extends coreController {

    /**
     * Not an action really
     */
    public function installAction() {

        // Create new admin user if required
        User::installAdmin();
    }

    /**
     * Role problem
     */
    public function roleerrorAction() {
        $this->View('user/roleerror');
    }

    /**
     * @param $status status from previous screen
     */
    public function indexAction($status = '')
    {
        $this->require_login('ROLE_ADMIN', 'user/index');

        $users = User::getUsers();

        $this->View('user/index', array(
            'users' => $users,
            'status' => $status,
        ));
    }

    public function loginAction() {

        // hopefully no errors
        $errors = null;

        // initial username is empty
        $username = '';
        $rememberme = false;
        if (isset($_COOKIE['railtouruser'])) {
            $username = $_COOKIE['railtouruser'];
            $rememberme = true;
        }

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
                $rememberme = isset($data['rememberme']);

                // Validate user
                $user = User::validate($username, $password);

                if ($user) {
                    $_SESSION['user'] = $user->id;
                    if ($rememberme) {
                        setcookie('railtouruser', $username, time() + 365 * 24 * 3600, '/');
                    } else {
                        setcookie('railtouruser', '', time() - 3600, '/');
                    }
                    if (!empty($_SESSION['wantsurl'])) {
                        $redirect = $_SESSION['wantsurl'];
                    } else {
                        $redirect = 'admin/main';
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
            'rememberme' => $rememberme ? 'checked' : '',
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
            'ROLE_TELEPHONE' => 'ROLE_TELEPHONE',
        );

        // find/create the user
        $user = User::getUser($username);

        // hopefully no errors
        $errors = null;

        // anything submitted?
        if ($data = $this->getRequest()) {

            // Cancel?
            if (!empty($data['cancel'])) {
                $this->redirect('user/index/Cancelled');
            }

            // Validate
            $rules = array(
                'firstname' => 'required',
                'lastname' => 'required',
                'role' => 'required',
            );
            if (!$username) {
                $rules = array(
                    'username' => 'required',
                    'firstname' => 'required',
                    'lastname' => 'required'
                );

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
                $this->redirect('user/index/User saved');
            } else {
                $errors = $this->gump->get_readable_errors(false);
            }
        }

        // Create form
        $form = new \stdClass();
        $usernameattrs = $user->username ? ['disabled' => 'disabled'] : [];
        $form->username = $this->form->text('username', 'Username', $user->username, FORM_REQUIRED, $usernameattrs);
        $form->firstname = $this->form->text('firstname', 'First name', $user->firstname, FORM_REQUIRED);
        $form->lastname = $this->form->text('lastname', 'Last name', $user->lastname, FORM_REQUIRED);
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

    /**
     * @param $username
     * @throws \Exception
     */
    public function deleteAction($username) {

        $this->require_login('ROLE_ADMIN', 'user/delete/' . $username);
        
        // check it isn't admin
        if ('admin' == $username) {
            throw new \Exception("may not delete primary admin");
        }
        
        User::delete($username);

        $this->redirect('user/index/User deleted');
    }    
}

