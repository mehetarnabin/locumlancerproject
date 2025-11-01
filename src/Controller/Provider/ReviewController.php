<?php

namespace App\Controller\Provider;

use App\Repository\CashbackRepository;
use App\Repository\ReviewRepository;
use Doctrine\ORM\EntityManagerInterface;
use Pagerfanta\Doctrine\ORM\QueryAdapter;
use Pagerfanta\Pagerfanta;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/provider')]
class ReviewController extends AbstractController
{
    #[Route('/reviews', name: 'app_provider_reviews')]
    public function index(Request $request, ReviewRepository $reviewRepository, EntityManagerInterface $em): Response
    {
        $provider = $this->getUser()->getProvider();
        $rating = $request->query->get('rating');
        $date = $request->query->get('date');

        // Build query for reviews
        $qb = $reviewRepository->createQueryBuilder('r')
            ->where('r.provider = :provider')
            ->andWhere('r.reviewedBy = :reviewedBy')
            ->setParameter('provider', $provider->getId(), UuidType::NAME)
            ->setParameter('reviewedBy', 'EMPLOYER');

        if ($rating) {
            $qb->andWhere('r.point = :rating')->setParameter('rating', $rating);
        }
        if ($date) {
            $qb->andWhere('DATE(r.createdAt) = :date')->setParameter('date', new \DateTime($date));
        }

        $qb->orderBy('r.createdAt', 'DESC');

        // Setup Pagerfanta
        $adapter = new QueryAdapter($qb);
        $pagerfanta = new Pagerfanta($adapter);
        $pagerfanta->setMaxPerPage(12); // Change this per your need
        $pagerfanta->setCurrentPage($request->query->get('page', 1));

        return $this->render('provider/review/index.html.twig', [
            'pager' => $pagerfanta,
            'provider' => $provider,
        ]);
    }
}