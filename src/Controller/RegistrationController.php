<?php

namespace App\Controller;

use App\Entity\Employer;
use App\Entity\Provider;
use App\Entity\User;
use App\Form\ProviderRegistrationFormType;
use App\Form\RegistrationFormType;
use App\Message\SendVerificationMessage;
use App\Repository\UserRepository;
use App\Security\AppUserAuthenticator;
use App\Security\EmailVerifier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;

class RegistrationController extends AbstractController
{
    public function __construct(private EmailVerifier $emailVerifier)
    {
    }

    #[Route('/register', name: 'app_register')]
    public function register(
        Request $request,
        UserPasswordHasherInterface $userPasswordHasher,
        Security $security,
        EntityManagerInterface $entityManager,
        MessageBusInterface $bus
    ): Response
    {
        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $userType = $user->getUserType();
            $user->setUserType($userType);

            if($userType == User::TYPE_PROVIDER) {
                $user->setRoles(['ROLE_PROVIDER']);

                $provider = new Provider();
                $provider->setUser($user);
                $provider->setName($user->getName());

                $entityManager->persist($provider);

                $user->setProvider($provider);
            }

            if($userType == User::TYPE_EMPLOYER) {
                $user->setRoles(['ROLE_EMPLOYER']);

                $employer = new Employer();
                $employer->setName($user->getName());

                $entityManager->persist($employer);

                $user->setEmployer($employer);
            }

            // encode the plain password
            $user->setPassword(
                $userPasswordHasher->hashPassword(
                    $user,
                    $form->get('plainPassword')->getData()
                )
            );

            $entityManager->persist($user);
            $entityManager->flush();

            $bus->dispatch(new SendVerificationMessage($user->getId()));

//            return $security->login($user, AppUserAuthenticator::class, 'main');

            return $this->redirectToRoute('app_register_check_email');
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form,
        ]);
    }

    #[Route('/check-email', name: 'app_register_check_email')]
    public function checkEmail(Request $request, UserRepository $userRepository): Response
    {
        return $this->render('registration/check_email.html.twig');
    }

    #[Route('/verify-email', name: 'app_verify_email')]
    public function verifyUserEmail(Request $request, UserRepository $userRepository): Response
    {
        $id = $request->query->get('id');

        if (null === $id) {
            return $this->redirectToRoute('app_register');
        }

        $user = $userRepository->find($id);

        if (null === $user) {
            return $this->redirectToRoute('app_register');
        }

        // validate email confirmation link, sets User::isVerified=true and persists
        try {
            $this->emailVerifier->handleEmailConfirmation($request, $user);
        } catch (VerifyEmailExceptionInterface $exception) {
            $this->addFlash('verify_email_error', $exception->getReason());
            return $this->redirectToRoute('app_register');
        }

        $this->addFlash('verify_email_success', 'Your email address has been verified. Please login to continue.');
        return $this->redirectToRoute('app_login');
    }
}
