<?php

namespace App\Controller;

use App\Entity\Employer;
use App\Entity\Provider;
use App\Entity\User;
use App\Form\CompanyProfileType;
use App\Form\UserProfileType;
use App\Security\AppUserAuthenticator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/onboard')]
class OnboardController extends AbstractController
{
    #[Route('/', name: 'app_onboard')]
    public function index(Request $request, EntityManagerInterface $em, Security $security): Response
    {
        $user = $this->getUser();

        if($user->getUserType() == User::TYPE_PROVIDER) {
            return $this->redirectToRoute('app_provider_dashboard');
        }elseif($user->getUserType() == User::TYPE_EMPLOYER) {
            return $this->redirectToRoute('app_employer_dashboard');
        }

        if($request->getMethod() == 'POST'){
            $userType = $request->get('type');
            $user->setUserType($userType);

            if($userType == User::TYPE_PROVIDER) {
                $user->setRoles(['ROLE_PROVIDER']);

                $provider = new Provider();
                $provider->setUser($user);
                $provider->setName($user->getName());

                $em->persist($provider);

                $user->setProvider($provider);
            }

            if($userType == User::TYPE_EMPLOYER) {
                $user->setRoles(['ROLE_EMPLOYER']);

                $employer = new Employer();
                $employer->setName($user->getName());

                $em->persist($employer);

                $user->setEmployer($employer);
            }

            $em->persist($user);
            $em->flush();

            return $security->login($user, AppUserAuthenticator::class, 'main');
        }

        return $this->render('onboard/index.html.twig', ['user' => $user]);
    }
}
