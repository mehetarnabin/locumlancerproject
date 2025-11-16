<?php
// src/Repository/ProfileAnalyticsRepository.php

namespace App\Repository;

use App\Entity\ProfileAnalytics;
use App\Entity\Provider;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ProfileAnalyticsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProfileAnalytics::class);
    }

    public function getProviderMetrics(Provider $provider, \DateTimeInterface $startDate = null): array
    {
        $qb = $this->createQueryBuilder('pa')
            ->where('pa.viewedProvider = :provider')
            ->setParameter('provider', $provider);

        if ($startDate) {
            $qb->andWhere('pa.createdAt >= :startDate')
               ->setParameter('startDate', $startDate);
        }

        $results = $qb->getQuery()->getResult();

        $metrics = [
            'total_impressions' => 0,
            'profile_views' => 0,
            'resume_downloads' => 0,
            'search_impressions' => 0,
        ];

        foreach ($results as $analytic) {
            $metrics['total_impressions']++;
            
            switch ($analytic->getType()) {
                case 'profile_view':
                    $metrics['profile_views']++;
                    break;
                case 'resume_download':
                    $metrics['resume_downloads']++;
                    break;
                case 'search_impression':
                    $metrics['search_impressions']++;
                    break;
            }
        }

        return $metrics;
    }

    public function getMonthlyTrends(Provider $provider, int $months = 6): array
    {
        $endDate = new \DateTime();
        $startDate = (clone $endDate)->modify("-$months months");

        $qb = $this->createQueryBuilder('pa')
            ->select('MONTH(pa.createdAt) as month, YEAR(pa.createdAt) as year, pa.type, COUNT(pa.id) as count')
            ->where('pa.viewedProvider = :provider')
            ->andWhere('pa.createdAt >= :startDate')
            ->setParameter('provider', $provider)
            ->setParameter('startDate', $startDate)
            ->groupBy('year, month, pa.type')
            ->orderBy('year', 'ASC')
            ->addOrderBy('month', 'ASC');

        return $qb->getQuery()->getResult();
    }

    public function getLast30DaysMetrics(Provider $provider): array
    {
        $startDate = new \DateTime('-30 days');
        
        $qb = $this->createQueryBuilder('pa')
            ->select('DATE(pa.createdAt) as date, pa.type, COUNT(pa.id) as count')
            ->where('pa.viewedProvider = :provider')
            ->andWhere('pa.createdAt >= :startDate')
            ->setParameter('provider', $provider)
            ->setParameter('startDate', $startDate)
            ->groupBy('date, pa.type')
            ->orderBy('date', 'ASC');

        return $qb->getQuery()->getResult();
    }

    public function recordAnalyticsEvent(Provider $provider, ?User $viewer, string $type): void
    {
        $analytic = new ProfileAnalytics();
        $analytic->setViewedProvider($provider);
        $analytic->setViewer($viewer);
        $analytic->setType($type);
        $analytic->setIpAddress($_SERVER['REMOTE_ADDR'] ?? null);
        $analytic->setUserAgent($_SERVER['HTTP_USER_AGENT'] ?? null);

        $this->getEntityManager()->persist($analytic);
        $this->getEntityManager()->flush();
    }
}