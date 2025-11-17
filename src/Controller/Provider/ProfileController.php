<?php

namespace App\Controller\Provider;

use App\Entity\Notification;
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

        // --- PROFILE FORM ---
        $profileForm = $this->createForm(UserProfileType::class, $user);
        $profileForm->handleRequest($request);

        if ($profileForm->isSubmitted() && $profileForm->isValid()) {
            /** @var UploadedFile $profileFile */
            $profileFile = $profileForm->get('profilePicture')->getData();

            if ($profileFile) {
                $userDir = $uploadDirectory . '/' . $user->getId();
                if (!is_dir($userDir)) mkdir($userDir, 0777, true);

                $safeFilename = $slugger->slug(pathinfo($profileFile->getClientOriginalName(), PATHINFO_FILENAME));
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $profileFile->guessExtension();

                try {
                    $profileFile->move($userDir, $newFilename);
                    $user->setProfilePictureFilename($newFilename); // <-- Use Filename property
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
        $document = new Document();
        $documentForm = $this->createForm(DocumentType::class, $document);
        $documentForm->handleRequest($request);

        if ($documentForm->isSubmitted() && $documentForm->isValid()) {
            /** @var UploadedFile $uploadedFile */
            $uploadedFile = $documentForm->get('fileName')->getData();

            if ($uploadedFile) {
                $userDir = $uploadDirectory . '/' . $user->getId();
                if (!is_dir($userDir)) mkdir($userDir, 0777, true);

                $safeFilename = $slugger->slug(pathinfo($uploadedFile->getClientOriginalName(), PATHINFO_FILENAME));
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $uploadedFile->guessExtension();

                try {
                    $uploadedFile->move($userDir, $newFilename);
                    $document->setFileName($newFilename);
                    $document->setUser($user);
                } catch (FileException $e) {
                    $this->addFlash('error', 'Document upload failed: ' . $e->getMessage());
                    return $this->redirectToRoute('app_provider_profile');
                }

                $em->persist($document);
                $em->flush();
                $this->addFlash('success', 'Document uploaded successfully.');
                return $this->redirectToRoute('app_provider_profile');
            }
        }

        return $this->render('provider/profile/profile.html.twig', [
            'user' => $user,
            'profileForm' => $profileForm->createView(),
            'documentForm' => $documentForm->createView(),
        ]);
    }


    

    #[Route('/cv', name: 'app_provider_profile_cv', methods: ['GET', 'POST'])]
    public function cv(
        Request $request,
        EntityManagerInterface $em,
        SluggerInterface $slugger,
        #[Autowire('%kernel.project_dir%/public/uploads')] string $uploadDirectory
    ): Response
    {
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
                $newFilename = $safeFilename.'-'.uniqid().'.'.$cvFile->guessExtension();

                // Move the file to the directory where brochures are stored
                try {
                    $cvFile->move($uploadDirectory. '/' . $user->getId(), $newFilename);
                } catch (FileException $e) {
                    // ... handle exception if something happens during file upload
                }

                $provider->setCvFilename($newFilename);
            }

            $em->persist($provider);
            $em->flush();

            $this->addFlash('success', 'CV uploaded successfully.');

            if($request->get('save_continue') == 1){
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
    )
    {
        $user = $this->getUser();
        $provider = $user->getProvider();

        $cvFilePath = $uploadDirectory.'/'. $user->getId().'/'.$provider->getCvFilename();
        if(file_exists($cvFilePath)) {
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

            if($request->get('save_continue') == 1){
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

            if($request->get('save_continue') == 1){
                return $this->redirectToRoute('app_provider_education_index');
            }

            return $this->redirectToRoute('app_provider_profile_work_preferences');
        }

        return $this->render('provider/profile/work-preferences.html.twig', [
            'provider' => $provider,
            'form' => $form,
        ]);
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
    ): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        // Validate fields
        if (empty($data['bankName']) || empty($data['bankAccountName']) || empty($data['bankAccountNumber']) ||
            empty($data['paypalName']) || empty($data['paypalAccountNumber'])) {
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
    ): Response
    {
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
    ): Response
    {
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
                $newFilename = $safeFilename.'-'.uniqid().'.'.$uploadedFile->guessExtension();

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

}