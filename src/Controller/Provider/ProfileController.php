<?php

namespace App\Controller\Provider;

use App\Entity\Notification;
use App\Entity\Provider;
use App\Form\ChangePasswordFormType;
use App\Form\ProviderCvType;
use App\Form\ProviderBasicInformationType;
use App\Form\ProviderWorkPreferenceType;
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
use App\Form\DocumentType;
use App\Entity\Document;
use App\Entity\Education;
use App\Entity\License;
use App\Entity\Insurance;
use App\Form\EducationType;
use App\Form\InsuranceType;

#[Route('/provider/profile')]
class ProfileController extends AbstractController
{
    #[Route('/', name: 'app_provider_profile', methods: ['GET', 'POST'])]
    public function profile(
        Request $request,
        EntityManagerInterface $em,
        SluggerInterface $slugger,
        #[Autowire('%kernel.project_dir%/public/uploads')] string $uploadDirectory

    ): Response {
        $user = $this->getUser();
        $provider = $user?->getProvider();
        if (!$provider) {
            throw $this->createAccessDeniedException('Provider profile not found.');
        }
        $cvDocuments = $em->getRepository(Document::class)->findBy(
            ['user' => $user, 'category' => 'CV Upload'],
            ['createdAt' => 'DESC']
        );

        // --- PROFILE FORM ---
        $profileForm = $this->createForm(UserProfileType::class, $user);
        $profileForm->handleRequest($request);

        if ($profileForm->isSubmitted() && $profileForm->isValid()) {
            /** @var UploadedFile $profileFile */
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

            $em->persist($user);
            $em->flush();

            $this->addFlash('success', 'Profile updated successfully.');
            return $this->redirectToRoute('app_provider_profile');
        }

        // --- DOCUMENT FORM ---
        $cvForm = $this->createForm(ProviderCvType::class, $provider);
        $cvForm->handleRequest($request);

        // Debug logging
        if ($request->isXmlHttpRequest()) {
            error_log('AJAX Request detected');
            error_log('Request method: ' . $request->getMethod());
            error_log('Expected Form Name: ' . $cvForm->getName());
            error_log('Form submitted: ' . ($cvForm->isSubmitted() ? 'YES' : 'NO'));
            if ($cvForm->isSubmitted()) {
                error_log('Form valid: ' . ($cvForm->isValid() ? 'YES' : 'NO'));
                if (!$cvForm->isValid()) {
                    error_log('Form Errors: ' . $cvForm->getErrors(true, false));
                }
            }
            error_log('POST keys: ' . json_encode(array_keys($request->request->all())));
            error_log('FILES keys: ' . json_encode(array_keys($request->files->all())));
        }

        if ($cvForm->isSubmitted() && $cvForm->isValid()) {
            /** @var UploadedFile $cvFile */
            $cvFile = $cvForm->get('cv')->getData();

            if ($cvFile) {
                $userDir = $uploadDirectory . '/' . $user->getId();
                if (!is_dir($userDir)) {
                    mkdir($userDir, 0777, true);
                }

                $safeFilename = $slugger->slug(pathinfo($cvFile->getClientOriginalName(), PATHINFO_FILENAME));
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $cvFile->guessExtension();

                try {
                    $cvFile->move($userDir, $newFilename);
                    $document = new Document();
                    $document->setUser($user);
                    $document->setCategory('CV Upload');
                    $document->setName($cvFile->getClientOriginalName() ?: 'CV Upload');
                    $document->setFileName($newFilename);
                    $provider->setCvFilename($newFilename);
                    $em->persist($document);
                } catch (FileException $e) {
                    if ($request->isXmlHttpRequest()) {
                        return $this->json(['success' => false, 'message' => 'CV upload failed: ' . $e->getMessage()], 400);
                    }
                    $this->addFlash('error', 'CV upload failed: ' . $e->getMessage());
                    return $this->redirectToRoute('app_provider_profile');
                }

                $em->persist($provider);
                $em->flush();

                // Handle AJAX request
                if ($request->isXmlHttpRequest()) {
                    // Get updated CV documents list
                    $cvDocuments = $em->getRepository(Document::class)->findBy(
                        ['user' => $user, 'category' => 'CV Upload'],
                        ['createdAt' => 'DESC']
                    );

                    // Render the CV list HTML
                    $cvListHtml = $this->renderView('provider/profile/_cv_list.html.twig', [
                        'cvDocuments' => $cvDocuments,
                    ]);

                    return $this->json([
                        'success' => true,
                        'message' => 'CV uploaded successfully.',
                        'cvListHtml' => $cvListHtml,
                        'fileName' => $document->getName() ?: $cvFile->getClientOriginalName()
                    ]);
                }

                $this->addFlash('success', 'CV uploaded successfully.');
            } else {
                if ($request->isXmlHttpRequest()) {
                    return $this->json(['success' => false, 'message' => 'Please select a CV file to upload.'], 400);
                }
                $this->addFlash('warning', 'Please select a CV file to upload.');
            }

            if (!$request->isXmlHttpRequest()) {
                return $this->redirectToRoute('app_provider_profile');
            }
        }

        // Handle AJAX validation errors
        if ($cvForm->isSubmitted() && !$cvForm->isValid() && $request->isXmlHttpRequest()) {
            $errors = [];
            foreach ($cvForm->getErrors(true) as $error) {
                $errors[] = $error->getMessage();
            }
            return $this->json(['success' => false, 'message' => implode(', ', $errors)], 400);
        }

        $workPreferenceForm = $this->createForm(ProviderWorkPreferenceType::class, $provider, [
            'action' => $this->generateUrl('app_provider_profile_work_preferences_save'),
            'method' => 'POST',
        ]);

        $educationForm = $this->createForm(EducationType::class, new Education(), [
            'action' => $this->generateUrl('app_provider_education_new'),
            'method' => 'POST',
        ]);

        $licenses = $em->getRepository(License::class)->findBy(['user' => $user], ['issueDate' => 'DESC']);
        $insurances = $em->getRepository(Insurance::class)->findBy(['user' => $user], ['effectiveDate' => 'DESC']);
        $insuranceForm = $this->createForm(InsuranceType::class, new Insurance(), [
            'action' => $this->generateUrl('app_provider_insurance_index'),
            'method' => 'POST',
        ]);

        $licenseForm = $this->createForm(\App\Form\LicenseType::class, new License(), [
            'action' => $this->generateUrl('app_provider_license_index'),
            'method' => 'POST',
        ]);

        return $this->render('provider/profile/profile.html.twig', [
            'user' => $user,
            'profileForm' => $profileForm->createView(),
            'cvForm' => $cvForm->createView(),
            'providerEntity' => $provider,
            'cvDocuments' => $cvDocuments,
            'workPreferenceForm' => $workPreferenceForm->createView(),
            'educationForm' => $educationForm->createView(),
            'licenses' => $licenses,
            'insurances' => $insurances,
            'licenseForm' => $licenseForm->createView(),
            'insuranceForm' => $insuranceForm->createView(),
        ]);
    }

    #[Route('/photo/upload', name: 'app_provider_profile_photo_upload', methods: ['POST'])]
    public function uploadPhoto(
        Request $request,
        EntityManagerInterface $em,
        SluggerInterface $slugger,
        #[Autowire('%kernel.project_dir%/public/uploads')] string $uploadDirectory
    ): JsonResponse {
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
        $this->addFlash('success', 'Profile picture updated.');
        return $this->redirectToRoute('app_provider_profile');
    }

    #[Route('/cv/delete/{id}', name: 'app_provider_profile_cv_delete', methods: ['POST'])]
    public function deleteCv(Document $document, Request $request, EntityManagerInterface $em, #[Autowire('%kernel.project_dir%/public/uploads')] string $uploadDirectory): Response
    {
        $user = $this->getUser();
        if ($document->getUser() !== $user) {
            throw $this->createAccessDeniedException();
        }
        if (!$this->isCsrfTokenValid('delete_cv_' . $document->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }
        $filePath = $uploadDirectory . '/' . $user->getId() . '/' . $document->getFileName();
        if (is_file($filePath)) {
            @unlink($filePath);
        }
        $em->remove($document);
        $em->flush();
        $this->addFlash('success', 'CV deleted successfully.');
        return $this->redirectToRoute('app_provider_profile');
    }




    #[Route('/cv', name: 'app_provider_profile_cv', methods: ['GET', 'POST'])]
    public function cv(
        Request $request,
        EntityManagerInterface $em,
        SluggerInterface $slugger,
        #[Autowire('%kernel.project_dir%/public/uploads')] string $uploadDirectory
    ): Response {
        $user = $this->getUser();
        $provider = $user->getProvider();
        $form = $this->createForm(ProviderCvType::class, $provider);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile $brochureFile */
            $cvFile = $form->get('cv')->getData();

            // this condition is needed because the 'brochure' field is not required
            // so the PDF file must be processed only when a file is uploaded
            if ($cvFile) {
                $originalFilename = pathinfo($cvFile->getClientOriginalName(), PATHINFO_FILENAME);
                // this is needed to safely include the file name as part of the URL
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $cvFile->guessExtension();

                // Move the file to the directory where brochures are stored
                try {
                    $cvFile->move($uploadDirectory . '/' . $user->getId(), $newFilename);
                } catch (FileException $e) {
                    // ... handle exception if something happens during file upload
                }

                $provider->setCvFilename($newFilename);
            }

            $em->persist($provider);
            $em->flush();

            $this->addFlash('success', 'CV uploaded successfully.');

            if ($request->get('save_continue') == 1) {
                return $this->redirectToRoute('app_provider_profile_basic_information');
            }

            return $this->redirectToRoute('app_provider_profile_cv');
        }

        return $this->render('provider/profile/cv.html.twig', [
            'provider' => $provider,
            'form' => $form,
        ]);
    }

    #[Route('/cv-remove', name: 'app_provider_profile_cv_remove', methods: ['GET'])]
    public function cvRemove(
        EntityManagerInterface $em,
        #[Autowire('%kernel.project_dir%/public/uploads')] string $uploadDirectory
    ) {
        $user = $this->getUser();
        $provider = $user->getProvider();

        $cvFilePath = $uploadDirectory . '/' . $user->getId() . '/' . $provider->getCvFilename();
        if (file_exists($cvFilePath)) {
            unlink($cvFilePath);
        }

        $provider->setCvFilename(null);

        $em->persist($provider);
        $em->flush();

        $this->addFlash('success', 'CV removed successfully.');
        return $this->redirectToRoute('app_provider_profile_cv');
    }

    #[Route('/basic-information', name: 'app_provider_profile_basic_information', methods: ['GET', 'POST'])]
    public function basicInformation(Request $request, EntityManagerInterface $em): Response
    {
        $provider = $this->getUser()->getProvider();
        $form = $this->createForm(ProviderBasicInformationType::class, $provider);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($provider);
            $em->flush();

            $this->addFlash('success', 'Basic information updated successfully.');

            if ($request->get('save_continue') == 1) {
                return $this->redirectToRoute('app_provider_profile_work_preferences');
            }

            return $this->redirectToRoute('app_provider_profile_basic_information');
        }

        return $this->render('provider/profile/basic-information.html.twig', [
            'provider' => $provider,
            'form' => $form,
        ]);
    }

    #[Route('/work-preferences', name: 'app_provider_profile_work_preferences', methods: ['GET', 'POST'])]
    public function workPreferences(Request $request, EntityManagerInterface $em): Response
    {
        $provider = $this->getUser()->getProvider();
        $form = $this->createForm(ProviderWorkPreferenceType::class, $provider);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($provider);
            $em->flush();

            $this->addFlash('success', 'Work preferences updated successfully.');

            if ($request->get('save_continue') == 1) {
                return $this->redirectToRoute('app_provider_education_index');
            }

            return $this->redirectToRoute('app_provider_profile_work_preferences');
        }

        return $this->render('provider/profile/work-preferences.html.twig', [
            'provider' => $provider,
            'form' => $form,
        ]);
    }

    #[Route('/work-preferences/save-inline', name: 'app_provider_profile_work_preferences_save', methods: ['POST'])]
    public function saveWorkPreferencesInline(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $provider = $this->getUser()->getProvider();
        if (!$provider) {
            return $this->json(['success' => false, 'message' => 'Provider profile not found.'], 404);
        }

        $form = $this->createForm(ProviderWorkPreferenceType::class, $provider);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($provider);
            $em->flush();

            $summaryHtml = $this->renderView('provider/profile/_work_preferences_summary.html.twig', [
                'provider' => $provider,
            ]);

            return $this->json([
                'success' => true,
                'summaryHtml' => $summaryHtml,
                'hasPreferences' => $this->hasWorkPreferencesData($provider),
            ]);
        }

        $errors = [];
        foreach ($form->getErrors(true) as $error) {
            $errors[] = $error->getMessage();
        }

        return $this->json([
            'success' => false,
            'message' => $errors ? implode("\n", $errors) : 'Unable to save work preferences.',
        ], 400);
    }

    #[Route('/change-password', name: 'app_provider_change_password', methods: ['GET', 'POST'])]
    public function changePassword(Request $request, UserPasswordHasherInterface $passwordEncoder, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();

        $form = $this->createForm(ChangePasswordFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $currentPassword = $form->get('current_password')->getData();
            $newPassword = $form->get('new_password')->getData();

            if (!$passwordEncoder->isPasswordValid($user, $currentPassword)) {
                $this->addFlash('error', 'The current password is incorrect.');
                return $this->redirectToRoute('app_provider_change_password');
            }

            $encodedPassword = $passwordEncoder->hashPassword($user, $newPassword);
            $user->setPassword($encodedPassword);

            $em->flush();

            $this->addFlash('success', 'Password changed successfully.');
            return $this->redirectToRoute('app_provider_profile');
        }

        return $this->render('provider/profile/change_password.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/save-cashback', name: 'app_provider_save_cashback', methods: ['POST'])]
    public function saveCashback(
        Request $request,
        EntityManagerInterface $em
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        // Validate fields
        if (
            empty($data['bankName']) || empty($data['bankAccountName']) || empty($data['bankAccountNumber']) ||
            empty($data['paypalName']) || empty($data['paypalAccountNumber'])
        ) {
            return new JsonResponse(['success' => false, 'message' => 'All fields are required.'], 400);
        }

        /** @var Provider $provider */
        $provider = $this->getUser()->getProvider();

        if (!$provider) {
            return new JsonResponse(['success' => false, 'message' => 'Provider not found.'], 404);
        }

        // Save to cashback JSON field
        $provider->setCashback([
            'bank' => [
                'name' => $data['bankName'],
                'accountName' => $data['bankAccountName'],
                'accountNumber' => $data['bankAccountNumber'],
            ],
            'paypal' => [
                'name' => $data['paypalName'],
                'accountNumber' => $data['paypalAccountNumber'],
            ]
        ]);

        $em->persist($provider);
        $em->flush();

        return new JsonResponse(['success' => true]);
    }

    #[Route('/notification-update-preferences-single', name: 'app_provider_notification_update_preferences_single', methods: ['POST'])]
    public function updateNotificationPreferencesSingle(
        Request $request,
        EntityManagerInterface $em,
        CsrfTokenManagerInterface $csrfTokenManager
    ): Response {
        $provider = $this->getUser()->getProvider();

        $data = json_decode($request->getContent(), true);

        if (!$csrfTokenManager->isTokenValid(new CsrfToken('update_notification', $request->headers->get('X-CSRF-TOKEN')))) {
            return new JsonResponse(['message' => 'Invalid CSRF Token'], 400);
        }

        if (!isset($data['key'], $data['value'])) {
            return new JsonResponse(['message' => 'Invalid data'], 400);
        }

        $preferences = $provider->getNotificationPreferences() ?? [];

        $preferences[$data['key']] = (bool) $data['value'];

        $provider->setNotificationPreferences($preferences);
        $em->persist($provider);

        $em->flush();

        return new JsonResponse(['message' => 'Preference updated successfully']);
    }

    #[Route('/notification-update-preferences-all', name: 'app_provider_notification_update_preferences_all', methods: ['POST'])]
    public function updateNotificationPreferencesAll(
        Request $request,
        EntityManagerInterface $em,
        CsrfTokenManagerInterface $csrfTokenManager
    ): Response {
        $provider = $this->getUser()->getProvider();

        $data = json_decode($request->getContent(), true);

        if (!$csrfTokenManager->isTokenValid(new CsrfToken('update_notification', $request->headers->get('X-CSRF-TOKEN')))) {
            return new JsonResponse(['message' => 'Invalid CSRF Token'], 400);
        }

        if (!isset($data['value'])) {
            return new JsonResponse(['message' => 'Invalid data'], 400);
        }

        $newState = (bool) $data['value'];

        $notificationOptions = array_keys(Notification::getAllProviderNotificationTypes());

        $preferences = [];
        foreach ($notificationOptions as $option) {
            $preferences[$option] = $newState;
        }

        $provider->setNotificationPreferences($preferences);
        $em->persist($provider);
        $em->flush();

        return new JsonResponse(['message' => 'All preferences updated successfully']);
    }

    public function editProfile(Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        $form = $this->createForm(UserProfileType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            /** @var UploadedFile $file */
            $file = $form->get('profilePictureFilename')->getData();

            if ($file) {
                // Define upload directory (parameter or hardcoded)
                $uploadsDir = $this->getParameter('kernel.project_dir') . '/public/uploads/' . $user->getId();

                // Create directory if not exists
                if (!is_dir($uploadsDir)) {
                    mkdir($uploadsDir, 0777, true);
                }

                // Generate unique filename
                $newFilename = uniqid() . '.' . $file->guessExtension();

                // Move file
                $file->move($uploadsDir, $newFilename);

                // Save in DB (since your column is 'profile_picture')
                $user->setProfilePicture($newFilename);
            }

            $entityManager->persist($user);
            $entityManager->flush();

            $this->addFlash('success', 'Profile updated successfully!');
            return $this->redirectToRoute('profile_edit');
        }

        return $this->render('provider/profile/profile.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/cv/upload', name: 'cv_upload')]
    public function uploadCv(Request $request, SluggerInterface $slugger): Response
    {
        $document = new Document();
        $form = $this->createForm(DocumentType::class, $document);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $uploadedFile = $form['fileName']->getData();

            if ($uploadedFile) {
                $originalFilename = pathinfo($uploadedFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $uploadedFile->guessExtension();

                try {
                    $uploadedFile->move(
                        $this->getParameter('documents_directory'),
                        $newFilename
                    );
                } catch (FileException $e) {
                    $this->addFlash('error', 'Failed to upload file.');
                    return $this->redirectToRoute('cv_upload');
                }

                $document->setFileName($newFilename); // Save filename in entity
            }

            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($document);
            $entityManager->flush();

            $this->addFlash('success', 'CV uploaded successfully!');
            return $this->redirectToRoute('cv_upload');
        }

        return $this->render('document/upload.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    private function hasWorkPreferencesData(Provider $provider): bool
    {
        return !empty($provider->getDesiredPayRate())
            || !empty($provider->getDesiredHour())
            || !empty($provider->getDesiredStates())
            || !empty($provider->getPayRateDaily())
            || !empty($provider->getPayRateHourly())
            || !empty($provider->getPreferredPatientVolume())
            || $provider->getAvailabilityToStart() !== null
            || $provider->getWillingToTravel() !== null
            || $provider->getProfession() !== null
            || ($provider->getSpecialities() && $provider->getSpecialities()->count() > 0);
    }


    // Add these routes to your existing ProfileController class

}
