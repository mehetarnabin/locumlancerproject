<?php

namespace App\Controller\Admin;

use App\Entity\Application;
use App\Entity\DocumentRequest;
use App\Entity\Education;
use App\Entity\Experience;
use App\Entity\Insurance;
use App\Entity\Job;
use App\Entity\Review;
use App\Entity\User;
use App\Repository\JobReportRepository;
use App\Repository\JobRepository;
use Doctrine\ORM\EntityManagerInterface;
use Omines\DataTablesBundle\Adapter\Doctrine\ORMAdapter;
use Omines\DataTablesBundle\Column\BoolColumn;
use Omines\DataTablesBundle\Column\DateTimeColumn;
use Omines\DataTablesBundle\Column\NumberColumn;
use Omines\DataTablesBundle\Column\TextColumn;
use Omines\DataTablesBundle\Column\TwigStringColumn;
use Omines\DataTablesBundle\DataTable;
use Omines\DataTablesBundle\DataTableFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Workflow\Registry;

#[Route('/admin/jobs')]
class JobController extends AbstractController
{
//    #[Route('/', name: 'app_admin_jobs')]
//    public function index(Request $request, JobRepository $jobRepository): Response
//    {
//        $filters = $request->query->all();
//        $offset = $request->query->get('page', 1);
//        $perPage = $request->get('per_page', 25);
//
//        $jobs = $jobRepository->getAll($offset, $perPage, $filters);
//
//        return $this->render('admin/job/index.html.twig', ['jobs' => $jobs]);
//    }

    #[Route('/', name: 'app_admin_jobs')]
    public function index(Request $request, DataTableFactory $dataTableFactory): Response
    {
        $table = $dataTableFactory->create()
            ->add('title', TwigStringColumn::class, [
                'label' => 'Title',
                'template' => '<a href="{{ url(\'app_admin_job_detail\', {id: row.id}) }}">{{ row.title }}</a>',
            ])
            ->add('employer', TextColumn::class, ['field' => 'employer.name', 'label' => 'Employer'])
            ->add('status', TextColumn::class, ['label' => 'Status'])
            ->add('blocked', BoolColumn::class, [
                'label' => 'Blocked',
                'render' => function ($value) {
                    return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'YES' : 'NO';
                },
            ])
            ->add('verified', BoolColumn::class, [
                'label' => 'Verified',
                'render' => function ($value) {
                    return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'YES' : 'NO';
                },
            ])
            ->add('createdAt', DateTimeColumn::class, ['format' => 'm/d/Y', 'label' => 'Created At', 'searchable' => false])
            ->addOrderBy('createdAt', DataTable::SORT_DESCENDING)
            ->createAdapter(ORMAdapter::class, [
                'entity' => Job::class,
            ])
//            ->add('Actions', TextColumn::class, [
//                'label' => 'Actions',
//                'render' => function($value, $context) {
//                    $linkShow = sprintf(
//                        '<a href="%s" class="" title="Details"><img src="%s" class="icon-image" /></a>',
//                        $this->generateUrl('app_admin_job_detail', ['id' => $context->getId()]),
//                        '/assets/icons/transparency.png'
//                    );

//                    $isBlocked = $context->isBlocked();
//
//                    if ($isBlocked !== true) {
//                        $linkBlock = sprintf(
//                            '<a href="%s" class="" title="Block" onclick="return confirm(\'Are you sure? Do you want to block this job?\')"><img src="%s" class="icon-image" /></a>',
//                            $this->generateUrl('app_admin_job_block', ['id' => $context->getId()]),
//                            '/assets/icons/prohibition.png'
//                        );
//                    }else{
//                        $linkBlock = sprintf(
//                            '<a href="%s" class="" title="Unblock" onclick="return confirm(\'Are you sure? Do you want to unblock this job?\')"><img src="%s" class="icon-image" /></a>',
//                            $this->generateUrl('app_admin_job_block', ['id' => $context->getId()]),
//                            '/assets/icons/unlocked.png'
//                        );
//                    }

//                    return $linkShow.' &nbsp; '.$linkBlock;
//                    return $linkShow;
//                }])
            ->handleRequest($request)
        ;

        if ($table->isCallback()) {
            return $table->getResponse();
        }

        return $this->render('admin/job/index.html.twig', ['datatable' => $table]);
    }

    #[Route('/{id}/detail', name: 'app_admin_job_detail')]
    public function show(Job $job, JobReportRepository $jobReportRepository)
    {
        $jobReports = $jobReportRepository->findBy(['job' => $job], ['id' => 'DESC']);

        return $this->render('admin/job/show.html.twig', [
            'job' => $job,
            'jobReports' => $jobReports,
        ]);
    }

    #[Route('/{id}/block', name: 'app_admin_job_block')]
    public function block(Job $job, EntityManagerInterface $em)
    {
        if($job->isBlocked()) {
            $this->addFlash('success', 'Job unblocked successfully.');
            $job->setBlocked(false);
        }else{
            $this->addFlash('success', 'Job blocked successfully.');
            $job->setBlocked(true);
        }

        $em->persist($job);
        $em->flush();

        return $this->redirectToRoute('app_admin_job_detail', ['id' => $job->getId()]);
    }

    #[Route('/{id}/verify', name: 'app_admin_job_verify')]
    public function verify(Job $job, EntityManagerInterface $em)
    {
        if($job->isVerified()) {
            $this->addFlash('success', 'Job unverified successfully.');
            $job->setVerified(false);
        }else{
            $this->addFlash('success', 'Job verified successfully.');
            $job->setVerified(true);
        }

        $em->persist($job);
        $em->flush();

        return $this->redirectToRoute('app_admin_job_detail', ['id' => $job->getId()]);
    }

    #[Route('/{id}/applications', name: 'app_admin_job_applications', methods: ['GET'])]
    public function applications(
        Job $job,
        Request $request,
        EntityManagerInterface $em,
        Registry $workflowRegistry
    ): Response
    {
        $application = $em->getRepository(Application::class)->findOneBy(['job' => $job], ['id' => 'DESC']);
        if($request->get('applicationId')) {
            $application = $em->getRepository(Application::class)->find($request->get('applicationId'));
        }

        if(!$application){
            return $this->render('admin/job/applications-empty.html.twig', ['job' => $job,]);
        }

        $provider = $application->getProvider();

        $user = $provider->getUser();

        $educations = $em->getRepository(Education::class)->findBy(['user' => $user]);
        $experiences = $em->getRepository(Experience::class)->findBy(['user' => $user]);
        $insurances = $em->getRepository(Insurance::class)->findBy(['user' => $user]);
        $review = $em->getRepository(Review::class)->findOneBy(['application' => $application, 'provider' => $provider]);

        $documentRequests = $em->getRepository(DocumentRequest::class)->findBy(['provider' => $application->getProvider(), 'application' => $application]);

        $applications = $em->getRepository(Application::class)->findBy(['job' => $job], ['id' => 'DESC']);

        if(count($applications) > 0) {
            $workflow = $workflowRegistry->get(reset($applications), 'job_application_workflow');
        }

        $jobApplicationTransitions = [];
        foreach ($applications as $jobApplication) {
            $jobApplicationTransitions[$jobApplication->getId()->toString()] = array_map(fn($t) => $t->getName(), $workflow->getEnabledTransitions($jobApplication));
        }

        return $this->render('admin/job/applications.html.twig', [
            'job' => $job,
            'applicationDetail' => $application,
            'applications' => $applications,
            'educations' => $educations,
            'experiences' => $experiences,
            'insurances' => $insurances,
            'documentRequests' => $documentRequests,
            'user' => $user,
            'provider' => $provider,
            'review' => $review,
            'jobApplicationTransitions' => $jobApplicationTransitions,
        ]);
    }
}