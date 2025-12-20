<?php

namespace App\Controller\Employer;

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
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/employer/profile')]
class ProfileController extends AbstractController
{
    #[Route('/', name: 'app_employer_profile', methods: ['GET', 'POST'])]
    public function profile(
        Request $request,
        EntityManagerInterface $em,
        SluggerInterface $slugger,
        #[Autowire('%kernel.project_dir%/public/uploads')] string $uploadDirectory
    ): Response
    {
        $user = $this->getUser();
        $employer = $user->getEmployer();
        
        if (!$employer) {
            throw $this->createAccessDeniedException('Employer profile not found.');
        }

        // Calculate average rating from reviews
        $reviews = $em->getRepository(Review::class)->findBy([
            'employer' => $employer,
            'reviewedBy' => 'PROVIDER'
        ]);
        $averageRating = null;
        if (!empty($reviews)) {
            $totalPoints = 0;
            $count = 0;
            foreach ($reviews as $review) {
                if ($review->getPoint() !== null) {
                    $totalPoints += $review->getPoint();
                    $count++;
                }
            }
            if ($count > 0) {
                $averageRating = round($totalPoints / $count, 2);
            }
        }

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

        // Count jobs posted
        $jobsCount = $em->getRepository(\App\Entity\Job::class)->count([
            'employer' => $employer
        ]);

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

            // Update employer fields
            $employer->setCompanyName($request->get('companyName'));
            $employer->setContactPersonName($request->get('contactPersonName'));
            $employer->setContactEmail($request->get('contactEmail'));

            $em->persist($employer);
            $em->persist($user);
            $em->flush();

            $this->addFlash('success', 'Profile updated successfully.');
            return $this->redirectToRoute('app_employer_profile');
        }

        return $this->render('employer/profile/profile.html.twig', [
            'user' => $user,
            'employer' => $employer,
            'profileForm' => $profileForm->createView(),
            'averageRating' => $averageRating,
            'membershipLevel' => $membershipLevel,
            'yearsActive' => $yearsActive,
            'jobsCount' => $jobsCount,
            'reviewsCount' => count($reviews),
        ]);
    }

    #[Route('/update', name: 'app_employer_profile_update')]
    public function profileUpdate(
        Request $request,
        EntityManagerInterface $em,
        SluggerInterface $slugger,
        #[Autowire('%kernel.project_dir%/public/uploads')] string $uploadDirectory
    ): Response
    {
        $user = $this->getUser();
        $employer = $user->getEmployer();
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
                $newFilename = $safeFilename.'-'.uniqid().'.'.$profilePictureFile->guessExtension();

                // Move the file to the directory where brochures are stored
                try {
                    $profilePictureFile->move($uploadDirectory. '/' . $user->getId(), $newFilename);
                } catch (FileException $e) {
                    // ... handle exception if something happens during file upload
                }

                $user->setProfilePictureFilename($newFilename);
            }

            $employer->setCompanyName($request->get('companyName'));
            $employer->setContactPersonName($request->get('contactPersonName'));
            $employer->setContactEmail($request->get('contactEmail'));

            $em->persist($employer);
            $em->persist($user);
            $em->flush();

            $this->addFlash('success', 'Profile updated successfully.');
            return $this->redirectToRoute('app_employer_profile');
        }

        return $this->render('employer/profile/profile-update.html.twig', [
            'user' => $user,
            'form' => $form,
            'employer' => $user->getEmployer(),
        ]);
    }

    #[Route('/change-password', name: 'app_employer_change_password', methods: ['GET', 'POST'])]
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
                return $this->redirectToRoute('app_employer_change_password');
            }

            $encodedPassword = $passwordEncoder->hashPassword($user, $newPassword);
            $user->setPassword($encodedPassword);

            $em->flush();

            $this->addFlash('success', 'Password changed successfully.');
            return $this->redirectToRoute('app_employer_profile');
        }

        return $this->render('employer/profile/change_password.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/photo/upload', name: 'app_employer_profile_photo_upload', methods: ['POST'])]
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

        return $this->json(['success' => true, 'url' => $url]);
    }

    #[Route('/profile-picture-remove', name: 'app_employer_profile_picture_remove', methods: ['GET'])]
    public function profilePictureRemove(
        EntityManagerInterface $em,
        #[Autowire('%kernel.project_dir%/public/uploads')] string $uploadDirectory
    )
    {
        $user = $this->getUser();

        $ppFilePath = $uploadDirectory.'/'. $user->getId().'/'.$user->getProfilePictureFilename();
        if(file_exists($ppFilePath)) {
            unlink($ppFilePath);
        }

        $user->setProfilePicture(null);
        $user->setProfilePictureFilename(null);

        $em->persist($user);
        $em->flush();

        $this->addFlash('success', 'Profile picture removed successfully.');
        return $this->redirectToRoute('app_employer_profile');
    }
}