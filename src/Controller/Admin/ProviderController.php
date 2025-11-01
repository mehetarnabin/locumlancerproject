<?php

namespace App\Controller\Admin;

use App\Entity\Job;
use App\Entity\User;
use App\Repository\JobRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Omines\DataTablesBundle\Adapter\Doctrine\ORMAdapter;
use Omines\DataTablesBundle\Column\BoolColumn;
use Omines\DataTablesBundle\Column\DateTimeColumn;
use Omines\DataTablesBundle\Column\NumberColumn;
use Omines\DataTablesBundle\Column\TextColumn;
use Omines\DataTablesBundle\Column\TwigStringColumn;
use Omines\DataTablesBundle\DataTable;
use Omines\DataTablesBundle\DataTableFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route('/admin/providers')]
class ProviderController extends AbstractController
{
//    #[Route('/', name: 'app_admin_providers')]
//    public function index(Request $request, UserRepository $userRepository): Response
//    {
//        $filters = $request->query->all();
//        $offset = $request->query->get('page', 1);
//        $perPage = $request->get('per_page', 25);
//        $filters['userType'] = User::TYPE_PROVIDER;
//
//        $users = $userRepository->getAll($offset, $perPage, $filters);
//
//        return $this->render('admin/provider/index.html.twig', ['users' => $users]);
//    }

    #[Route('/', name: 'app_admin_providers')]
    public function index(Request $request, DataTableFactory $dataTableFactory): Response
    {
        $table = $dataTableFactory->create()
            ->add('name', TwigStringColumn::class, [
                'label' => 'Name',
                'template' => '<a href="{{ url(\'app_admin_provider_detail\', {id: row.id}) }}">{{ row.name }}</a>',
            ])
            ->add('email', TextColumn::class, ['label' => 'Email'])
            ->add('isVerified', BoolColumn::class, [
                'label' => 'Verified',
                'render' => function ($value) {
                    return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'YES' : 'NO';
                },
            ])
            ->add('blocked', BoolColumn::class, [
                'label' => 'Blocked',
                'render' => function ($value) {
                    return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'YES' : 'NO';
                },
            ])
            ->add('createdAt', DateTimeColumn::class, ['format' => 'm/d/Y', 'label' => 'Created At', 'searchable' => false])
            ->addOrderBy('createdAt', DataTable::SORT_DESCENDING)
            ->createAdapter(ORMAdapter::class, [
                'entity' => User::class,
                'query' => function (QueryBuilder $builder) {
                    $builder
                        ->select('u')
                        ->from(User::class, 'u')
                        ->where('u.userType = :userType')
                        ->setParameter('userType', User::TYPE_PROVIDER);
                    ;
                },
            ])
//            ->add('Actions', TextColumn::class, [
//                'label' => 'Actions',
//                'render' => function($value, $context) {
//                    $linkShow = sprintf('<a href="%s" class="" title="Details"><img src="%s" class="icon-image" /></a>',
//                $this->generateUrl('app_admin_provider_detail', ['id' => $context->getId()]),
//                        '/assets/icons/transparency.png'
//                    );

//                    $isBlocked = $context->isBlocked();
//
//                    if ($isBlocked !== true) {
//                        $linkBlock = sprintf(
//                            '<a href="%s" class="" title="Block" onclick="return confirm(\'Are you sure? Do you want to block this provider?\')"><img src="%s" class="icon-image" /></a>',
//                            $this->generateUrl('app_admin_provider_block', ['id' => $context->getId()]),
//                            '/assets/icons/prohibition.png'
//                        );
//                    }else{
//                        $linkBlock = sprintf(
//                            '<a href="%s" class="" title="Unblock" onclick="return confirm(\'Are you sure? Do you want to unblock this provider?\')"><img src="%s" class="icon-image" /></a>',
//                            $this->generateUrl('app_admin_provider_block', ['id' => $context->getId()]),
//                            '/assets/icons/unlocked.png'
//                        );
//                    }

//                    return $linkShow.' &nbsp; '.$linkBlock;
//                    return $linkShow;
//                }])
            ->handleRequest($request)
        ;

        if ($table->isCallback()) {
            return $table->getResponse();
        }

        return $this->render('admin/provider/index.html.twig', ['datatable' => $table]);
    }

    #[Route('/{id}/detail', name: 'app_admin_provider_detail')]
    public function show(User $user)
    {
        return $this->render('admin/provider/show.html.twig', [
            'user' => $user,
            'provider' => $user->getProvider(),
        ]);
    }

    #[Route('/{id}/block', name: 'app_admin_provider_block')]
    public function block(User $user, EntityManagerInterface $em)
    {
        if($user->isBlocked()) {
            $this->addFlash('success', 'Provider unblocked successfully.');
            $user->setBlocked(false);
        }else{
            $this->addFlash('success', 'Provider blocked successfully.');
            $user->setBlocked(true);
        }

        $em->persist($user);
        $em->flush();

        return $this->redirectToRoute('app_admin_provider_detail', ['id' => $user->getId()]);
    }
}