<?php

namespace App\Controller\Provider;

use App\Entity\Cashback;
use App\Repository\CashbackRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/provider')]
class CashbackController extends AbstractController
{
    #[Route('/cashback', name: 'app_provider_cashback')]
    public function index(CashbackRepository $cashbackRepository): Response
    {
        $provider = $this->getUser()->getProvider();
        $cashbacks = $cashbackRepository->findBy(['provider' => $this->getUser()->getProvider()], ['createdAt' => 'DESC']);
        return $this->render('provider/cashback/index.html.twig', [
            'cashbacks' => $cashbacks,
            'provider' => $provider,
        ]);
    }

    #[Route('/cashback/setup', name: 'app_provider_cashback_setup')]
    public function setup(
        Request $request,
        EntityManagerInterface $em
    ): Response
    {
        $provider = $this->getUser()->getProvider();

        if($request->isMethod('POST')) {
            $data = $request->request->all();

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

            $this->addFlash('success', 'Cashback setup successful.');
            return $this->redirectToRoute('app_provider_cashback');
        }

        return $this->render('provider/cashback/setup.html.twig', [
            'provider' => $provider,
        ]);
    }

    #[Route('/cashback/{id}/detail', name: 'app_provider_cashback_detail')]
    public function show(Cashback $cashback)
    {
        return $this->render('provider/cashback/show.html.twig', [
            'cashback' => $cashback,
        ]);
    }
}