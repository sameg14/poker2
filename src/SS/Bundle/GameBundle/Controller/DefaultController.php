<?php

namespace SS\Bundle\GameBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DefaultController extends Controller
{
    /**
     * Show list of active games a user can join
     * @return Response
     */
    public function indexAction()
    {
        return $this->render('SSGameBundle:Default:index.html.twig');
    }
}
