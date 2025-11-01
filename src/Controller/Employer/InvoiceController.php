<?php

namespace App\Controller\Employer;

use App\Entity\Cashback;
use App\Entity\Employer;
use App\Entity\Invoice;
use App\Event\InvoiceEvent;
use App\Service\ConfigManager;
use Doctrine\ORM\EntityManagerInterface;
use Stripe\Stripe;
use Stripe\StripeClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[Route('/employer')]
class InvoiceController extends AbstractController
{
    #[Route('/invoices', name: 'app_employer_invoices')]
    public function index(EntityManagerInterface $em): Response
    {
        $employer = $this->getUser()->getEmployer();

        $invoices = $em->getRepository(Invoice::class)->findBy(['employer' => $employer], ['createdAt' => 'DESC']);
        return $this->render('employer/invoice/index.html.twig', [
            'invoices' => $invoices,
        ]);
    }

    #[Route('/invoices/pending', name: 'app_employer_invoices_pending')]
    public function pending(EntityManagerInterface $em): Response
    {
        $employer = $this->getUser()->getEmployer();

        $invoices = $em->getRepository(Invoice::class)->findBy(['employer' => $employer, 'status' => Invoice::INVOICE_STATUS_PENDING], ['createdAt' => 'DESC']);
        return $this->render('employer/invoice/pending-invoices.html.twig', [
            'invoices' => $invoices,
        ]);
    }

    #[Route('/invoices/paid', name: 'app_employer_invoices_paid')]
    public function paid(EntityManagerInterface $em): Response
    {
        $employer = $this->getUser()->getEmployer();

        $invoices = $em->getRepository(Invoice::class)->findBy(['employer' => $employer, 'status' => Invoice::INVOICE_STATUS_PAID], ['createdAt' => 'DESC']);
        return $this->render('employer/invoice/paid-invoices.html.twig', [
            'invoices' => $invoices,
        ]);
    }

    #[Route('/invoices/{id}/show', name: 'app_employer_invoice_show')]
    public function show(Invoice $invoice): Response
    {
        $currentEmployer = $this->getUser()->getEmployer();

        if($invoice->getEmployer() !== $currentEmployer) {
            $this->addFlash('error', "You don't have access to this invoice.");
            return $this->redirectToRoute('app_employer_invoices');
        }

        return $this->render('employer/invoice/detail.html.twig', [
            'invoice' => $invoice,
        ]);
    }

    #[Route('/invoices/{id}/pay', name: 'app_employer_invoice_pay')]
    public function pay(Invoice $invoice, Request $request, EntityManagerInterface $em, ParameterBagInterface  $params): Response
    {
        $currentEmployer = $this->getUser()->getEmployer();

        if($invoice->getEmployer() !== $currentEmployer) {
            $this->addFlash('error', "You don't have access to this invoice.");
            return $this->redirectToRoute('app_employer_invoices');
        }

        return $this->render('employer/invoice/pay.html.twig', [
            'invoice' => $invoice,
            'stripe_api_key' => $params->get('stripe_api_key'),
            'stripe_public_key' => $params->get('stripe_public_key')
        ]);
    }

    #[Route('/invoices/{id}/pay/get-client-secret', name: 'app_employer_invoice_pay_get_client_secret', methods: ['POST'])]
    public function getClientSecret(Invoice $invoice, Request $request, EntityManagerInterface $em, ParameterBagInterface  $params): Response
    {
        $stripe = new StripeClient([
            "api_key" => $params->get('stripe_api_key')
        ]);

        $checkout_session = $stripe->checkout->sessions->create([
            'line_items' => [[
                'price_data' => [
                    'currency' => 'USD',
                    'product_data' => [
                        'name' => $invoice->getParticular(),
                    ],
                    'unit_amount' => $invoice->getAmount() * 100,
                ],
                'quantity' => 1,
            ]],
            'mode' => 'payment',
            'ui_mode' => 'embedded',
            'allow_promotion_codes' => true,
            'return_url' => $this->generateUrl('app_employer_invoice_payment_return', ['id' => $invoice->getId()], UrlGeneratorInterface::ABSOLUTE_URL).'?session_id={CHECKOUT_SESSION_ID}',
        ]);

        return new JsonResponse([
            'clientSecret' => $checkout_session->client_secret
        ]);
    }

//    #[Route('/invoices/get-payment-status', name: 'app_employer_invoice_get_payment_status', methods: ['POST'])]
//    public function getPaymentStatus(ParameterBagInterface  $params): Response
//    {
//        $stripe = new StripeClient([
//            "api_key" => $params->get('stripe_api_key')
//        ]);
//
//        try {
//            // retrieve JSON from POST body
//            $jsonStr = file_get_contents('php://input');
//            $jsonObj = json_decode($jsonStr);
//
//            $session = $stripe->checkout->sessions->retrieve($jsonObj->session_id);
//
//            return new JsonResponse(['status' => $session->status, 'customer_email' => $session->customer_details->email]);
//        } catch (\Exception $e) {
//            return new JsonResponse(['error' => $e->getMessage()], 400);
//        }
//    }

    #[Route('/invoices/{id}/payment-return', name: 'app_employer_invoice_payment_return')]
    public function success(
        Invoice $invoice,
        Request $request,
        EntityManagerInterface $em,
        ParameterBagInterface  $params,
        EventDispatcherInterface $dispatcher,
        ConfigManager $configManager
    ): Response
    {
        $stripe = new StripeClient([
            "api_key" => $params->get('stripe_api_key')
        ]);

        $sessionId = $request->get('session_id');

        try {
            $session = $stripe->checkout->sessions->retrieve($sessionId);

            if($session->status == 'open')
            {
                return $this->redirectToRoute('app_employer_invoice_pay', ['id' => $invoice->getId()]);
            } elseif($session->status == 'complete'){
                $invoice->setStatus(Invoice::INVOICE_STATUS_PAID);

                $em->persist($invoice);

                if($configManager->get('cashback_percent') && $configManager->get('cashback_percent') > 0) {
                    $cashBack = new CashBack();
                    $cashBack->setEmployer($invoice->getEmployer());
                    $cashBack->setProvider($invoice->getProvider());
                    $cashBack->setParticular($invoice->getParticular());
                    $cashBack->setJob($invoice->getJob());
                    $cashBack->setInvoice($invoice);
                    $cashBack->setAmount($invoice->getAmount() * $configManager->get('cashback_percent') / 100);
                    $cashBack->setStatus(Cashback::CASHBACK_STATUS_PENDING);

                    $em->persist($cashBack);
                }

                $em->flush();

                $dispatcher->dispatch(new InvoiceEvent($invoice), InvoiceEvent::INVOICE_PAID);

                $this->addFlash('success', 'Invoice paid successfully.');
                return $this->redirectToRoute('app_employer_invoices');
            }
        } catch (\Exception $e) {
            $this->addFlash('error', $e->getMessage());
            return $this->redirectToRoute('app_employer_invoice_pay', ['id' => $invoice->getId()]);
        }

        $this->addFlash('error', 'Something went wrong!');
        return $this->redirectToRoute('app_employer_invoices');
    }
}