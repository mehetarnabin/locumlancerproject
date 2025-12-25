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
use App\Entity\Bookmark;

#[Route('/provider/application')]
class ApplicationController extends AbstractController
{
    #[Route('/risk-assessment', name: 'app_provider_risk_assessment')]
    public function riskAssessment(Request $request, EntityManagerInterface $em): Response
    {
        $provider = $this->getUser()->getProvider();

        if($request->getMethod() === 'POST') {
            $data = $request->request->all();
            $provider->setRiskAssessment($data);

            $em->persist($provider);
            $em->flush();

            if ($request->isXmlHttpRequest()) {
                return $this->json([
                    'status' => 'success',
                    'riskAssessment' => $provider->getRiskAssessment(),
                    'continue' => $request->get('save_continue') == 1
                ]);
            }
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
            $data = $request->request->all();
            $provider->setHealthAssessment($data);

            $em->persist($provider);
            $em->flush();

            if ($request->isXmlHttpRequest()) {
                return $this->json([
                    'status' => 'success',
                    'healthAssessment' => $provider->getHealthAssessment(),
                    'continue' => $request->get('save_continue') == 1
                ]);
            }
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

            if ($request->isXmlHttpRequest()) {
                return $this->json(['status' => 'success']);
            }
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

            // Use raw SQL to properly escape the rank column name (MySQL reserved keyword)
            // Detach entity first to prevent Doctrine listeners from interfering
            $em->detach($application);
            
            $connection = $em->getConnection();
            $now = (new \DateTime())->format('Y-m-d H:i:s');
            $idBinary = $applicationId->toBinary();
            $rankStr = (string)$rank;
            
            // Get the actual PDO connection from Doctrine's connection wrapper
            // We need to go through multiple layers to get the raw PDO instance
            $wrappedConnection = $connection->getWrappedConnection();
            
            // Handle different connection wrapper types
            if (method_exists($wrappedConnection, 'getWrappedConnection')) {
                $pdo = $wrappedConnection->getWrappedConnection();
            } elseif ($wrappedConnection instanceof \PDO) {
                $pdo = $wrappedConnection;
            } else {
                // Fallback: try to get native connection
                if (method_exists($connection, 'getNativeConnection')) {
                    $pdo = $connection->getNativeConnection();
                } else {
                    // Last resort: use connection params to create new PDO
                    $params = $connection->getParams();
                    $dsn = sprintf(
                        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                        $params['host'] ?? 'localhost',
                        $params['port'] ?? 3306,
                        $params['dbname'] ?? ''
                    );
                    $pdo = new \PDO($dsn, $params['user'] ?? '', $params['password'] ?? '');
                    $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
                }
            }
            
            // Ensure we have a PDO instance
            if (!$pdo instanceof \PDO) {
                throw new \RuntimeException('Could not obtain PDO connection');
            }
            
            // Execute raw SQL with backticks using PDO directly
            // Backticks MUST be preserved - this bypasses all Doctrine processing
            $sql = "UPDATE `b_application` SET `rank` = :rank_val, `updated_at` = :updated_at WHERE `id` = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':rank_val', $rankStr, \PDO::PARAM_STR);
            $stmt->bindValue(':updated_at', $now, \PDO::PARAM_STR);
            $stmt->bindValue(':id', $idBinary, \PDO::PARAM_STR);
            $stmt->execute();
            
            // Clear the entity manager to ensure fresh data on next fetch
            $em->clear();
            
            // Re-fetch the entity to get updated values
            $application = $em->getRepository(Application::class)->find($applicationId);

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

            $user = $this->getUser();
            if (!$user) {
                return $this->json([
                    'success' => false,
                    'message' => 'User not authenticated.'
                ], 401);
            }
            
            $provider = $user->getProvider();
            if (!$provider) {
                return $this->json([
                    'success' => false,
                    'message' => 'Provider not found.'
                ], 400);
            }
            
            $archivedCount = 0;
            $skippedCount = 0;
            $applicationRepo = $em->getRepository(Application::class);
            $bookmarkRepo = $em->getRepository(Bookmark::class);

            foreach ($data['ids'] as $appIdStr) {
                try {
                    $applicationId = Uuid::fromString($appIdStr);
                    $application = $applicationRepo->find($applicationId);

                    if (!$application) {
                        $skippedCount++;
                        continue;
                    }

                    // Verify the application belongs to the current user's provider
                    if ($application->getProvider() !== $provider) {
                        $skippedCount++;
                        continue;
                    }

                    // Archive the application using the entity method
                    if (!$application->isArchived()) {
                        $application->archive();
                        $em->persist($application);
                        $archivedCount++;
                    } else {
                        // Already archived, skip
                        $skippedCount++;
                    }
                    
                    // Also ensure bookmark exists and is removed (if it exists)
                    // This ensures consistency with saved section behavior
                    $job = $application->getJob();
                    if ($job) {
                        $bookmark = $bookmarkRepo->findOneBy([
                            'job' => $job,
                            'user' => $user
                        ]);
                        
                        if ($bookmark) {
                            // Remove bookmark since it's now archived via application
                            $em->remove($bookmark);
                        }
                    }
                } catch (\Throwable $e) {
                    // Invalid UUID or other issue, skip
                    $skippedCount++;
                    continue;
                }
            }

            $em->flush();

            // Build message based on results
            if ($archivedCount === 0) {
                $message = 'No applications were archived.';
                if ($skippedCount > 0) {
                    $message .= ' They may already be archived or not found.';
                }
            } else {
                $message = "$archivedCount application(s) archived successfully.";
                if ($archivedCount === 1) {
                    $message = "1 application archived successfully.";
                }
                $message .= " They will appear in your archived jobs section.";
            }

            return $this->json([
                'success' => $archivedCount > 0,
                'message' => $message,
                'archived_count' => $archivedCount
            ]);
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
                
                if ($request->isXmlHttpRequest()) {
                    return new JsonResponse(['success' => false, 'message' => 'Provider not found.'], 401);
                }
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
                
                if ($request->isXmlHttpRequest()) {
                    return new JsonResponse(['success' => false, 'message' => 'No applications found to delete or you do not have permission to delete them.'], 404);
                }
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
            
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse([
                    'success' => true,
                    'message' => sprintf('Successfully removed %d application(s) from your list.', $count)
                ]);
            }
            
            $this->addFlash('success', sprintf('Successfully removed %d application(s) from your list.', $count));

        } catch (\Exception $e) {
            $debugLog .= "EXCEPTION: " . $e->getMessage() . "\n";
            $debugLog .= "TRACE: " . $e->getTraceAsString() . "\n";
            file_put_contents('delete_debug.log', $debugLog, FILE_APPEND);
            
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(['success' => false, 'message' => 'An error occurred while deleting applications.'], 500);
            }
            
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

    #[Route('/bookmarks/archive', name: 'app_provider_bookmarks_archive', methods: ['POST'])]
    public function archiveBookmarks(Request $request, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        
        if (!$user) {
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(['success' => false, 'message' => 'User not found.'], 401);
            }
            $this->addFlash('error', 'User not found.');
            return $this->redirectToRoute('app_provider_jobs_saved');
        }

        $data = json_decode($request->getContent(), true);
        $bookmarkIds = $data['bookmark_ids'] ?? [];

        if (empty($bookmarkIds)) {
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(['success' => false, 'message' => 'No saved jobs selected for archiving.'], 400);
            }
            $this->addFlash('error', 'No saved jobs selected for archiving.');
            return $this->redirectToRoute('app_provider_jobs_saved');
        }

        try {
            // Convert string UUIDs to binary format
            $uuidBinaries = array_map(function($id) {
                return Uuid::fromString($id)->toBinary();
            }, $bookmarkIds);

            $bookmarkRepository = $em->getRepository(Bookmark::class);

            // Find bookmarks belonging to the user
            $bookmarks = $bookmarkRepository->findBy([
                'id' => $uuidBinaries,
                'user' => $user
            ]);

            if (empty($bookmarks)) {
                if ($request->isXmlHttpRequest()) {
                    return new JsonResponse(['success' => false, 'message' => 'No bookmarks found to archive.'], 404);
                }
                $this->addFlash('error', 'No bookmarks found to archive.');
                return $this->redirectToRoute('app_provider_jobs_saved');
            }

            // Archive related applications if they exist, or create and archive one if not
            $applicationRepo = $em->getRepository(Application::class);
            $provider = $user->getProvider();
            $archivedCount = 0;
            
            foreach ($bookmarks as $bookmark) {
                $job = $bookmark->getJob();
                if ($job) {
                    // Check if there's an application for this job by this user
                    $application = $applicationRepo->findOneBy([
                        'job' => $job,
                        'provider' => $provider
                    ]);
                    
                    if ($application) {
                        // Archive existing application if not already archived
                        if (!$application->isArchived()) {
                            $application->archive();
                            $em->persist($application);
                            $archivedCount++;
                        }
                    } else {
                        // Create a new application with 'saved' status and immediately archive it
                        // This ensures the job appears in archived jobs section
                        $application = new Application();
                        $application->setJob($job);
                        $application->setProvider($provider);
                        $application->setEmployer($job->getEmployer());
                        $application->setStatus('saved');
                        $application->setRank($bookmark->getRank()); // Preserve the rank if any
                        
                        // Immediately archive it
                        $application->archive();
                        $em->persist($application);
                        $archivedCount++;
                    }
                }
                
                // Remove the bookmark (it will appear in archived jobs via the archived application)
                $em->remove($bookmark);
            }

            $em->flush();

            $count = count($bookmarks);
            $message = "$count job(s) archived successfully. They will appear in your archived jobs section.";
                
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse([
                    'success' => true,
                    'message' => $message
                ]);
            }

            $this->addFlash('success', $message);
            return $this->redirectToRoute('app_provider_jobs_saved');

        } catch (\Exception $e) {
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Error archiving jobs: ' . $e->getMessage()
                ], 500);
            }

            $this->addFlash('error', 'Error archiving jobs: ' . $e->getMessage());
            return $this->redirectToRoute('app_provider_jobs_saved');
        }
    }

    #[Route('/bookmarks/delete', name: 'app_provider_bookmarks_delete', methods: ['POST'])]
    public function deleteBookmarks(Request $request, EntityManagerInterface $em): Response
    {
        $debugLog = "=== DELETE BOOKMARKS DEBUG ===\n";
        $debugLog .= "Time: " . date('Y-m-d H:i:s') . "\n";

        // Accept both form-encoded (comma-separated or JSON string) and raw JSON body
        $bookmarkIdsRaw = $request->request->get('bookmark_ids');
        $debugLog .= "Raw bookmark_ids (form): " . ($bookmarkIdsRaw ?? 'null') . "\n";

        $bookmarkIds = [];
        if ($bookmarkIdsRaw !== null) {
            $decoded = json_decode($bookmarkIdsRaw, true);
            if (is_array($decoded)) {
                $bookmarkIds = $decoded;
                $debugLog .= "Parsed IDs from JSON string in form.\n";
            } else {
                // Fallback: comma-separated string
                $maybeList = array_filter(array_map('trim', explode(',', $bookmarkIdsRaw)));
                if (!empty($maybeList)) {
                    $bookmarkIds = $maybeList;
                    $debugLog .= "Parsed IDs from comma-separated string in form.\n";
                }
            }
        }

        if (empty($bookmarkIds)) {
            $jsonBody = json_decode($request->getContent(), true);
            $debugLog .= "Raw request body JSON: " . print_r($jsonBody, true) . "\n";
            if (is_array($jsonBody) && isset($jsonBody['bookmark_ids']) && is_array($jsonBody['bookmark_ids'])) {
                $bookmarkIds = $jsonBody['bookmark_ids'];
                $debugLog .= "Parsed IDs from JSON request body.\n";
            }
        }

        $debugLog .= "Final bookmark IDs (pre-normalize): " . print_r($bookmarkIds, true) . "\n";

        // Normalize: ensure array of unique, non-empty strings
        if (!is_array($bookmarkIds)) {
            $bookmarkIds = [];
        }
        $bookmarkIds = array_values(array_filter(array_unique(array_map('strval', $bookmarkIds))));
        $debugLog .= "Normalized bookmark IDs: " . print_r($bookmarkIds, true) . "\n";

        if (empty($bookmarkIds)) {
            $debugLog .= "ERROR: No bookmarks selected for deletion\n";
            file_put_contents('delete_debug.log', $debugLog, FILE_APPEND);
            
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(['success' => false, 'message' => 'No saved jobs selected for deletion.'], 400);
            }
            $this->addFlash('error', 'No saved jobs selected for deletion.');
            return $this->redirectToRoute('app_provider_jobs_saved');
        }

        try {
            $user = $this->getUser();
            
            if (!$user) {
                $debugLog .= "ERROR: User not found\n";
                file_put_contents('delete_debug.log', $debugLog, FILE_APPEND);
                if ($request->isXmlHttpRequest()) {
                    return new JsonResponse(['success' => false, 'message' => 'User not found.'], 401);
                }
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
                if ($request->isXmlHttpRequest()) {
                    return new JsonResponse(['success' => false, 'message' => 'No saved jobs found to delete or you do not have permission to delete them.'], 404);
                }
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

            if ($request->isXmlHttpRequest()) {
                return new JsonResponse([
                    'success' => true,
                    'message' => sprintf('Successfully removed %d saved job(s) from your list.', $count),
                    'deleted' => $count
                ]);
            }

            $this->addFlash('success', sprintf('Successfully removed %d saved job(s) from your list.', $count));

        } catch (\Exception $e) {
            $debugLog .= "EXCEPTION: " . $e->getMessage() . "\n";
            $debugLog .= "TRACE: " . $e->getTraceAsString() . "\n";
            file_put_contents('delete_debug.log', $debugLog, FILE_APPEND);
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'An error occurred while deleting saved jobs.'
                ], 500);
            }
            $this->addFlash('error', 'An error occurred while deleting saved jobs.');
        }

        return $this->redirectToRoute('app_provider_jobs_saved');
    }

    #[Route('/{id}/messages', name: 'app_provider_application_messages', methods: ['GET'])]
    public function getApplicationMessages(
        Application $application,
        EntityManagerInterface $em
    ): JsonResponse {
        $user = $this->getUser();
        $provider = $user?->getProvider();

        // Security check
        if ($application->getProvider() !== $provider) {
            return $this->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        // Get all messages for this application
        // Use raw SQL with UNHEX for proper UUID comparison (primary strategy)
        $job = $application->getJob();
        $providerUser = $provider->getUser();
        
        $applicationIdStr = $application->getId()->toRfc4122();
        $jobIdStr = $job->getId()->toRfc4122();
        $providerUserIdStr = $providerUser->getId()->toRfc4122();
        
        $messages = [];
        $conn = $em->getConnection();
        
        // Use UNHEX to convert UUID strings to binary for MySQL comparison
        $sql = "
            SELECT m.id FROM b_message m 
            WHERE m.deleted = 0 
            AND m.is_draft = 0 
            AND (
                m.application_id = UNHEX(REPLACE(:applicationId, '-', '')) 
                OR (
                    m.job_uuid = UNHEX(REPLACE(:jobId, '-', '')) 
                    AND (
                        m.receiver_id = UNHEX(REPLACE(:receiverId, '-', '')) 
                        OR m.sender_id = UNHEX(REPLACE(:senderId, '-', ''))
                    )
                )
            )
            ORDER BY m.created_at ASC
        ";
        
        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery([
            'applicationId' => $applicationIdStr,
            'jobId' => $jobIdStr,
            'receiverId' => $providerUserIdStr,
            'senderId' => $providerUserIdStr
        ]);
        
        $rawMessageIds = $result->fetchFirstColumn();
        
        // Convert binary UUIDs to Uuid objects and load entities
        if (!empty($rawMessageIds)) {
            foreach ($rawMessageIds as $binaryId) {
                try {
                    // Convert binary UUID to Uuid object
                    $uuid = \Symfony\Component\Uid\Uuid::fromBinary($binaryId);
                    $msg = $em->getRepository(\App\Entity\Message::class)->find($uuid);
                    if ($msg) {
                        $messages[] = $msg;
                    }
                } catch (\Exception $e) {
                    // Skip invalid UUIDs
                    continue;
                }
            }
        }
        
        // Fallback: Try Doctrine query if raw SQL didn't work
        if (empty($messages)) {
            $messages = $em->getRepository(\App\Entity\Message::class)->createQueryBuilder('m')
                ->where('m.deleted = false')
                ->andWhere('m.isDraft = false')
                ->andWhere('m.application = :application')
                ->setParameter('application', $application)
                ->orderBy('m.createdAt', 'ASC')
                ->getQuery()
                ->getResult();
        }

        $messagesData = [];
        foreach ($messages as $msg) {
            // Only include messages where provider is the receiver (messages FROM employer TO provider)
            // or messages where provider is the sender (replies FROM provider TO employer)
            $isProviderReceiver = $msg->getReceiver() && $msg->getReceiver()->getId()->equals($providerUser->getId());
            $isProviderSender = $msg->getSender() && $msg->getSender()->getId()->equals($providerUser->getId());
            
            // Include the message if provider is involved (either as sender or receiver)
            if ($isProviderReceiver || $isProviderSender) {
                $messagesData[] = [
                    'id' => $msg->getId()->toRfc4122(),
                    'sender_id' => $msg->getSender()->getId()->toRfc4122(),
                    'sender_name' => $msg->getSender()->getName(),
                    'sender_email' => $msg->getSender()->getEmail(),
                    'receiver_id' => $msg->getReceiver() ? $msg->getReceiver()->getId()->toRfc4122() : null,
                    'receiver_name' => $msg->getReceiver() ? $msg->getReceiver()->getName() : null,
                    'subject' => $msg->getSubject(),
                    'text' => $msg->getText(),
                    'created_at' => $msg->getCreatedAt()->format('Y-m-d H:i:s'),
                    'is_seen' => $msg->isSeen(),
                ];
            }
        }

        // Mark messages as seen
        foreach ($messages as $msg) {
            if ($msg->getReceiver() && $msg->getReceiver()->getId()->equals($user->getId()) && !$msg->isSeen()) {
                $msg->setSeen(true);
                $em->persist($msg);
            }
        }
        $em->flush();

        return $this->json([
            'success' => true,
            'messages' => $messagesData
        ]);
    }

    #[Route('/{id}/reply-message', name: 'app_provider_application_reply_message', methods: ['POST'])]
    public function replyToMessage(
        Application $application,
        Request $request,
        EntityManagerInterface $em,
        \Symfony\Component\EventDispatcher\EventDispatcherInterface $dispatcher,
        \Symfony\Component\Mailer\MailerInterface $mailer
    ): JsonResponse {
        $user = $this->getUser();
        $provider = $user?->getProvider();

        // Security check
        if ($application->getProvider() !== $provider) {
            return $this->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $data = json_decode($request->getContent(), true) ?: [];
        $parentMessageId = $data['parent_message_id'] ?? null;
        $messageText = trim($data['message'] ?? '');

        if (empty($messageText)) {
            return $this->json(['success' => false, 'message' => 'Message is required'], 400);
        }

        try {
            // Get parent message
            $parentMessage = null;
            if ($parentMessageId) {
                $parentMessage = $em->getRepository(\App\Entity\Message::class)->find($parentMessageId);
                if (!$parentMessage || $parentMessage->getApplication() !== $application) {
                    return $this->json(['success' => false, 'message' => 'Parent message not found'], 404);
                }
            }

            // Get employer user
            $employer = $application->getEmployer();
            if (!$employer) {
                return $this->json(['success' => false, 'message' => 'Employer not found'], 404);
            }

            $employerUser = $employer->getUser();
            if (!$employerUser) {
                return $this->json(['success' => false, 'message' => 'Employer user not found'], 404);
            }

            // Create reply message
            $reply = new \App\Entity\Message();
            $reply->setSender($user);
            $reply->setReceiver($employerUser);
            $reply->setEmployer($employer);
            $reply->setJob($application->getJob());
            $reply->setApplication($application);
            $reply->setParent($parentMessage);
            $reply->setSubject($parentMessage ? ('Re: ' . $parentMessage->getSubject()) : 'Re: Message');
            $reply->setText($messageText);
            $reply->setIsDraft(false);
            $reply->setSentAt(new \DateTime());
            $reply->setSeen(false);

            $em->persist($reply);
            $em->flush();

            // Send email notification (non-blocking - don't fail if email fails)
            try {
                $dispatcher->dispatch(new \App\Event\MessageEvent($reply), \App\Event\MessageEvent::MESSAGE_CREATED);
                
                // Send email
                $employerEmail = $employerUser->getEmail();
                if ($employerEmail) {
                    $providerEmail = $user->getEmail() ?? 'notifications@locumlancer.com';
                    $senderName = $user->getName() ?: $user->getEmail() ?: 'Provider';
                    
                    // Try to use template, fallback to simple HTML if template doesn't exist
                    $emailBody = '';
                    try {
                        $emailBody = $this->renderView('emails/message_notification.html.twig', [
                            'subject' => $reply->getSubject(),
                            'message_text' => $messageText,
                            'sender_name' => $senderName,
                            'sender_email' => $providerEmail,
                            'has_attachment' => false,
                            'attachment_name' => null,
                        ]);
                    } catch (\Exception $templateException) {
                        // Fallback to simple HTML email if template doesn't exist
                        $emailBody = "
                            <html>
                            <body>
                                <h2>{$reply->getSubject()}</h2>
                                <p><strong>From:</strong> {$senderName} ({$providerEmail})</p>
                                <div style='padding: 15px; background: #f5f5f5; border-radius: 5px; margin: 15px 0;'>
                                    {$messageText}
                                </div>
                                <p><small>This message was sent via LocumLancer Platform</small></p>
                            </body>
                            </html>
                        ";
                    }
                    
                    $email = (new \Symfony\Component\Mime\Email())
                        ->from($providerEmail)
                        ->to($employerEmail)
                        ->subject($reply->getSubject() . ' - LocumLancer')
                        ->html($emailBody);
                    $mailer->send($email);
                }
            } catch (\Exception $emailException) {
                // Log email error but don't fail the request since message is already saved
                error_log('Email sending failed: ' . $emailException->getMessage());
            }

            return $this->json([
                'success' => true,
                'message' => 'Reply sent successfully',
                'message_id' => $reply->getId()->toRfc4122()
            ]);
        } catch (\Exception $e) {
            // Ensure we always return JSON, even for unexpected errors
            error_log('Error in replyToMessage: ' . $e->getMessage());
            error_log('Stack trace: ' . $e->getTraceAsString());
            
            return $this->json([
                'success' => false,
                'message' => 'Error sending reply: ' . $e->getMessage()
            ], 500);
        }
    }

}
