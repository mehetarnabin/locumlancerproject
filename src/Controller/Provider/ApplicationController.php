<?php

namespace App\Controller\Provider;

use App\Form\ProviderApplicationCertificationType;
use App\Form\ProviderReleaseAuthorizationType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

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
}