<?php

namespace App\Controller\Recruiter;

use App\Repository\CashbackRepository;
use App\Repository\ReviewRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/recruiter')]
class ReviewController extends AbstractController
{
    #[Route('/reviews', name: 'app_recruiter_reviews')]
    public function index(ReviewRepository $reviewRepository): Response
    {
        $employer = $this->getUser()->getEmployer();
        $receivedReviews = $reviewRepository->findBy([
            'employer' => $employer,
            'reviewedBy' => 'PROVIDER'
        ], ['createdAt' => 'DESC']);
        $writtenReviews = $reviewRepository->findBy([
            'employer' => $employer,
            'reviewedBy' => 'EMPLOYER'
        ], ['createdAt' => 'DESC']);

        return $this->render('recruiter/review/index.html.twig', [
            'reviews' => $receivedReviews,
            'writtenReviews' => $writtenReviews,
            'employer' => $employer,
        ]);
    }
}
