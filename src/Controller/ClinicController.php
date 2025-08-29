<?php

namespace App\Controller;

use App\Entity\Clinic;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\HttpFoundation\RedirectResponse;

#[Route('/clinic')]
#[IsGranted('ROLE_ADMIN')]
class ClinicController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {}

    #[Route('/', name: 'clinic_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->redirectToRoute('app_my_company');

        /** @var User $user */
        $user = $this->getUser();
        
        // Obtener solo las clínicas que el usuario ha creado
        $clinics = $user->getOwnedClinics();

        return $this->render('clinic/index.html.twig', [
            'clinics' => $clinics,
        ]);
    }

    #[Route('/new', name: 'clinic_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        return $this->redirectToRoute('app_my_company');
        $clinic = new Clinic();
        
        if ($request->isMethod('POST')) {
            $name = $request->request->get('name');
            $address = $request->request->get('address');
            $phone = $request->request->get('phone');
            $email = $request->request->get('email');

            if (empty($name)) {
                $this->addFlash('error', 'El nombre de la clínica es obligatorio.');
                return $this->render('clinic/new.html.twig', ['clinic' => $clinic]);
            }

            $clinic->setName($name);
            $clinic->setAddress($address);
            $clinic->setPhone($phone);
            $clinic->setEmail($email);
            
            // Asignar el usuario actual como creador
            /** @var User $user */
            $user = $this->getUser();
            $clinic->setCreatedBy($user);

            $this->entityManager->persist($clinic);
            $this->entityManager->flush();

            $this->addFlash('success', 'Clínica creada exitosamente.');
            return $this->redirectToRoute('clinic_index');
        }

        return $this->render('clinic/new.html.twig', [
            'clinic' => $clinic,
        ]);
    }

    #[Route('/{id}', name: 'clinic_show', methods: ['GET'])]
    public function show(Clinic $clinic): Response
    {
        return $this->redirectToRoute('app_my_company');
        /** @var User $user */
        $user = $this->getUser();
        
        // Verificar que el usuario puede ver esta clínica
        if (!$user->canEdit($clinic)) {
            $this->addFlash('error', 'No tienes permisos para ver esta clínica.');
            return $this->redirectToRoute('clinic_index');
        }

        return $this->render('clinic/show.html.twig', [
            'clinic' => $clinic,
        ]);
    }

    #[Route('/{id}/edit', name: 'clinic_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Clinic $clinic): Response
    {
        return $this->redirectToRoute('app_my_company');
        /** @var User $user */
        $user = $this->getUser();
        
        // Verificar permisos de edición
        if (!$user->canEdit($clinic)) {
            $this->addFlash('error', 'No tienes permisos para editar esta clínica.');
            return $this->redirectToRoute('clinic_index');
        }

        if ($request->isMethod('POST')) {
            $name = $request->request->get('name');
            $address = $request->request->get('address');
            $phone = $request->request->get('phone');
            $email = $request->request->get('email');

            if (empty($name)) {
                $this->addFlash('error', 'El nombre de la clínica es obligatorio.');
                return $this->render('clinic/edit.html.twig', ['clinic' => $clinic]);
            }

            $clinic->setName($name);
            $clinic->setAddress($address);
            $clinic->setPhone($phone);
            $clinic->setEmail($email);
            $clinic->setUpdatedAt(new \DateTime());

            $this->entityManager->flush();

            $this->addFlash('success', 'Clínica actualizada exitosamente.');
            return $this->redirectToRoute('clinic_show', ['id' => $clinic->getId()]);
        }

        return $this->render('clinic/edit.html.twig', [
            'clinic' => $clinic,
        ]);
    }

    #[Route('/{id}/delete', name: 'clinic_delete', methods: ['POST'])]
    public function delete(Request $request, Clinic $clinic): Response
    {
        return $this->redirectToRoute('app_my_company');
        /** @var User $user */
        $user = $this->getUser();
        
        // Verificar permisos de eliminación
        if (!$user->canEdit($clinic)) {
            $this->addFlash('error', 'No tienes permisos para eliminar esta clínica.');
            return $this->redirectToRoute('clinic_index');
        }

        // Verificar token CSRF para seguridad
        if ($this->isCsrfTokenValid('delete'.$clinic->getId(), $request->request->get('_token'))) {
            // Verificar que no tenga usuarios, profesionales, pacientes o servicios asociados
            if ($clinic->getUsers()->count() > 0 || 
                $clinic->getProfessionals()->count() > 0 || 
                $clinic->getPatients()->count() > 0 || 
                $clinic->getServices()->count() > 0) {
                $this->addFlash('error', 'No se puede eliminar la clínica porque tiene datos asociados.');
                return $this->redirectToRoute('clinic_show', ['id' => $clinic->getId()]);
            }

            $this->entityManager->remove($clinic);
            $this->entityManager->flush();
            
            $this->addFlash('success', 'Clínica eliminada exitosamente.');
        } else {
            $this->addFlash('error', 'Token de seguridad inválido.');
        }

        return $this->redirectToRoute('clinic_index');
    }
}