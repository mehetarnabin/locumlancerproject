<?php

namespace App\Controller\Employer;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/employer')]
class FaqController extends AbstractController
{
    #[Route('/faq', name: 'app_employer_faq')]
    public function index(): Response
    {
        return $this->render('employer/faq/index.html.twig', []);
    }
}