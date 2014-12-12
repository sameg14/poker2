<?php

namespace SS\Bundle\GameBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class DefaultController extends Controller
{
    public function indexAction($name)
    {
        return $this->render('SSGameBundle:Default:index.html.twig', array('name' => $name));
    }
}
