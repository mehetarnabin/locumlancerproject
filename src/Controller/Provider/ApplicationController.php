<?php

namespace App\Controller\Provider;
use App\Entity\Application;
use App\Form\ProviderApplicationCertificationType;
use App\Form\ProviderReleaseAuthorizationType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Uid\Uuid;

#[Route('/provider/application')]
class ApplicationController extends AbstractController
{
    #[Route('/risk-assessment', name: 'app_provider_risk_assessment')]
    public function riskAssessment(Request $request, EntityManagerInterface $em): Response
    {
        $provider = $this->getUser()->getProvider();

        if($request->getMethod() === 'POST') {
            $provider->setRiskAssessment($request->get('riskAssessment'));

            $em->persist($provider);
            $em->flush();

            $this->addFlash('success', 'Risk assessment updated successfully.');

            if($request->get('save_continue') == 1){
                return $this->redirectToRoute('app_provider_application_certification');
            }

            return $this->redirectToRoute('app_provider_risk_assessment');
        }

        return $this->render('provider/profile/risk-assessment.html.twig', [
            'provider' => $provider,
            'riskAssessment' => $provider->getRiskAssessment(),
        ]);
    }

    #[Route('/health-assessment', name: 'app_provider_health_assessment')]
    public function healthAssessment(Request $request, EntityManagerInterface $em): Response
    {
        $provider = $this->getUser()->getProvider();

        if($request->getMethod() === 'POST') {
            $provider->setHealthAssessment($request->get('healthAssessment'));

            $em->persist($provider);
            $em->flush();

            $this->addFlash('success', 'Health assessment updated successfully.');

            if($request->get('save_continue') == 1){
                return $this->redirectToRoute('app_provider_risk_assessment');
            }

            return $this->redirectToRoute('app_provider_health_assessment');
        }

        return $this->render('provider/profile/health-assessment.html.twig', [
            'provider' => $provider,
            'healthAssessment' => $provider->getHealthAssessment(),
        ]);
    }

    #[Route('/application-certification', name: 'app_provider_application_certification')]
    public function applicationCertification(Request $request, EntityManagerInterface $em): Response
    {
        $provider = $this->getUser()->getProvider();
        $form = $this->createForm(ProviderApplicationCertificationType::class, $provider);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($provider);
            $em->flush();

            $this->addFlash('success', 'Application certification updated successfully.');

            if($request->get('save_continue') == 1){
                return $this->redirectToRoute('app_provider_release_authorization');
            }

            return $this->redirectToRoute('app_provider_application_certification');
        }

        return $this->render('provider/profile/application-certification.html.twig', [
            'provider' => $provider,
            'form' => $form,
        ]);
    }

    #[Route('/release-authorization', name: 'app_provider_release_authorization')]
    public function releaseAndAuthorization(Request $request, EntityManagerInterface $em): Response
    {
        $provider = $this->getUser()->getProvider();
        $form = $this->createForm(ProviderReleaseAuthorizationType::class, $provider);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($provider);
            $em->flush();

            $this->addFlash('success', 'Release and Authorization updated successfully.');
            return $this->redirectToRoute('app_provider_release_authorization');
        }

        return $this->render('provider/profile/release-authorization.html.twig', [
            'provider' => $provider,
            'form' => $form,
        ]);
    }

    #[Route('/update-rank', name: 'app_update_application_rank', methods: ['POST'])]
    public function updateRank(Request $request, EntityManagerInterface $em): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            if (!isset($data['applicationId'], $data['rank'])) {
                return $this->json([
                    'success' => false,
                    'message' => 'Invalid request data'
                ], 400);
            }

            $rank = floatval($data['rank']);
            if ($rank < 1) $rank = 1;
            if ($rank > 10) $rank = 10;

            $applicationId = Uuid::fromString($data['applicationId']);
            $application = $em->getRepository(Application::class)->find($applicationId);

            if (!$application) {
                return $this->json([
                    'success' => false,
                    'message' => 'Application not found'
                ], 404);
            }

            $application->setRank($rank);
            $em->flush();

            return $this->json([
                'success' => true,
                'message' => 'Rank updated successfully',
                'rank' => $application->getRank()
            ]);
        } catch (\Exception $e) {
            // Optional: Log exception here
            return $this->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }
    

   #[Route('/archive/bulk', name: 'app_provider_application_archive_bulk', methods: ['POST'])]
public function archiveApplicationsJobsBulk(Request $request, EntityManagerInterface $em): JsonResponse
{
    try {
        $data = json_decode($request->getContent(), true);

        if (empty($data['ids']) || !is_array($data['ids'])) {
            return $this->json([
                'success' => false,
                'message' => 'Please select at least one application to archive.'
            ], 400);
        }

        // Temporarily disabled to avoid archived column error
        return $this->json([
            'success' => true,
            'message' => "Archive functionality temporarily disabled."
        ]);
        
        /* Original code - disabled temporarily
        $archivedCount = 0;
        $applicationRepo = $em->getRepository(Application::class);

        foreach ($data['ids'] as $appIdStr) {
            $application = $applicationRepo->find($appIdStr); // ✅ no Uuid::fromString()

            if (!$application) {
                continue;
            }

            $job = $application->getJob();
            if (!$job || ($job->getArchived() ?? false)) {
                continue;
            }

            $job->setArchived(true);
            $em->persist($job);
            $archivedCount++;
        }

        $em->flush();

        return $this->json([
            'success' => true,
            'message' => "$archivedCount job(s) archived successfully."
        ]);
        */
    } catch (\Throwable $e) {
        return $this->json([
            'success' => false,
            'message' => 'Server error: ' . $e->getMessage(),
        ], 500);
    }
}



}