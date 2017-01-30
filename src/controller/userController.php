<?php

namespace thepurpleblob\railtour\controller;

use thepurpleblob\core\coreController;

class UserController extends coreController
{

    /**
     * Not an action really
     */
    public function installAction() 
    {

        // Get all our users
        $users = \ORM::forTable('User')->findMany();
        
        // if there are none, we will create the default admin user
        if (!$users) {
            $user = \ORM::forTable('User')->create();
            $user->firstname = 'admin';
            $user->lastname = 'admin';
            $user->username = 'admin';
            $user->password = md5('admin');
            $user->role = 'ROLE_ADMIN';
            $user->save();
        }
    }
    
    public function indexAction()
    {
        $this->require_login('ROLE_ADMIN', 'user/index');

        // Get all our users
        $users = \ORM::forTable('srps_users')->findMany();

        $this->View('user/index.html.twig', array(
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
                $user = \ORM::for_table('srps_users')
                    ->where(array(
                        'username' => $username,
                        'password' => md5($password),
                    ))
                    ->findOne();
                if ($user) {
                    $_SESSION['user'] = $user;
                    if (!empty($_SESSION['wantsurl'])) {
                        $redirect = $_SESSION['wantsurl'];
                    } else {
                        $redirect = 'service/index';
                    }
                    $this->redirect($redirect);
                } else {
                    $errors[] = 'Login is invalid';
                }
            }
        }

        $this->View('user/login', array(
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
        if (!empty($username)) {
            $user = \ORM::forTable('srps_users')->where('username', $username)->findOne();
            if (!$user) {
                throw new \Exception("User $username not found in db");
            }
        } else {
            $user = \ORM::forTable('srps_users')->create();
            $user->username = '';
            $user->firstname = '';
            $user->lastname = '';
            $user->role = 'ROLE_ORGANISER';
            $user->is_active = 1;
        }

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

        
        // display form
        $this->View('user/edit.html.twig', array(
            'username' => $username,
            'user' => $user,
            'rolechoice' => $rolechoice,
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

