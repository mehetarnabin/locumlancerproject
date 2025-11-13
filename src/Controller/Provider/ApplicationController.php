<?php

namespace App\Controller\Provider;
use App\Entity\Application;
use App\Entity\Bookmark;
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

 #[Route('/delete', name: 'app_provider_applications_delete', methods: ['POST'])]
public function deleteApplications(
    Request $request, 
    EntityManagerInterface $em
): Response {
    $debugLog = "=== DELETE APPLICATIONS DEBUG ===\n";
    $debugLog .= "Time: " . date('Y-m-d H:i:s') . "\n";

    $applicationIdsJson = $request->request->get('application_ids');
    $debugLog .= "Raw application_ids: " . $applicationIdsJson . "\n";
    
    $applicationIds = json_decode($applicationIdsJson, true);
    $debugLog .= "Decoded application IDs: " . print_r($applicationIds, true) . "\n";

    if (empty($applicationIds)) {
        $debugLog .= "ERROR: No applications selected for deletion\n";
        file_put_contents('delete_debug.log', $debugLog, FILE_APPEND);
        $this->addFlash('error', 'No applications selected for deletion.');
        return $this->redirectToRoute('app_provider_jobs_applications');
    }

    try {
        $user = $this->getUser();
        $provider = $user->getProvider();
        
        if (!$provider) {
            $debugLog .= "ERROR: Provider not found for user\n";
            file_put_contents('delete_debug.log', $debugLog, FILE_APPEND);
            $this->addFlash('error', 'Provider not found.');
            return $this->redirectToRoute('app_provider_jobs_applications');
        }

        // Convert string UUIDs to binary format
        $uuidBinaries = array_map(function($id) {
            return Uuid::fromString($id)->toBinary();
        }, $applicationIds);

        $applicationRepository = $em->getRepository(Application::class);

        // Query applications owned by current provider
        $applications = $applicationRepository->findBy([
            'id' => $uuidBinaries,
            'provider' => $provider
        ]);

        $debugLog .= "Applications found with provider filter: " . count($applications) . "\n";

        if (empty($applications)) {
            $debugLog .= "ERROR: No applications found with provider filter\n";
            file_put_contents('delete_debug.log', $debugLog, FILE_APPEND);
            $this->addFlash('error', 'No applications found to delete or you do not have permission to delete them.');
            return $this->redirectToRoute('app_provider_jobs_applications');
        }

        $count = 0;
        foreach ($applications as $application) {
            $debugLog .= "Processing application ID: " . $application->getId()->toRfc4122() . "\n";
            
            // Delete ALL related records from ALL dependent tables
            $this->deleteAllApplicationDependencies($application, $em, $debugLog);
            
            $debugLog .= " - Deleting application\n";
            $em->remove($application);
            $count++;
        }

        $em->flush();

        $debugLog .= "SUCCESS: Deleted $count applications\n";
        file_put_contents('delete_debug.log', $debugLog, FILE_APPEND);
        $this->addFlash('success', sprintf('Successfully removed %d application(s) from your list.', $count));

    } catch (\Exception $e) {
        $debugLog .= "EXCEPTION: " . $e->getMessage() . "\n";
        $debugLog .= "TRACE: " . $e->getTraceAsString() . "\n";
        file_put_contents('delete_debug.log', $debugLog, FILE_APPEND);
        $this->addFlash('error', 'An error occurred while deleting applications.');
    }

    return $this->redirectToRoute('app_provider_jobs_applications');
}

private function deleteAllApplicationDependencies(Application $application, EntityManagerInterface $em, &$debugLog): void
{
    $connection = $em->getConnection();
    $applicationId = $application->getId()->toBinary();
    
    // ALL tables that reference b_application (from your SQL query)
    $dependentTables = [
        'b_document_request',
        'b_interview', 
        'b_review'
    ];
    
    // First handle Interview through Doctrine (since it's a OneToOne relationship)
    $interview = $application->getInterview();
    if ($interview) {
        $debugLog .= " - Removing interview: " . $interview->getId() . "\n";
        $application->setInterview(null);
        $em->remove($interview);
        $em->flush(); // Flush immediately
    }
    
    // Delete from ALL other tables using raw SQL
    foreach ($dependentTables as $table) {
        // Skip interview if already handled via Doctrine
        if ($table === 'b_interview' && $interview) {
            continue;
        }
        
        try {
            $deleteCount = $connection->executeStatement(
                "DELETE FROM $table WHERE application_id = ?",
                [$applicationId]
            );
            if ($deleteCount > 0) {
                $debugLog .= " - Deleted $deleteCount record(s) from $table\n";
            }
        } catch (\Exception $e) {
            $debugLog .= " - Note: Could not delete from $table: " . $e->getMessage() . "\n";
            // Continue with other tables even if one fails
        }
    }
}

#[Route('/bookmarks/delete', name: 'app_provider_bookmarks_delete', methods: ['POST'])]
public function deleteBookmarks(Request $request, EntityManagerInterface $em): Response
{
    $debugLog = "=== DELETE BOOKMARKS DEBUG ===\n";
    $debugLog .= "Time: " . date('Y-m-d H:i:s') . "\n";

    $bookmarkIdsJson = $request->request->get('bookmark_ids');
    $debugLog .= "Raw bookmark_ids: " . $bookmarkIdsJson . "\n";
    
    $bookmarkIds = json_decode($bookmarkIdsJson, true);
    $debugLog .= "Decoded bookmark IDs: " . print_r($bookmarkIds, true) . "\n";

    if (empty($bookmarkIds)) {
        $debugLog .= "ERROR: No bookmarks selected for deletion\n";
        file_put_contents('delete_debug.log', $debugLog, FILE_APPEND);
        $this->addFlash('error', 'No saved jobs selected for deletion.');
        return $this->redirectToRoute('app_provider_jobs_saved');
    }

    try {
        $user = $this->getUser();
        
        if (!$user) {
            $debugLog .= "ERROR: User not found\n";
            file_put_contents('delete_debug.log', $debugLog, FILE_APPEND);
            $this->addFlash('error', 'User not found.');
            return $this->redirectToRoute('app_provider_jobs_saved');
        }

        // Convert string UUIDs to binary format
        $uuidBinaries = array_map(function($id) use (&$debugLog) {
            $uuid = Uuid::fromString($id);
            $binary = $uuid->toBinary();
            $debugLog .= "Converted: $id -> " . bin2hex($binary) . "\n";
            return $binary;
        }, $bookmarkIds);

        $bookmarkRepository = $em->getRepository(Bookmark::class);

        // ✅ FIXED: Query by 'user' instead of 'provider'
        $bookmarks = $bookmarkRepository->findBy([
            'id' => $uuidBinaries,
            'user' => $user  // Changed from 'provider' to 'user'
        ]);

        $debugLog .= "Bookmarks found with user filter: " . count($bookmarks) . "\n";

        if (empty($bookmarks)) {
            $debugLog .= "ERROR: No bookmarks found with user filter\n";
            file_put_contents('delete_debug.log', $debugLog, FILE_APPEND);
            $this->addFlash('error', 'No saved jobs found to delete or you do not have permission to delete them.');
            return $this->redirectToRoute('app_provider_jobs_saved');
        }

        $count = 0;
        foreach ($bookmarks as $bookmark) {
            $debugLog .= "Deleting bookmark ID: " . $bookmark->getId()->toRfc4122() . " for job: " . $bookmark->getJob()->getTitle() . "\n";
            $em->remove($bookmark);
            $count++;
        }

        $em->flush();

        $debugLog .= "SUCCESS: Deleted $count bookmarks\n";
        file_put_contents('delete_debug.log', $debugLog, FILE_APPEND);
        $this->addFlash('success', sprintf('Successfully removed %d saved job(s) from your list.', $count));

    } catch (\Exception $e) {
        $debugLog .= "EXCEPTION: " . $e->getMessage() . "\n";
        $debugLog .= "TRACE: " . $e->getTraceAsString() . "\n";
        file_put_contents('delete_debug.log', $debugLog, FILE_APPEND);
        $this->addFlash('error', 'An error occurred while deleting saved jobs.');
    }

    return $this->redirectToRoute('app_provider_jobs_saved');
}



}