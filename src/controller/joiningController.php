<?php

namespace thepurpleblob\railtour\controller;

use thepurpleblob\core\coreController;

/**
 * Joining controller.
 *
 */
class JoiningController extends coreController
{
    /**
     * Lists all Joining entities.
     *
     */
    public function indexAction($serviceid)
    {

        $service = $em->getRepository('SRPSBookingBundle:Service')
            ->find($serviceid);

        $joinings = $em->getRepository('SRPSBookingBundle:Joining')
            ->findByServiceid($serviceid);

        $pricebands = $em->getRepository('SRPSBookingBundle:Priceband')
            ->findByServiceid($serviceid);

        // add pricebandgroup names
        foreach ($joinings as $joining) {
            $pricebandgroup = $em->getRepository('SRPSBookingBundle:Pricebandgroup')
                ->find($joining->getPricebandgroupid());
            $joining->setPricebandname($pricebandgroup->getName());
        }

        // We just want to know if the pricebands are set up
        $setup = !empty($pricebands);

        return $this->render('SRPSBookingBundle:Joining:index.html.twig',
            array(
                'joinings' => $joinings,
                'service' => $service,
                'serviceid' => $serviceid,
                'setup' => $setup
                ));
    }

    /**
     * Displays a form to create a new Destination entity.
     */
    public function newAction($serviceid)
    {
        $joining = new Joining();
        $joining->setServiceid($serviceid);

        $em = $this->getDoctrine()->getManager();
        $service = $em->getRepository('SRPSBookingBundle:Service')
            ->find($serviceid);

        $pricebandgroups = $em->getRepository('SRPSBookingBundle:Pricebandgroup')
            ->findByServiceid($serviceid);

        $joiningtype = new JoiningType($pricebandgroups, $service);
        $form   = $this->createForm($joiningtype, $joining);

        return $this->render('SRPSBookingBundle:Joining:edit.html.twig', array(
            'joining' => $joining,
            'service' => $service,
            'serviceid' => $serviceid,
            'form'   => $form->createView(),
        ));
    }

    /**
     * Edits an existing Joining entity.
     */
    public function editAction($serviceid, $joiningid, Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        if ($joiningid) {
            $joining = $em->getRepository('SRPSBookingBundle:Joining')
                ->find($joiningid);
        } else {
            $joining = new Joining;
        }

        // Service
        $service = $em->getRepository('SRPSBookingBundle:Service')
            ->find($serviceid);
        $joining->setServiceid($serviceid);

        $pricebandgroups = $em->getRepository('SRPSBookingBundle:Pricebandgroup')
            ->findByServiceid($serviceid);

        $joiningtype = new JoiningType($pricebandgroups, $service);
        $editForm = $this->createForm($joiningtype, $joining);
        $editForm->handleRequest($request);

        if ($editForm->isValid()) {
            $joining->setCrs(strtoupper($joining->getCrs()));
            $em->persist($joining);
            $em->flush();

            return $this->redirect($this->generateUrl('admin_joining', array('serviceid' => $serviceid)));
        }

        return $this->render('SRPSBookingBundle:Joining:edit.html.twig', array(
            'joining' => $joining,
            'form' => $editForm->createView(),
            'service' => $service,
            'serviceid' => $serviceid,
        ));
    }

    /**
     * Deletes a Service entity.
     *
     */
    public function deleteAction($joiningid)
    {

        $em = $this->getDoctrine()->getManager();
        $joining = $em->getRepository('SRPSBookingBundle:Joining')
            ->find($joiningid);

        if ($joining) {
            $serviceid = $joining->getServiceid();
            $em->remove($joining);
            $em->flush();

            return $this->redirect($this->generateUrl('admin_joining', array('serviceid' => $serviceid)));
        }

        return $this->redirect($this->generateUrl('admin_service'));
    }
}
