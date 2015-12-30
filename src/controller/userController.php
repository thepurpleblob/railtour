<?php

namespace thepurpleblob\railtour\controller;

use thepurpleblob\core\coreController;

class UserController extends coreController
{
    
    public function homeAction()
    {
         return $this->render('SRPSBookingBundle:User:home.html.twig', array(
        ));       
    }
    
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
        $em = $this->getDoctrine()->getManager();

        // Get all our users
        $users = $em->getRepository('SRPSBookingBundle:User')
            ->findAll();

        return $this->render('SRPSBookingBundle:User:index.html.twig', array(
            'users' => $users,
        ));
    }
    
    public function editAction($username, Request $request) {
        
        $em = $this->getDoctrine()->getManager();
        
        // find the user
        $user = $em->getRepository('SRPSBookingBundle:User')
            ->findOneBy(array('username'=>$username));  
        if (!$user) {
            throw new \Exception("User $username not found in db");
        }
        
        // Create form
        $usertype = new UserType(true);
        $form = $this->createForm($usertype, $user);
        
        // submitted?
        $form->handleRequest($request);
        if ($form->isValid()) {

            $em->persist($user);
            $em->flush();

            return $this->redirect($this->generateUrl('admin_user_index'));
        }
        
        // display form
        return $this->render('SRPSBookingBundle:User:edit.html.twig', array(
            'user' => $user,
            'form' => $form->createView(),
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

