<?php

namespace App\Controller\Admin;

use App\Entity\Recruiter;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Omines\DataTablesBundle\Adapter\Doctrine\ORMAdapter;
use Omines\DataTablesBundle\Column\BoolColumn;
use Omines\DataTablesBundle\Column\DateTimeColumn;
use Omines\DataTablesBundle\Column\TextColumn;
use Omines\DataTablesBundle\Column\TwigStringColumn;
use Omines\DataTablesBundle\DataTable;
use Omines\DataTablesBundle\DataTableFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route('/admin/recruiters')]
class RecruiterController extends AbstractController
{
    #[Route('/', name: 'app_admin_recruiters')]
    public function index(Request $request, DataTableFactory $dataTableFactory): Response
    {
        $table = $dataTableFactory->create()
            ->add('name', TwigStringColumn::class, [
                'label' => 'Name',
                'template' => '<a href="{{ url(\'app_admin_recruiter_detail\', {id: row.id}) }}">{{ row.recruiter.companyName|default(row.name) }}</a>',
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
                        ->select('u', 'r')
                        ->from(User::class, 'u')
                        ->leftJoin('u.recruiter', 'r')
                        ->where('u.userType = :userType')
                        ->setParameter('userType', User::TYPE_RECRUITER);;
                },
            ])
            ->add('Actions', TextColumn::class, [
                'label' => 'Actions',
                'render' => function ($value, $context) {
                    $linkShow = sprintf(
                        '<a href="%s" class="" title="Details"><img src="%s" class="icon-image" /></a>',
                        $this->generateUrl('app_admin_recruiter_detail', ['id' => $context->getId()]),
                        '/assets/icons/transparency.png'
                    );

                    return $linkShow;
                }
            ])
            ->handleRequest($request);

        if ($table->isCallback()) {
            return $table->getResponse();
        }

        return $this->render('admin/recruiter/index.html.twig', ['datatable' => $table]);
    }

    #[Route('/{id}/detail', name: 'app_admin_recruiter_detail')]
    public function show(User $user)
    {
        return $this->render('admin/recruiter/show.html.twig', [
            'user' => $user,
            'recruiter' => $user->getRecruiter(),
        ]);
    }

    #[Route('/{id}/block', name: 'app_admin_recruiter_block')]
    public function block(User $user, EntityManagerInterface $em)
    {
        if ($user->isBlocked()) {
            $this->addFlash('success', 'Recruiter unblocked successfully.');
            $user->setBlocked(false);
        } else {
            $this->addFlash('success', 'Recruiter blocked successfully.');
            $user->setBlocked(true);
        }

        $em->persist($user);
        $em->flush();

        return $this->redirectToRoute('app_admin_recruiter_detail', ['id' => $user->getId()]);
    }
}
