<?php

namespace App\Controller\Admin;

use App\Entity\Application;
use App\Entity\DocumentRequest;
use App\Entity\Job;
use App\Entity\Review;
use App\Event\ApplicationEvent;
use App\Event\ReviewEvent;
use App\Repository\ApplicationRepository;
use App\Repository\EmployerRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/admin/applications')]
class ApplicationController extends AbstractController
{
    #[Route('/', name: 'app_admin_applications')]
    public function index(EntityManagerInterface $em, Request $request): Response
    {
        $employer = $this->getUser()->getEmployer();
        $offset = $request->query->get('page', 1);
        $perPage = $request->get('per_page', 25);
        $filters = $request->query->all();

        $applications = $em->getRepository(Application::class)->getAll($offset, $perPage, $filters);
        $statusCounts = $em->getRepository(Application::class)->getApplicationStatusCounts();

        $query = $em->createQuery('SELECT COUNT(a.id) FROM App\Entity\Application a');
        $totalApplications = $query->getSingleScalarResult();

        return $this->render('admin/application/index.html.twig', [
            'applications' => $applications,
            'statusCounts' => $statusCounts,
            'totalApplications' => $totalApplications,
        ]);
    }
}