<?php

namespace App\Controller\Admin;

use App\Entity\Package;
use App\Form\PackageType;
use App\Repository\PackageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/package')]
class PackageController extends AbstractController
{
    #[Route('/', name: 'admin_package_index', methods: ['GET'])]
    public function index(PackageRepository $packageRepository): Response
    {
        return $this->render('admin/package/index.html.twig', [
            'packages' => $packageRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'admin_package_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $package = new Package();
        $form = $this->createForm(PackageType::class, $package);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($package);
            $entityManager->flush();

            $this->addFlash('success', 'Package created successfully!');
            return $this->redirectToRoute('admin_package_index', [], Response::HTTP_SEE_OTHER);
        }

        // Show form errors if submitted but invalid
        if ($form->isSubmitted() && !$form->isValid()) {
            $errors = [];
            foreach ($form->getErrors(true) as $error) {
                $errors[] = $error->getMessage();
            }
            $this->addFlash('error', 'Please fix the following errors: ' . implode(', ', $errors));
        }

        return $this->render('admin/package/new.html.twig', [
            'package' => $package,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'admin_package_show', methods: ['GET'])]
    public function show(Package $package): Response
    {
        return $this->render('admin/package/show.html.twig', [
            'package' => $package,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_package_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Package $package, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(PackageType::class, $package);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'Package updated successfully!');

            return $this->redirectToRoute('admin_package_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/package/edit.html.twig', [
            'package' => $package,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'admin_package_delete', methods: ['POST'])]
    public function delete(Request $request, Package $package, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$package->getId(), $request->request->get('_token'))) {
            $entityManager->remove($package);
            $entityManager->flush();

            $this->addFlash('success', 'Package deleted successfully!');
        }

        return $this->redirectToRoute('admin_package_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/toggle-active', name: 'admin_package_toggle_active', methods: ['POST'])]
    public function toggleActive(Package $package, EntityManagerInterface $entityManager): Response
    {
        $package->setIsActive(!$package->isActive());
        $entityManager->flush();

        $status = $package->isActive() ? 'activated' : 'deactivated';
        $this->addFlash('success', "Package {$status} successfully!");

        return $this->redirectToRoute('admin_package_index');
    }

    #[Route('/{id}/set-default', name: 'admin_package_set_default', methods: ['POST'])]
    public function setDefault(Package $package, EntityManagerInterface $entityManager, PackageRepository $packageRepository): Response
    {
        // Remove default from all packages of the same target
        $defaultPackages = $packageRepository->findBy([
            'isDefault' => true,
            'target' => $package->getTarget()
        ]);
        
        foreach ($defaultPackages as $defaultPackage) {
            $defaultPackage->setIsDefault(false);
        }

        // Set this package as default
        $package->setIsDefault(true);
        $entityManager->flush();

        $this->addFlash('success', 'Default package set successfully!');

        return $this->redirectToRoute('admin_package_index');
    }
}