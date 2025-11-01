<?php

namespace App\Controller\Employer;

use App\Repository\CashbackRepository;
use App\Repository\ReviewRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/employer')]
class ReviewController extends AbstractController
{
    #[Route('/reviews', name: 'app_employer_reviews')]
    public function index(ReviewRepository $reviewRepository): Response
    {
        $employer = $this->getUser()->getEmployer();
        $reviews = $reviewRepository->findBy([
            'employer' => $employer,
            'reviewedBy' => 'PROVIDER'
        ], ['createdAt' => 'DESC']);

        return $this->render('employer/review/index.html.twig', [
            'reviews' => $reviews,
            'employer' => $employer,
        ]);
    }
}