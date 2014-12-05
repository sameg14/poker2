<?php

namespace SS\PokerBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class DefaultController extends Controller
{
    public function indexAction()
    {
        return $this->render('SSPokerBundle:Default:index.html.twig');
    }
}
