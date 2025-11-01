<?php

namespace App\Controller\Employer;

use App\Entity\Employer;
use App\Entity\Message;
use App\Entity\Provider;
use App\Entity\User;
use App\Event\MessageEvent;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/employer')]
class MessageController extends AbstractController
{
    #[Route('/messages', name: 'app_employer_messages')]
    public function index(Request $request, EntityManagerInterface $em)
    {
        $user = $this->getUser();
        $type = $request->query->get('type', 'inbox');
        $offset = $request->query->get('page', 1);
        $perPage = $request->get('per_page', 10);
        $filters['keyword'] = $request->query->get('keyword');
        if($type == 'inbox') {
            $filters = [
                'receiver' => $user->getId()
            ];
        }
        if($type == 'sent') {
            $filters = [
                'sender' => $user->getId()
            ];
        }

        $messages = $em->getRepository(Message::class)->getAll($offset, $perPage, $filters);

        return $this->render('employer/message/index.html.twig', [
            'messages' => $messages,
        ]);
    }

    #[Route('/messages/new', name: 'app_employer_messages_new')]
    public function new(
        Request $request,
        EntityManagerInterface $em,
        EventDispatcherInterface $dispatcher,
        SluggerInterface $slugger,
        #[Autowire('%kernel.project_dir%/public/uploads')] string $uploadDirectory
    )
    {
        $user = $this->getUser();

        if($request->isMethod('POST')) {
            $message = new Message();
            $provider = $em->getRepository(User::class)->find($request->get('receiver'));
            $employerUser = $em->getRepository(User::class)->findOneBy(['employer' => $user->getEmployer()]);

            $message->setEmployer($user->getEmployer());
            $message->setReceiver($provider);
            $message->setSender($employerUser);
            $message->setText($request->get('message'));

            if($request->files->get('attachment')) {
                $file = $request->files->get('attachment');

                $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                // this is needed to safely include the file name as part of the URL
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$file->guessExtension();

                // Move the file to the directory where brochures are stored
                try {
                    $file->move($uploadDirectory. '/' . $user->getId(), $newFilename);
                } catch (FileException $e) {
                    // ... handle exception if something happens during file upload
                }

                $message->setAttachment($newFilename);
            }

            $em->persist($message);
            $em->flush();

            $dispatcher->dispatch(new MessageEvent($message), MessageEvent::MESSAGE_CREATED);

            $this->addFlash('success', 'Message has been sent successfully');
            return $this->redirectToRoute('app_employer_messages');
        }

        $providers = $em->getRepository(User::class)->getProvidersForMessage( $user->getEmployer()->getId());

        return $this->render('employer/message/new.html.twig', [
            'providers' => $providers,
        ]);
    }

    #[Route('/messages/{id}/show', name: 'app_employer_message_show', methods: ['GET'])]
    public function show(Message $message, EntityManagerInterface $em)
    {
        $currentEmployer = $this->getUser()->getEmployer();

        if($message->getEmployer() !== $currentEmployer) {
            $this->addFlash('error', "You don't have access to this message.");
            return $this->redirectToRoute('app_employer_messages');
        }

        return $this->render('employer/message/show.html.twig', [
            'message' => $message,
            'replies' => $em->getRepository(Message::class)->findBy(['parent' => $message]),
        ]);
    }

    #[Route('/messages/{id}/reply', name: 'app_employer_message_reply_ajax', methods: ['GET', 'POST'])]
    public function ajaxReply(
        Message $message,
        Request $request,
        EntityManagerInterface $em,
        EventDispatcherInterface $dispatcher,
        SluggerInterface $slugger,
        #[Autowire('%kernel.project_dir%/public/uploads')] string $uploadDirectory
    ): JsonResponse {
        $user = $this->getUser();
        $employerUser = $em->getRepository(User::class)->findOneBy(['employer' => $user->getEmployer()]);

        if ($message->getEmployer() !== $user->getEmployer()) {
            return new JsonResponse(['error' => 'Unauthorized'], 403);
        }

        $reply = new Message();
        $reply->setParent($message);
        $reply->setEmployer($user->getEmployer());
        $reply->setSender($employerUser);
        $reply->setReceiver($message->getSender());
        $reply->setText($request->request->get('message'));

        if ($file = $request->files->get('attachment')) {
            $safeFilename = $slugger->slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
            $newFilename = $safeFilename . '-' . uniqid() . '.' . $file->guessExtension();

            try {
                $file->move($uploadDirectory . '/' . $user->getId(), $newFilename);
            } catch (FileException $e) {
                return new JsonResponse(['error' => 'File upload failed.'], 500);
            }

            $reply->setAttachment($newFilename);
        }

        $em->persist($reply);
        $em->flush();

        $dispatcher->dispatch(new MessageEvent($reply), MessageEvent::MESSAGE_CREATED);

        return new JsonResponse([
            'success' => true,
            'replyHtml' => $this->renderView('employer/message/_reply_item.html.twig', ['reply' => $reply])
        ]);
    }

    #[Route('/messages/{id}/delete', name: 'app_employer_message_delete', methods: ['GET'])]
    public function delete(Message $message, EntityManagerInterface $entityManager)
    {
        $currentEmployer = $this->getUser()->getEmployer();

        if($message->getEmployer() !== $currentEmployer) {
            $this->addFlash('error', "You don't have access to this message.");
            return $this->redirectToRoute('app_employer_messages');
        }

        $entityManager->remove($message);
        $entityManager->flush();

        $this->addFlash('success', 'Message has been deleted successfully');
        return $this->redirectToRoute('app_employer_messages');
    }
}