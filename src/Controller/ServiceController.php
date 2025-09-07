<?php

namespace App\Controller;

use App\Entity\Service;
use App\Entity\User;
use App\Form\ServiceType;
use App\Repository\ServiceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/servicios')]
#[IsGranted('ROLE_ADMIN')]
class ServiceController extends AbstractController
{
    #[Route('/', name: 'app_service_index', methods: ['GET'])]
    public function index(Request $request, ServiceRepository $serviceRepository): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $company = $user->getCompany();
        
        if (!$company) {
            $this->addFlash('error', 'Debe estar asociado a una empresa para gestionar servicios.');
            return $this->redirectToRoute('app_dashboard');
        }

        // Obtener el parámetro de búsqueda
        $search = $request->query->get('search', '');
        
        // Buscar servicios con filtro por nombre si se proporciona
        if (!empty($search)) {
            $services = $serviceRepository->findByNameAndCompany($search, $company);
        } else {
            // Ordenar por activo primero, luego por nombre
            $services = $serviceRepository->createQueryBuilder('s')
                ->where('s.company = :company')
                ->andWhere('s.active = :active')
                ->setParameter('company', $company)
                ->setParameter('active', true)
                ->orderBy('s.name', 'ASC')
                ->getQuery()
                ->getResult();
        }

        return $this->render('service/index.html.twig', [
            'services' => $services,
            'company' => $company,
            'search' => $search,
        ]);
    }

    #[Route('/new', name: 'app_service_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $company = $user->getCompany();
        
        if (!$company) {
            $this->addFlash('error', 'Debe estar asociado a una empresa para gestionar servicios.');
            return $this->redirectToRoute('app_dashboard');
        }
        
        $service = new Service();
        $service->setCompany($company);
        $service->setActive(true);
        
        $form = $this->createForm(ServiceType::class, $service, ['is_edit' => false]);
        $form->handleRequest($request);
        
        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                $service->setUpdatedAt(new \DateTime());
                $entityManager->persist($service);
                $entityManager->flush();
                
                $this->addFlash('success', 'Servicio creado exitosamente.');
                return $this->redirectToRoute('app_service_index');
            } else {
                foreach ($form->getErrors(true) as $error) {
                    $this->addFlash('error', $error->getMessage());
                }
            }
        }
        
        return $this->render('service/form.html.twig', [
            'form' => $form,
            'service' => $service,
            'company' => $company,
            'is_edit' => false
        ]);
    }

    #[Route('/new/form', name: 'app_service_new_form', methods: ['GET'])]
    public function newForm(): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $company = $user->getCompany();
        
        if (!$company) {
            return new Response('<div class="alert alert-danger">Debe estar asociado a una empresa para gestionar servicios.</div>');
        }
        
        $service = new Service();
        $service->setCompany($company);
        $service->setActive(true);
        
        $form = $this->createForm(ServiceType::class, $service, ['is_edit' => false]);
        
        return $this->render('service/form.html.twig', [
            'form' => $form,
            'service' => $service,
            'company' => $company,
            'is_edit' => false
        ]);
    }

    #[Route('/{id}', name: 'app_service_show', methods: ['GET'])]
    public function show(Service $service): Response
    {
        if (!$this->canAccess($service)) {
            $this->addFlash('error', 'No tiene permisos para ver este servicio.');
            return $this->redirectToRoute('app_service_index');
        }

        return $this->render('service/show.html.twig', [
            'service' => $service,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_service_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Service $service, EntityManagerInterface $entityManager): Response
    {
        if (!$this->canAccess($service)) {
            $this->addFlash('error', 'No tiene permisos para editar este servicio.');
            return $this->redirectToRoute('app_service_index');
        }
        
        $form = $this->createForm(ServiceType::class, $service, ['is_edit' => true]);
        $form->handleRequest($request);
        
        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                $service->setUpdatedAt(new \DateTime());
                $entityManager->flush();
                
                $this->addFlash('success', 'Servicio actualizado exitosamente.');
                return $this->redirectToRoute('app_service_index');
            } else {
                foreach ($form->getErrors(true) as $error) {
                    $this->addFlash('error', $error->getMessage());
                }
            }
        }
        
        return $this->render('service/form.html.twig', [
            'form' => $form,
            'service' => $service,
            'company' => $service->getCompany(),
            'is_edit' => true
        ]);
    }

    #[Route('/{id}/form', name: 'app_service_edit_form', methods: ['GET'])]
    public function editForm(Service $service): Response
    {
        if (!$this->canAccess($service)) {
            throw $this->createAccessDeniedException('No tiene permisos para editar este servicio.');
        }
        
        $form = $this->createForm(ServiceType::class, $service, ['is_edit' => true]);
        
        return $this->render('service/form.html.twig', [
            'form' => $form,
            'service' => $service,
            'company' => $service->getCompany(),
            'is_edit' => true
        ]);
    }

    #[Route('/{id}/details', name: 'app_service_details', methods: ['GET'])]
    public function getDetails(Service $service): JsonResponse
    {
        if (!$this->canAccess($service)) {
            return new JsonResponse(['error' => 'No autorizado'], 403);
        }
    
        return new JsonResponse([
            'id' => $service->getId(),
            'name' => $service->getName(),
            'description' => $service->getDescription(),
            'duration' => $service->getDefaultDurationMinutes(),
            'price' => $service->getPriceAsFloat(),
            'active' => $service->isActive(),
            'professionals_count' => $service->getProfessionalServices()->count(),
            'delivery_type' => $service->getDeliveryType()?->getLabel(),
            'service_type' => $service->getServiceType()?->getLabel(),
            'frequency_weeks' => $service->getFrequencyWeeks(),
            'reminder_note' => $service->getReminderNote(),
            'online_booking_enabled' => $service->isOnlineBookingEnabled()
        ]);
    }

    #[Route('/{id}', name: 'app_service_delete', methods: ['POST'])]
    public function delete(Request $request, Service $service, EntityManagerInterface $entityManager): Response
    {
        if (!$this->canAccess($service)) {
            $this->addFlash('error', 'No tiene permisos para eliminar este servicio.');
            return $this->redirectToRoute('app_service_index');
        }

        if ($this->isCsrfTokenValid('delete'.$service->getId(), $request->request->get('_token'))) {
            // Borrado soft: desactivar en lugar de eliminar
            $service->setActive(false);
            $service->setUpdatedAt(new \DateTime());
            
            $entityManager->flush();
            $this->addFlash('success', 'Servicio desactivado exitosamente.');
        } else {
            $this->addFlash('error', 'Token de seguridad inválido.');
        }

        return $this->redirectToRoute('app_service_index');
    }

    #[Route('/{id}/reactivate', name: 'app_service_reactivate', methods: ['POST'])]
    public function reactivate(Request $request, Service $service, EntityManagerInterface $entityManager): Response
    {
        if (!$this->canAccess($service)) {
            $this->addFlash('error', 'No tiene permisos para reactivar este servicio.');
            return $this->redirectToRoute('app_service_index');
        }

        if ($this->isCsrfTokenValid('reactivate'.$service->getId(), $request->request->get('_token'))) {
            $service->setActive(true);
            $service->setUpdatedAt(new \DateTime());
            
            $entityManager->flush();
            $this->addFlash('success', 'Servicio reactivado exitosamente.');
        } else {
            $this->addFlash('error', 'Token de seguridad inválido.');
        }

        return $this->redirectToRoute('app_service_index');
    }

    private function canAccess(Service $service): bool
    {
        /** @var User $user */
        $user = $this->getUser();
        $userCompany = $user->getCompany();
        
        return $userCompany && $service->getCompany() === $userCompany;
    }
}