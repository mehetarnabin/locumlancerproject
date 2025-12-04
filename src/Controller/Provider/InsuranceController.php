<?php

namespace App\Controller\Provider;

use App\Entity\Insurance;
use App\Form\InsuranceType;
use App\Repository\InsuranceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Intl\Countries;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

#[Route('/provider/application/insurance')]
class InsuranceController extends AbstractController
{
    #[Route('/', name: 'app_provider_insurance_index', methods: ['GET', 'POST'])]
    public function index(InsuranceRepository $insuranceRepository, Request $request, EntityManagerInterface $entityManager): Response
    {
        $insurance = new Insurance();
        $insurance->setUser($this->getUser());
        $form = $this->createForm(InsuranceType::class, $insurance);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($insurance);
            $entityManager->flush();

            if ($request->isXmlHttpRequest()) {
                return $this->json([
                    'status' => 'success',
                    'insurance' => [
                        'id' => (string)$insurance->getId(),
                        'carrier' => $insurance->getCarrier(),
                        'policyNumber' => $insurance->getPolicyNumber(),
                        'amount' => $insurance->getAmount(),
                        'state' => $insurance->getState(),
                        'city' => $insurance->getCity(),
                        'effectiveDate' => $insurance->getEffectiveDate() ? $insurance->getEffectiveDate()->format('m/d/Y') : null,
                        'expirationDate' => $insurance->getExpirationDate() ? $insurance->getExpirationDate()->format('m/d/Y') : null,
                    ]
                ]);
            }

            $this->addFlash('success', 'Professional Liability Insurance added successfully');
            return $this->redirectToRoute('app_provider_insurance_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('provider/insurance/index.html.twig', [
            'insurances' => $insuranceRepository->findBy(['user' => $this->getUser()], ['effectiveDate' => 'DESC']),
            'insurance' => $insurance,
            'form' => $form->createView(),
            'countries' => Countries::getNames()
        ]);
    }

    #[Route('/new', name: 'app_provider_insurance_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $insurance = new Insurance();
        $insurance->setUser($this->getUser());
        $form = $this->createForm(InsuranceType::class, $insurance);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($insurance);
            $entityManager->flush();

            $this->addFlash('success', 'Professional Liability Insurance added successfully');
            return $this->redirectToRoute('app_provider_insurance_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('provider/insurance/new.html.twig', [
            'insurance' => $insurance,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_provider_insurance_show', methods: ['GET'])]
    public function show(Insurance $insurance, Request $request): Response
    {
        return $this->render('provider/insurance/show.html.twig', [
            'insurance' => $insurance,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_provider_insurance_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Insurance $insurance, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(InsuranceType::class, $insurance, [
            'action' => $this->generateUrl('app_provider_insurance_edit', ['id' => $insurance->getId()]),
            'method' => 'POST',
        ]);
        $form->handleRequest($request);

        if ($request->isXmlHttpRequest()) {
            if ($request->isMethod('POST')) {
                if ($form->isSubmitted() && $form->isValid()) {
                    $entityManager->flush();
                    return $this->json([
                        'status' => 'success',
                        'insurance' => [
                            'id' => (string)$insurance->getId(),
                            'carrier' => $insurance->getCarrier(),
                            'policyNumber' => $insurance->getPolicyNumber(),
                            'amount' => $insurance->getAmount(),
                            'state' => $insurance->getState(),
                            'city' => $insurance->getCity(),
                            'effectiveDate' => $insurance->getEffectiveDate() ? $insurance->getEffectiveDate()->format('m/d/Y') : null,
                            'expirationDate' => $insurance->getExpirationDate() ? $insurance->getExpirationDate()->format('m/d/Y') : null,
                        ]
                    ]);
                }
                return $this->render('provider/insurance/_form.html.twig', [
                    'form' => $form->createView(),
                ]);
            }
            return $this->json([
                'form' => $this->renderView('provider/insurance/_form.html.twig', [
                    'form' => $form->createView(),
                ])
            ]);
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            if ($request->isXmlHttpRequest()) {
                return $this->json(['status' => 'success']);
            }

            $this->addFlash('success', 'Professional Liability Insurance updated successfully');

            if($request->request->has('save_continue')){
                return $this->redirectToRoute('app_provider_health_assessment');
            }

            return $this->redirectToRoute('app_provider_insurance_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('provider/insurance/edit.html.twig', [
            'insurance' => $insurance,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/delete', name: 'app_provider_insurance_delete', methods: ['GET'])]
    public function delete(Request $request, Insurance $insurance, EntityManagerInterface $entityManager): Response
    {
        $entityManager->remove($insurance);
        $entityManager->flush();

        $this->addFlash('success', 'Insurance & training deleted successfully');
        return $this->redirectToRoute('app_provider_insurance_index', [], Response::HTTP_SEE_OTHER);
    }
}
