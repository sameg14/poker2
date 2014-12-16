<?php

namespace SS\Bundle\GameBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class AuthController handles some authentication related actions
 *
 * @package SS\Bundle\GameBundle\Controller
 */
class AuthController extends Controller
{

    public function saveFacebookUserAction()
    {
        $request = new Request();
        $response = new Response(json_encode(array('name' => '')));
        $response->headers->set('Content-Type', 'application/json');

        return $response;
    }

    public function saveAccessTokenAction()
    {

    }
}