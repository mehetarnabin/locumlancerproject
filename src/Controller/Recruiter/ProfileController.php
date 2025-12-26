<?php

namespace App\Controller\Recruiter;

use App\Entity\Document;
use App\Entity\Notification;
use App\Entity\Review;
use App\Entity\PackageSubscription;
use App\Form\ChangePasswordFormType;
use App\Form\UserProfileType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/recruiter/profile')]
class ProfileController extends AbstractController
{
    #[Route('/', name: 'app_recruiter_profile', methods: ['GET', 'POST'])]
    public function profile(
        Request $request,
        EntityManagerInterface $em,
        SluggerInterface $slugger,
        #[Autowire('%kernel.project_dir%/public/uploads')] string $uploadDirectory
    ): Response {
        /** @var \App\Entity\User $user */
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $recruiter = $user->getRecruiter();
        $employer = null; // Removed as we focus on recruiter

        if (!$recruiter) {
            throw $this->createAccessDeniedException('Recruiter profile not found.');
        }

        // Calculate average rating from reviews (via jobs -> employer reviews? OR recruiter specific?)
        // Recruiter rating is in Recruiter entity
        $averageRating = $recruiter->getRating();
        $averageRating = null;
        // Reviews count? 
        // Maybe fetch reviews where job.recruiter = this recruiter?
        // For now set to 0 or leave empty
        $reviewsCount = 0;

        // Get active membership/subscription
        $activeSubscription = $em->getRepository(PackageSubscription::class)->findOneBy(
            [
                'user' => $user,
                'status' => PackageSubscription::STATUS_ACTIVE
            ],
            ['endDate' => 'DESC']
        );
        $membershipLevel = null;
        if ($activeSubscription && $activeSubscription->getPackage()) {
            $membershipLevel = ucfirst($activeSubscription->getPackage()->getType());
        }

        // Calculate years active
        $yearsActive = 0;
        if ($user->getCreatedAt()) {
            $now = new \DateTime();
            $createdAt = $user->getCreatedAt();
            $diff = $now->diff($createdAt);
            $yearsActive = $diff->y;
        }

        // Count jobs posted (assigned to recruiter)
        $jobsCount = $recruiter->getJobRecruiters()->count();

        // Profile form for inline editing
        $profileForm = $this->createForm(UserProfileType::class, $user);
        $profileForm->handleRequest($request);

        if ($profileForm->isSubmitted() && $profileForm->isValid()) {
            /** @var \Symfony\Component\HttpFoundation\File\UploadedFile $profileFile */
            $profileFile = $profileForm->get('profilePictureFilename')->getData();

            if ($profileFile) {
                $userDir = $uploadDirectory . '/' . $user->getId();
                if (!is_dir($userDir)) mkdir($userDir, 0777, true);

                $safeFilename = $slugger->slug(pathinfo($profileFile->getClientOriginalName(), PATHINFO_FILENAME));
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $profileFile->guessExtension();

                try {
                    $profileFile->move($userDir, $newFilename);
                    $user->setProfilePictureFilename($newFilename);
                } catch (FileException $e) {
                    $this->addFlash('error', 'Profile picture upload failed: ' . $e->getMessage());
                }
            }

            // Update recruiter fields
            $recruiter->setCompanyName($request->get('companyName'));
            $recruiter->setSpeciality($request->get('speciality')); // Added speciality as it is in Recruiter entity

            // Update User fields
            $user->setName($request->get('contactPersonName'));
            $user->setEmail($request->get('contactEmail'));
            $user->setAddress($request->get('address'));
            $user->setPhone2($request->get('phone2'));
            // Website not supported in Recruiter/User entities currently

            // Update user gender field
            if ($request->get('gender')) {
                $user->setGender($request->get('gender'));
            }

            $em->persist($recruiter);
            $em->persist($user);
            $em->flush();

            $this->addFlash('success', 'Profile updated successfully.');

            // If AJAX request, return JSON response instead of redirect
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse([
                    'success' => true,
                    'message' => 'Profile updated successfully.',
                    'redirect' => $this->generateUrl('app_recruiter_profile')
                ]);
            }

            return $this->redirectToRoute('app_recruiter_profile');
        }

        // Get employer documents
        $documents = $em->getRepository(Document::class)->findBy(
            ['user' => $user],
            ['createdAt' => 'DESC']
        );

        return $this->render('recruiter/profile/profile.html.twig', [
            'user' => $user,
            'recruiter' => $recruiter,
            'profileForm' => $profileForm->createView(),
            'averageRating' => $averageRating,
            'membershipLevel' => $membershipLevel,
            'yearsActive' => $yearsActive,
            'jobsCount' => $jobsCount,
            'reviewsCount' => $reviewsCount,
            'documents' => $documents,
        ]);
    }

    #[Route('/update', name: 'app_recruiter_profile_update')]
    public function profileUpdate(
        Request $request,
        EntityManagerInterface $em,
        SluggerInterface $slugger,
        #[Autowire('%kernel.project_dir%/public/uploads')] string $uploadDirectory
    ): Response {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $recruiter = $user->getRecruiter();
        $form = $this->createForm(UserProfileType::class, $user);
        $form->remove('gender');
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $profilePictureFile = $form->get('profilePictureFilename')->getData();

            // this condition is needed because the 'brochure' field is not required
            // so the PDF file must be processed only when a file is uploaded
            if ($profilePictureFile) {
                $originalFilename = pathinfo($profilePictureFile->getClientOriginalName(), PATHINFO_FILENAME);
                // this is needed to safely include the file name as part of the URL
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $profilePictureFile->guessExtension();

                // Move the file to the directory where brochures are stored
                try {
                    $profilePictureFile->move($uploadDirectory . '/' . $user->getId(), $newFilename);
                } catch (FileException $e) {
                    // ... handle exception if something happens during file upload
                }

                $user->setProfilePictureFilename($newFilename);
            }

            $recruiter->setCompanyName($request->get('companyName'));
            // contactPersonName -> User name
            $user->setName($request->get('contactPersonName'));
            // contactEmail -> User email
            $user->setEmail($request->get('contactEmail'));

            $em->persist($recruiter);
            $em->persist($user);
            $em->flush();

            $this->addFlash('success', 'Profile updated successfully.');
            return $this->redirectToRoute('app_recruiter_profile');
        }

        return $this->render('recruiter/profile/profile-update.html.twig', [
            'user' => $user,
            'form' => $form,
            'recruiter' => $recruiter,
        ]);
    }

    #[Route('/change-password', name: 'app_recruiter_change_password', methods: ['GET', 'POST'])]
    public function changePassword(Request $request, UserPasswordHasherInterface $passwordEncoder, EntityManagerInterface $em): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        $form = $this->createForm(ChangePasswordFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $currentPassword = $form->get('current_password')->getData();
            $newPassword = $form->get('new_password')->getData();

            if (!$passwordEncoder->isPasswordValid($user, $currentPassword)) {
                $this->addFlash('error', 'The current password is incorrect.');
                return $this->redirectToRoute('app_recruiter_change_password');
            }

            $encodedPassword = $passwordEncoder->hashPassword($user, $newPassword);
            $user->setPassword($encodedPassword);

            $em->flush();

            $this->addFlash('success', 'Password changed successfully.');
            return $this->redirectToRoute('app_recruiter_profile');
        }

        return $this->render('recruiter/profile/change_password.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/photo/upload', name: 'app_recruiter_profile_photo_upload', methods: ['POST'])]
    public function uploadPhoto(
        Request $request,
        EntityManagerInterface $em,
        SluggerInterface $slugger,
        #[Autowire('%kernel.project_dir%/public/uploads')] string $uploadDirectory
    ): JsonResponse {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['success' => false, 'message' => 'Not authenticated'], 401);
        }

        /** @var UploadedFile|null $file */
        $file = $request->files->get('image')
            ?? $request->files->get('profileImage')
            ?? $request->files->get('profilePictureFilename');

        if (!$file) {
            return $this->json(['success' => false, 'message' => 'No image file provided'], 400);
        }

        $allowed = ['image/png', 'image/jpg', 'image/jpeg'];
        if (!in_array($file->getMimeType(), $allowed, true)) {
            return $this->json(['success' => false, 'message' => 'Invalid image type'], 400);
        }
        if ($file->getSize() > 8 * 1024 * 1024) {
            return $this->json(['success' => false, 'message' => 'Image size exceeds 8MB'], 400);
        }

        $userDir = $uploadDirectory . '/' . $user->getId();
        if (!is_dir($userDir)) {
            @mkdir($userDir, 0777, true);
        }

        $safeFilename = $slugger->slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
        $newFilename = $safeFilename . '-' . uniqid() . '.' . $file->guessExtension();

        try {
            $file->move($userDir, $newFilename);
        } catch (FileException $e) {
            return $this->json(['success' => false, 'message' => 'Upload failed'], 500);
        }

        $user->setProfilePictureFilename($newFilename);
        $em->persist($user);
        $em->flush();

        $url = '/uploads/' . $user->getId() . '/' . $newFilename;
        if ($request->isXmlHttpRequest()) {
            return $this->json(['success' => true, 'url' => $url]);
        }

        return $this->json(['success' => true, 'url' => $url]);
    }

    #[Route('/profile-picture-remove', name: 'app_recruiter_profile_picture_remove', methods: ['GET'])]
    public function profilePictureRemove(
        EntityManagerInterface $em,
        #[Autowire('%kernel.project_dir%/public/uploads')] string $uploadDirectory
    ) {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        $ppFilePath = $uploadDirectory . '/' . $user->getId() . '/' . $user->getProfilePictureFilename();
        if (file_exists($ppFilePath)) {
            unlink($ppFilePath);
        }

        $user->setProfilePicture(null);
        $user->setProfilePictureFilename(null);

        $em->persist($user);
        $em->flush();

        $this->addFlash('success', 'Profile picture removed successfully.');
        return $this->redirectToRoute('app_recruiter_profile');
    }

    #[Route('/documents/upload', name: 'app_recruiter_profile_documents_upload', methods: ['POST'])]
    public function uploadDocument(
        Request $request,
        EntityManagerInterface $em,
        SluggerInterface $slugger,
        #[Autowire('%kernel.project_dir%/public/uploads')] string $uploadDirectory
    ): JsonResponse {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['success' => false, 'message' => 'Not authenticated'], 401);
        }

        /** @var UploadedFile|null $file */
        $file = $request->files->get('document');

        if (!$file) {
            return $this->json(['success' => false, 'message' => 'No file provided'], 400);
        }

        $allowedMimes = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'image/jpeg', 'image/jpg', 'image/png'];
        if (!in_array($file->getMimeType(), $allowedMimes, true)) {
            return $this->json(['success' => false, 'message' => 'Invalid file type. Allowed: PDF, DOC, DOCX, JPG, PNG'], 400);
        }

        if ($file->getSize() > 10 * 1024 * 1024) {
            return $this->json(['success' => false, 'message' => 'File size exceeds 10MB'], 400);
        }

        $userDir = $uploadDirectory . '/' . $user->getId();
        if (!is_dir($userDir)) {
            @mkdir($userDir, 0777, true);
        }

        $safeFilename = $slugger->slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
        $newFilename = $safeFilename . '-' . uniqid() . '.' . $file->guessExtension();

        // Check if this is a replacement (edit)
        $replaceDocumentId = $request->request->get('replaceDocumentId');
        $document = null;

        if ($replaceDocumentId) {
            $document = $em->getRepository(Document::class)->find($replaceDocumentId);
            if ($document && $document->getUser() === $user) {
                // Delete old file
                $oldFilePath = $uploadDirectory . '/' . $user->getId() . '/' . $document->getFileName();
                if (is_file($oldFilePath)) {
                    @unlink($oldFilePath);
                }
            } else {
                $document = null; // Invalid document, create new
            }
        }

        if (!$document) {
            $document = new Document();
            $document->setUser($user);
            $document->setCategory('Company Documents');
        }

        try {
            $file->move($userDir, $newFilename);
        } catch (FileException $e) {
            return $this->json(['success' => false, 'message' => 'Upload failed: ' . $e->getMessage()], 500);
        }

        $document->setName($file->getClientOriginalName());
        $document->setFileName($newFilename);
        $document->setMimeType($file->getMimeType());

        $em->persist($document);
        $em->flush();

        // Get updated documents list
        $documents = $em->getRepository(Document::class)->findBy(
            ['user' => $user],
            ['createdAt' => 'DESC']
        );

        // Render document list HTML
        $documentsListHtml = $this->renderView('recruiter/profile/_documents_list.html.twig', [
            'documents' => $documents,
        ]);

        return $this->json([
            'success' => true,
            'message' => 'Document uploaded successfully.',
            'documentsListHtml' => $documentsListHtml,
        ]);
    }

    #[Route('/documents/delete/{id}', name: 'app_recruiter_profile_documents_delete', methods: ['POST'])]
    public function deleteDocument(
        Document $document,
        Request $request,
        EntityManagerInterface $em,
        #[Autowire('%kernel.project_dir%/public/uploads')] string $uploadDirectory
    ): JsonResponse {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        if (!$user || $document->getUser() !== $user) {
            return $this->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        if (!$this->isCsrfTokenValid('delete_document_' . $document->getId(), $request->request->get('_token'))) {
            return $this->json(['success' => false, 'message' => 'Invalid CSRF token'], 403);
        }

        $filePath = $uploadDirectory . '/' . $user->getId() . '/' . $document->getFileName();
        if (is_file($filePath)) {
            @unlink($filePath);
        }

        $em->remove($document);
        $em->flush();

        // Get updated documents list
        $documents = $em->getRepository(Document::class)->findBy(
            ['user' => $user],
            ['createdAt' => 'DESC']
        );

        // Render document list HTML
        $documentsListHtml = $this->renderView('recruiter/profile/_documents_list.html.twig', [
            'documents' => $documents,
        ]);

        return $this->json([
            'success' => true,
            'message' => 'Document deleted successfully.',
            'documentsListHtml' => $documentsListHtml,
        ]);
    }

    #[Route('/documents/list', name: 'app_recruiter_profile_documents_list', methods: ['GET'])]
    public function listDocuments(EntityManagerInterface $em): JsonResponse
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['success' => false, 'message' => 'Not authenticated'], 401);
        }

        $documents = $em->getRepository(Document::class)->findBy(
            ['user' => $user],
            ['createdAt' => 'DESC']
        );

        $data = array_map(function (Document $doc) {
            return [
                'id' => (string)$doc->getId(),
                'name' => $doc->getDisplayName(),
                'fileName' => $doc->getFileName(),
                'mimeType' => $doc->getMimeType(),
                'createdAt' => $doc->getCreatedAt()?->format('c'),
            ];
        }, $documents);

        return $this->json(['success' => true, 'documents' => $data]);
    }

    #[Route('/notification-update-preferences-single', name: 'app_recruiter_notification_update_preferences_single', methods: ['POST'])]
    public function updateNotificationPreferencesSingle(
        Request $request,
        EntityManagerInterface $em,
        CsrfTokenManagerInterface $csrfTokenManager
    ): Response {
        $recruiter = $this->getUser()->getRecruiter(); // Use recruiter instead of employer but Recruiter doesn't have Preferences field yet?
        // Check if Recruiter has NotificationPreferences. Entity showed NO field.
        // So this feature might be unavailable for Recruiters.
        return new JsonResponse(['message' => 'Preference update not supported for recruiters'], 400);

        $data = json_decode($request->getContent(), true);

        if (!$csrfTokenManager->isTokenValid(new CsrfToken('update_notification', $request->headers->get('X-CSRF-TOKEN')))) {
            return new JsonResponse(['message' => 'Invalid CSRF Token'], 400);
        }

        if (!isset($data['key'], $data['value'])) {
            return new JsonResponse(['message' => 'Invalid data'], 400);
        }

        $preferences = $employer->getNotificationPreferences() ?? [];

        $preferences[$data['key']] = (bool) $data['value'];

        // $recruiter->setNotificationPreferences($preferences);
        // $em->persist($recruiter);

        $em->flush();

        return new JsonResponse(['message' => 'Preference updated successfully']);
    }

    #[Route('/notification-update-preferences-all', name: 'app_recruiter_notification_update_preferences_all', methods: ['POST'])]
    public function updateNotificationPreferencesAll(
        Request $request,
        EntityManagerInterface $em,
        CsrfTokenManagerInterface $csrfTokenManager
    ): Response {
        $recruiter = $this->getUser()->getRecruiter(); // use recruiter

        $data = json_decode($request->getContent(), true);

        if (!$csrfTokenManager->isTokenValid(new CsrfToken('update_notification', $request->headers->get('X-CSRF-TOKEN')))) {
            return new JsonResponse(['message' => 'Invalid CSRF Token'], 400);
        }

        if (!isset($data['value'])) {
            return new JsonResponse(['message' => 'Invalid data'], 400);
        }

        $newState = (bool) $data['value'];

        $notificationOptions = array_keys(Notification::getAllEmployerNotificationTypes());

        $preferences = [];
        foreach ($notificationOptions as $option) {
            $preferences[$option] = $newState;
        }

        // $recruiter->setNotificationPreferences($preferences);
        // $em->persist($recruiter);
        $em->flush();

        return new JsonResponse(['message' => 'All preferences updated successfully - (Mocked for Recruiter)']);
    }
}
