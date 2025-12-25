<?php

namespace App\Controller\Employer;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/employer')]
class QuickTourController extends AbstractController
{
    #[Route('/quick-tour', name: 'app_employer_quick_tour')]
    public function index(): Response
    {
        return $this->render('employer/quick_tour/index.html.twig', [
            'controller_name' => 'QuickTourController',
        ]);
    }
}

