<?php

namespace App\Controller\Provider;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/provider')]
class FaqController extends AbstractController
{
    #[Route('/faq', name: 'app_provider_faq')]
    public function index(): Response
    {
        return $this->render('provider/faq/index.html.twig', []);
    }
}