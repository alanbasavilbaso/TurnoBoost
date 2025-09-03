<?php

namespace App\Controller;

use App\Entity\Service;
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
        $user = $this->getUser();
        $location = $user->getOwnedLocations()->first();
        
        if (!$location) {
            $this->addFlash('error', 'Debe crear un local antes de gestionar servicios.');
            return $this->redirectToRoute('location_index');
        }

        // Obtener el parámetro de búsqueda
        $search = $request->query->get('search', '');
        
        // Buscar servicios con filtro por nombre si se proporciona
        if (!empty($search)) {
            $services = $serviceRepository->findByNameAndLocation($search, $location);
        } else {
            $services = $serviceRepository->findBy(['location' => $location], ['name' => 'ASC']);
        }

        return $this->render('service/index.html.twig', [
            'services' => $services,
            'location' => $location,
            'search' => $search,
        ]);
    }

    #[Route('/new', name: 'app_service_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        $location = $user->getOwnedLocations()->first();
        
        if (!$location) {
            $this->addFlash('error', 'Debe crear un local antes de gestionar servicios.');
            return $this->redirectToRoute('location_index');
        }
        
        $service = new Service();
        $service->setLocation($location);
        $service->setActive(true); // Por defecto activo
        
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
                // Mostrar errores de validación como flash messages
                foreach ($form->getErrors(true) as $error) {
                    $this->addFlash('error', $error->getMessage());
                }
            }
        }
        
        return $this->render('service/form.html.twig', [
            'form' => $form,
            'service' => $service,
            'location' => $location,
            'is_edit' => false
        ]);
    }

    #[Route('/new/form', name: 'app_service_new_form', methods: ['GET'])]
    public function newForm(): Response
    {
        $user = $this->getUser();
        $location = $user->getOwnedLocations()->first();
        
        if (!$location) {
            return new Response('<div class="alert alert-danger">Debe crear un local antes de gestionar servicios.</div>');
        }
        
        $service = new Service();
        $service->setLocation($location);
        $service->setActive(true);
        
        $form = $this->createForm(ServiceType::class, $service, ['is_edit' => false]);
        
        return $this->render('service/form.html.twig', [
            'form' => $form,
            'service' => $service,
            'location' => $location,
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
                // Mostrar errores de validación como flash messages
                foreach ($form->getErrors(true) as $error) {
                    $this->addFlash('error', $error->getMessage());
                }
            }
        }
        
        return $this->render('service/form.html.twig', [
            'form' => $form,
            'service' => $service,
            'location' => $service->getLocation(),
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
            'location' => $service->getLocation(),
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
            // Verificar si tiene citas asociadas
            if ($service->getAppointments()->count() > 0) {
                $this->addFlash('error', 'No se puede eliminar el servicio porque tiene citas asociadas.');
                return $this->redirectToRoute('app_service_index');
            }

            $entityManager->remove($service);
            $entityManager->flush();
            $this->addFlash('success', 'Servicio eliminado exitosamente.');
        }

        return $this->redirectToRoute('app_service_index');
    }

    private function canAccess(Service $service): bool
    {
        $user = $this->getUser();
        $userLocation = $user->getOwnedLocations()->first();
        
        return $userLocation && $service->getLocation() === $userLocation;
    }
}