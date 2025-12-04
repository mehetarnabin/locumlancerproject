<?php

namespace App\Controller\Provider;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/provider')]
class RecruiterConnectController extends AbstractController
{
    #[Route('/recruiter-connect', name: 'app_provider_recruiter_connect')]
    public function index(): Response
    {
        return $this->render('provider/recruiter_connect/index.html.twig', [
            // Placeholder for future recruiter packages.
            'packages' => [],
        ]);
    }
}


