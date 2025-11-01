<?php

namespace App\Controller\Admin;

use App\Entity\Notification;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route('/admin')]
class DashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'app_admin_dashboard')]
    public function index(Request $request, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        $totalProviders = $em->createQuery("SELECT count(p.id) as totalProviders FROM App\Entity\Provider p")->getSingleScalarResult();
        $totalEmployers = $em->createQuery("SELECT count(e.id) as totalEmployers FROM App\Entity\Employer e")->getSingleScalarResult();
        $totalJobs = $em->createQuery("SELECT count(j.id) as totalJobs FROM App\Entity\Job j")->getSingleScalarResult();
        $totalApplications = $em->createQuery("SELECT count(a.id) as totalApplications FROM App\Entity\Application a")->getSingleScalarResult();
        $notifications = $em->getRepository(Notification::class)->findBy(['userType' => User::TYPE_ADMIN], ['id' => 'DESC'], 5);

        return $this->render('admin/dashboard.html.twig', [
            'totalProviders' => $totalProviders,
            'totalEmployers' => $totalEmployers,
            'totalJobs' => $totalJobs,
            'totalApplications' => $totalApplications,
            'notifications' => $notifications,
        ]);
    }
}