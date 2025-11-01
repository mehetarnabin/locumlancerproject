<?php

namespace App\Controller\Employer;

use App\Form\ChangePasswordFormType;
use App\Form\UserProfileType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/employer/profile')]
class ProfileController extends AbstractController
{
    #[Route('/', name: 'app_employer_profile')]
    public function profile(Request $request, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        $employer = $user->getEmployer();
        return $this->render('employer/profile/profile.html.twig', [
            'user' => $user,
            'employer' => $user->getEmployer(),
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