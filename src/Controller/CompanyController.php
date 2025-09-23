<?php

namespace App\Controller;

use App\Entity\Company;
use App\Entity\User;
use App\Form\CompanyType;
use App\Service\ImageUploadService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/configuracion')]
#[IsGranted('ROLE_ADMIN')]
class CompanyController extends AbstractController
{
    public function __construct(
        private ImageUploadService $imageUploadService
    ) {}

    #[Route('/', name: 'app_company_config', methods: ['GET', 'POST'])]
    public function config(Request $request, EntityManagerInterface $entityManager): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $company = $user->getCompany();
        
        if (!$company) {
            $this->addFlash('error', 'Debe estar asociado a una empresa para acceder a la configuración.');
            return $this->redirectToRoute('app_index');
        }

        $form = $this->createForm(CompanyType::class, $company);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Manejar subida de logo
            $logoFile = $form->get('logoFile')->getData();
            if ($logoFile) {
                try {
                    // Eliminar logo anterior si existe
                    if ($company->getLogoUrl()) {
                        $this->imageUploadService->deleteImage($company->getLogoUrl());
                    }
                    
                    $logoUrl = $this->imageUploadService->uploadCompanyLogo($logoFile, $company->getId());
                    $company->setLogoUrl($logoUrl);
                } catch (\Exception $e) {
                    $this->addFlash('error', 'Error al subir el logo: ' . $e->getMessage());
                }
            }

            // Manejar subida de portada
            $coverFile = $form->get('coverFile')->getData();
            if ($coverFile) {
                try {
                    // Eliminar portada anterior si existe
                    if ($company->getCoverUrl()) {
                        $this->imageUploadService->deleteImage($company->getCoverUrl());
                    }
                    
                    $coverUrl = $this->imageUploadService->uploadCompanyCover($coverFile, $company->getId());
                    $company->setCoverUrl($coverUrl);
                } catch (\Exception $e) {
                    $this->addFlash('error', 'Error al subir la portada: ' . $e->getMessage());
                }
            }

            $company->setUpdatedAt(new \DateTime());
            $entityManager->flush();

            $this->addFlash('success', 'La configuración de la empresa se ha actualizado correctamente.');
            return $this->redirectToRoute('app_company_config');
        }

        return $this->render('company/config.html.twig', [
            'company' => $company,
            'form' => $form->createView(),
        ]);
    }
}