<?php

namespace thepurpleblob\railtour\controller;

use thepurpleblob\core\coreController;

class UserController extends coreController
{
    
    public function installAction() 
    {
        $em = $this->getDoctrine()->getManager();

        // Get all our users
        $users = $em->getRepository('SRPSBookingBundle:User')
            ->findAll();
        
        // if there are none, we will create the default admin user
        if (!$users) {
            $user = new User;
            $user->setFirstname('admin');
            $user->setLastname('admin');
            $user->setUsername('admin');
            $user->setPassword('admin');
            $user->setRole('ROLE_ADMIN');
            $em->persist($user);
            $em->flush();
        }
        
        // regardless go to users screen
        return $this->redirect($this->generateUrl('admin_user_index'));
    }
    
    public function indexAction()
    {
        $this->require_login('ROLE_ADMIN', 'user/index');

        // Get all our users
        $users = \ORM::forTable('srps_users')->findMany();

        return $this->View('user/index.html.twig', array(
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

        $this->View('user/login.html.twig', array(
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
    
    public function editAction($username) {

        $this->require_login('ROLE_ADMIN', 'user/index');

        // possible roles
        $rolechoice = array(
            'ROLE_ADMIN' => 'ROLE_ADMIN',
            'ROLE_ORGANISER' => 'ROLE_ORGANISER',
        );

        // find the user
        $user = \ORM::forTable('srps_users')->where('username', $username)->findOne();
        if (!$user) {
            throw new \Exception("User $username not found in db");
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
            $this->gump->validation_rules(array(
                'firstname' => 'required',
                'lastname' => 'required',
                'role' => 'required',
                'password' => 'required',
            ));

            if ($data = $this->gump->run($data)) {
                $user->firstname = $data['firstname'];
                $user->lastname = $data['lastname'];
                $user->role = $data['role'];
                $user->is_active = $data['is_active'];
                $user->save();
                $this->redirect('user/index');
            } else {
                $errors = $this->gump->get_readable_errors(false);
            }
        }

        
        // display form
        return $this->View('user/edit.html.twig', array(
            'user' => $user,
            'rolechoice' => $rolechoice,
            'errors' => $errors,
        ));        
    }

    public function newAction(Request $request) {
        
        $em = $this->getDoctrine()->getManager();
        
        // create empty user
        $user = new User();
        
        // Create form
        $usertype = new UserType(false);
        $form = $this->createForm($usertype, $user);
        
        // submitted?
        $form->handleRequest($request);
            if ($form->isValid()) {

                $em->persist($user);
                $em->flush();

                return $this->redirect($this->generateUrl('admin_user_index'));
            }
        
        // display form
        return $this->render('SRPSBookingBundle:User:new.html.twig', array(
            'user' => $user,
            'form' => $form->createView(),
        ));        
    }    
    
    public function deleteAction($username) {
        
        $em = $this->getDoctrine()->getManager();
        
        // check it isn't admin
        if ('admin'==$username) {
            throw new \Exception("may not delete primary admin");
        }
        
        // find the user
        $user = $em->getRepository('SRPSBookingBundle:User')
            ->findOneBy(array('username'=>$username));  
        if (!$user) {
            throw new \Exception("User $username not found in db");
        }
        
        // Delete the user
        $em->remove($user);
        $em->flush();

        return $this->redirect($this->generateUrl('admin_user_index'));
    }    
}

