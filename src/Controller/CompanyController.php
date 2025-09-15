<?php

namespace App\Controller;

use App\Entity\Company;
use App\Entity\User;
use App\Form\CompanyType;
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