<?php

namespace App\Controller\Employer;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/employer')]
class MembershipController extends AbstractController
{
    #[Route('/membership', name: 'app_employer_membership')]
    public function index(): Response
    {
        return $this->render('employer/membership/membership.html.twig', [
            'controller_name' => 'MembershipController',
        ]);
    }
}
