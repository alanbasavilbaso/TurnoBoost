<?php

namespace App\Controller;

use App\Entity\Professional;
use App\Entity\ProfessionalAvailability;
use App\Form\ProfessionalType;
use App\Repository\ProfessionalRepository;
use App\Repository\ServiceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/profesionales')]
#[IsGranted('ROLE_ADMIN')]
class ProfessionalController extends AbstractController
{
    private RequestStack $requestStack;
    private ServiceRepository $serviceRepository;
    
    public function __construct(RequestStack $requestStack, ServiceRepository $serviceRepository)
    {
        $this->requestStack = $requestStack;
        $this->serviceRepository = $serviceRepository;
    }

    #[Route('/', name: 'app_professional_index', methods: ['GET'])]
    public function index(ProfessionalRepository $professionalRepository): Response
    {
        $user = $this->getUser();
        $company = $user->getCompany();
        
        if (!$company) {
            $this->addFlash('error', 'No tiene una empresa asignada.');
            return $this->redirectToRoute('app_dashboard');
        }
    
        // Filtrar solo profesionales activos de la empresa
        $professionals = $professionalRepository->findBy([
            'company' => $company,
            'active' => true
        ]);
    
        return $this->render('professional/index.html.twig', [
            'professionals' => $professionals,
            'company' => $company
        ]);
    }

    #[Route('/new', name: 'app_professional_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        $company = $user->getCompany();
        
        if (!$company) {
            $this->addFlash('error', 'Debe tener una empresa asignada antes de agregar profesionales.');
            return $this->redirectToRoute('app_dashboard');
        }

        $professional = new Professional();
        $professional->setCompany($company);
        $professional->setCreatedAt(new \DateTime());
        $professional->setUpdatedAt(new \DateTime());
        
        $form = $this->createForm(ProfessionalType::class, $professional, [
            'company' => $company,
            'is_edit' => false
        ]);
        
        // Establecer valores por defecto para nuevo profesional
        for ($day = 0; $day <= 6; $day++) {
            if ($day !== 6) { // No domingo
                $form->get("availability_{$day}_enabled")->setData(true);
                $form->get("availability_{$day}_range1_start")->setData('09:00');
                $form->get("availability_{$day}_range1_end")->setData('18:00');
            }
        }
        
        // NUEVO: Preseleccionar todos los servicios activos
        $allServices = $this->serviceRepository->findActiveByCompany($company);
        $form->get('services')->setData($allServices);
        
        $form->handleRequest($request);
    
        if ($form->isSubmitted() && $form->isValid()) {
            // Procesar horarios de disponibilidad
            $this->processAvailabilityData($form, $professional, $entityManager);
            
            // Procesar servicios seleccionados
            $this->processServices($form, $professional, $entityManager);
            
            $entityManager->persist($professional);
            $entityManager->flush();

            $this->addFlash('success', 'Profesional creado exitosamente.');
            return $this->redirectToRoute('app_professional_index');
        }

        return $this->render('professional/form.html.twig', [
            'professional' => $professional,
            'form' => $form,
            'company' => $company,
            'services' => $this->serviceRepository->findBy([
                'company' => $company,
                'active' => true
            ]),
            'is_edit' => false,
            'existing_service_configs' => [],
        ]);
    }

    #[Route('/{id}/edit', name: 'app_professional_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Professional $professional, EntityManagerInterface $entityManager): Response
    {
        if (!$this->canAccess($professional)) {
            $this->addFlash('error', 'No tiene permisos para editar este profesional.');
            return $this->redirectToRoute('app_professional_index');
        }
    
        $company = $professional->getCompany();
        
        $form = $this->createForm(ProfessionalType::class, $professional, [
            'company' => $company,
            'is_edit' => true
        ]);
        
        // Precargar servicios actuales
        $currentServices = $professional->getServices()->toArray();
        $form->get('services')->setData($currentServices);
        
        // Pre-llenar datos de disponibilidad existentes
        $this->populateAvailabilityData($form, $professional);
        
        // AGREGAR: Pre-llenar configuraciones de servicios existentes
        $serviceConfigs = $this->populateServiceConfigs($form, $professional);
        
        $form->handleRequest($request);
    
        if ($form->isSubmitted()) {
            // Debug: Mostrar errores de validación
            if (!$form->isValid()) {
                error_log('Form is not valid. Errors:');
                foreach ($form->getErrors(true) as $error) {
                    error_log('Form error: ' . $error->getMessage());
                }
                
                // Mostrar errores específicos en desarrollo
                if ($this->getParameter('kernel.environment') === 'dev') {
                    $errors = [];
                    foreach ($form->getErrors(true) as $error) {
                        $errors[] = $error->getMessage();
                    }
                    $this->addFlash('error', 'Errores de validación: ' . implode(', ', $errors));
                } else {
                    $this->addFlash('error', 'Hay errores en el formulario. Por favor, revise los datos.');
                }
            } else {
                try {
                    $professional->setUpdatedAt(new \DateTime());
                    
                    // Eliminar disponibilidades existentes
                    foreach ($professional->getAvailabilities() as $availability) {
                        $entityManager->remove($availability);
                    }
                    
                    // Procesar horarios de disponibilidad
                    $this->processAvailabilityData($form, $professional, $entityManager);
                    
                    // Procesar servicios seleccionados
                    // En el método edit/create, después de procesar availability:
                    $this->processServices($form, $professional, $entityManager);
                    
                    $entityManager->flush();
                    
                    $this->addFlash('success', 'Profesional actualizado exitosamente.');
                    return $this->redirectToRoute('app_professional_index');
                } catch (\Exception $e) {
                    // Log del error completo siempre
                    error_log('Error al actualizar profesional: ' . $e->getMessage());
                    error_log('Stack trace: ' . $e->getTraceAsString());
                    
                    // Mostrar mensaje específico solo en desarrollo
                    if ($this->getParameter('kernel.environment') === 'dev') {
                        $this->addFlash('error', 'Error al actualizar el profesional: ' . $e->getMessage());
                    } else {
                        $this->addFlash('error', 'Error al actualizar el profesional. Por favor, inténtelo de nuevo.');
                    }
                }
            }
        }
        
        return $this->render('professional/form.html.twig', [
            'professional' => $professional,
            'form' => $form,
            'company' => $company,
            'services' => $this->serviceRepository->findBy([
                'company' => $company,
                'active' => true
            ]),
            'is_edit' => true,
            'existing_service_configs' => $serviceConfigs,
        ]);
    }

    #[Route('/{id}', name: 'app_professional_show', methods: ['GET'])]
    public function show(Professional $professional): Response
    {
        if (!$this->canAccess($professional)) {
            $this->addFlash('error', 'No tiene permisos para ver este profesional.');
            return $this->redirectToRoute('app_professional_index');
        }

        return $this->render('professional/show.html.twig', [
            'professional' => $professional,
        ]);
    }

    #[Route('/{id}', name: 'app_professional_delete', methods: ['POST'])]
    public function delete(Request $request, Professional $professional, EntityManagerInterface $entityManager): Response
    {
        if (!$this->canAccess($professional)) {
            $this->addFlash('error', 'No tiene permisos para eliminar este profesional.');
            return $this->redirectToRoute('app_professional_index');
        }
    
        if ($this->isCsrfTokenValid('delete'.$professional->getId(), $request->request->get('_token'))) {
            // Soft delete: marcar como inactivo
            $professional->setActive(false);
            $professional->setUpdatedAt(new \DateTime());
            
            $entityManager->flush();
            $this->addFlash('success', 'Profesional eliminado exitosamente.');
        } else {
            $this->addFlash('error', 'Token CSRF inválido.');
        }
    
        return $this->redirectToRoute('app_professional_index');
    }

    private function canAccess(Professional $professional): bool
    {
        $user = $this->getUser();
        $userCompany = $user->getCompany();
        
        return $userCompany && $professional->getCompany() === $userCompany;
    }

    #[Route('/new/form', name: 'app_professional_new_form', methods: ['GET'])]
    public function newForm(Request $request): Response
    {
        $user = $this->getUser();
        $company = $user->getCompany();
        
        if (!$company) {
            return new Response('Debe tener una empresa asignada antes de agregar profesionales.', 400);
        }

        $professional = new Professional();
        $professional->setCompany($company);
        $professional->setCreatedAt(new \DateTime());
        $professional->setUpdatedAt(new \DateTime());
        
        $form = $this->createForm(ProfessionalType::class, $professional, [
            'company' => $company,
            'is_edit' => false
        ]);
        
        // Establecer valores por defecto para nuevo profesional
        for ($day = 0; $day <= 6; $day++) {
            if ($day !== 6) { // No domingo
                $form->get("availability_{$day}_enabled")->setData(true);
                $form->get("availability_{$day}_range1_start")->setData('09:00');
                $form->get("availability_{$day}_range1_end")->setData('18:00');
            }
        }
        
        // NUEVO: Preseleccionar todos los servicios activos
        $allServices = $this->serviceRepository->findActiveByCompany($company);
        $form->get('services')->setData($allServices);
        
        $form->handleRequest($request);
    
        if ($form->isSubmitted() && $form->isValid()) {
            // Procesar horarios de disponibilidad
            $this->processAvailabilityData($form, $professional, $entityManager);
            
            // Procesar servicios seleccionados
            $this->processServices($form, $professional, $entityManager);
            
            $entityManager->persist($professional);
            $entityManager->flush();

            $this->addFlash('success', 'Profesional creado exitosamente.');
            return $this->redirectToRoute('app_professional_index');
        }

        return $this->render('professional/form.html.twig', [
            'professional' => $professional,
            'form' => $form,
            'company' => $company,
            'services' => $this->serviceRepository->findBy([
                'company' => $company,
                'active' => true
            ]),
            'is_edit' => false,
            'existing_service_configs' => [],
        ]);
    }

    #[Route('/{id}/form', name: 'app_professional_form', methods: ['GET'])]
    public function form(Professional $professional, Request $request): Response
    {
        if (!$this->canAccess($professional)) {
            return new Response('No tiene permisos para editar este profesional.', 403);
        }

        $user = $this->getUser();
        $company = $user->getCompany();
        
        $form = $this->createForm(ProfessionalType::class, $professional, [
            'company' => $company,
            'is_edit' => true
        ]);
        
        // Precargar servicios actuales
        $currentServices = $professional->getServices()->toArray();
        $form->get('services')->setData($currentServices);
        
        // Pre-llenar datos de disponibilidad existentes
        $this->populateAvailabilityData($form, $professional);
        
        // AGREGAR: Pre-llenar configuraciones de servicios existentes
        $serviceConfigs = $this->populateServiceConfigs($form, $professional);
        
        $form->handleRequest($request);
    
        if ($form->isSubmitted()) {
            // Debug: Mostrar errores de validación
            if (!$form->isValid()) {
                error_log('Form is not valid. Errors:');
                foreach ($form->getErrors(true) as $error) {
                    error_log('Form error: ' . $error->getMessage());
                }
                
                // Mostrar errores específicos en desarrollo
                if ($this->getParameter('kernel.environment') === 'dev') {
                    $errors = [];
                    foreach ($form->getErrors(true) as $error) {
                        $errors[] = $error->getMessage();
                    }
                    $this->addFlash('error', 'Errores de validación: ' . implode(', ', $errors));
                } else {
                    $this->addFlash('error', 'Hay errores en el formulario. Por favor, revise los datos.');
                }
            } else {
                try {
                    $professional->setUpdatedAt(new \DateTime());
                    
                    // Eliminar disponibilidades existentes
                    foreach ($professional->getAvailabilities() as $availability) {
                        $entityManager->remove($availability);
                    }
                    
                    // Procesar horarios de disponibilidad
                    $this->processAvailabilityData($form, $professional, $entityManager);
                    
                    // Procesar servicios seleccionados
                    // En el método edit/create, después de procesar availability:
                    $this->processServices($form, $professional, $entityManager);
                    
                    $entityManager->flush();
                    
                    $this->addFlash('success', 'Profesional actualizado exitosamente.');
                    return $this->redirectToRoute('app_professional_index');
                } catch (\Exception $e) {
                    // Log del error completo siempre
                    error_log('Error al actualizar profesional: ' . $e->getMessage());
                    error_log('Stack trace: ' . $e->getTraceAsString());
                    
                    // Mostrar mensaje específico solo en desarrollo
                    if ($this->getParameter('kernel.environment') === 'dev') {
                        $this->addFlash('error', 'Error al actualizar el profesional: ' . $e->getMessage());
                    } else {
                        $this->addFlash('error', 'Error al actualizar el profesional. Por favor, inténtelo de nuevo.');
                    }
                }
            }
        }
        
        return $this->render('professional/form.html.twig', [
            'professional' => $professional,
            'form' => $form,
            'company' => $company,
            'services' => $this->serviceRepository->findBy([
                'company' => $company,
                'active' => true
            ]),
            'is_edit' => true,
            'existing_service_configs' => $serviceConfigs,
        ]);
    }

    private function processAvailabilityData(FormInterface $form, Professional $professional, EntityManagerInterface $entityManager): void
    {
        for ($day = 0; $day <= 6; $day++) {
            $enabled = $form->get("availability_{$day}_enabled")->getData();
            
            if ($enabled) {
                // Procesar hasta 2 rangos por día
                for ($range = 1; $range <= 2; $range++) {
                    $startTimeString = $form->get("availability_{$day}_range{$range}_start")->getData();
                    $endTimeString = $form->get("availability_{$day}_range{$range}_end")->getData();
                    
                    if ($startTimeString && $endTimeString) {
                        // Convertir strings a objetos DateTime
                        $startTime = \DateTime::createFromFormat('H:i', $startTimeString);
                        $endTime = \DateTime::createFromFormat('H:i', $endTimeString);
                        
                        if ($startTime && $endTime) {
                            $availability = new ProfessionalAvailability();
                            $availability->setProfessional($professional);
                            $availability->setWeekday($day);
                            $availability->setStartTime($startTime);
                            $availability->setEndTime($endTime);
                            
                            $entityManager->persist($availability);
                        }
                    }
                }
            }
        }
    }

    private function populateAvailabilityData(FormInterface $form, Professional $professional): void
    {
        $availabilitiesByDay = [];
        
        // Debug: Verificar cuántas disponibilidades tiene el profesional
        $availabilities = $professional->getAvailabilities();
        error_log("Professional ID: " . $professional->getId());
        error_log("Total availabilities: " . count($availabilities));
        
        
        // Agrupar disponibilidades por día
        foreach ($availabilities as $availability) {
            $day = $availability->getWeekday();
            error_log("Found availability for day: " . $day . " from " . $availability->getStartTime()->format('H:i') . " to " . $availability->getEndTime()->format('H:i'));
            
            if (!isset($availabilitiesByDay[$day])) {
                $availabilitiesByDay[$day] = [];
            }
            $availabilitiesByDay[$day][] = $availability;
        }
        
        error_log("Grouped availabilities: " . json_encode(array_keys($availabilitiesByDay)));
        
        // Llenar formulario
        foreach ($availabilitiesByDay as $day => $availabilities) {
            error_log("Setting day {$day} as enabled");
            
            try {
                $form->get("availability_{$day}_enabled")->setData(true);
                // var_dump("availability_{$day}_enabled");exit;
                // Ordenar por hora de inicio
                usort($availabilities, function($a, $b) {
                    return $a->getStartTime() <=> $b->getStartTime();
                });
                
                // Asignar a rangos (máximo 2)
                for ($range = 1; $range <= min(2, count($availabilities)); $range++) {
                    $availability = $availabilities[$range - 1];
                    $startTime = $availability->getStartTime()->format('H:i');
                    $endTime = $availability->getEndTime()->format('H:i');
                    
                    error_log("Setting day {$day} range {$range}: {$startTime} - {$endTime}");
                    
                    // Convertir DateTime a string para el formulario
                    $form->get("availability_{$day}_range{$range}_start")->setData($startTime);
                    $form->get("availability_{$day}_range{$range}_end")->setData($endTime);
                }
            } catch (\Exception $e) {
                error_log("Error setting form data for day {$day}: " . $e->getMessage());
            }
        }
    }
    
    private function processServices(FormInterface $form, Professional $professional, EntityManagerInterface $entityManager): void {
        // Obtener configuraciones de servicios desde los inputs hidden
        $request = $this->requestStack->getCurrentRequest();
        $serviceConfigs = $request->request->all('service_configs') ?? [];
        
        // Obtener la empresa del usuario logueado
        $company = $this->getUser()->getCompany();
        
        $serviceRepository = $entityManager->getRepository(\App\Entity\Service::class);
        $selectedServices = [];

        // Obtener todos los servicios de una vez (más eficiente)
        $serviceIds = array_keys($serviceConfigs);
        $selectedServices = $serviceRepository->findBy([
            'id' => $serviceIds,
            'company' => $company,
            'active' => true
        ]);
        
        
        // Eliminar todas las asociaciones existentes
        foreach ($professional->getProfessionalServices() as $professionalService) {
            $entityManager->remove($professionalService);
        }
        $professional->getProfessionalServices()->clear();
        
        // Crear nuevas asociaciones
        if ($selectedServices) {
            foreach ($selectedServices as $service) {
                $professionalService = new \App\Entity\ProfessionalService();
                $professionalService->setProfessional($professional);
                $professionalService->setService($service);
                
                $serviceId = $service->getId();
                
                // Configurar duración y precio
                if (isset($serviceConfigs[$serviceId]['duration'])) {
                    $professionalService->setCustomDurationMinutes((int)$serviceConfigs[$serviceId]['duration']);
                } else {
                    $professionalService->setCustomDurationMinutes($service->getDefaultDurationMinutes());
                }
                
                if (isset($serviceConfigs[$serviceId]['price'])) {
                    $professionalService->setCustomPrice((float)$serviceConfigs[$serviceId]['price']);
                } else {
                    $professionalService->setCustomPrice($service->getPrice());
                }
                
                // Procesar configuración de días
                $dayMapping = [
                    0 => 'setAvailableMonday',
                    1 => 'setAvailableTuesday', 
                    2 => 'setAvailableWednesday',
                    3 => 'setAvailableThursday',
                    4 => 'setAvailableFriday',
                    5 => 'setAvailableSaturday',
                    6 => 'setAvailableSunday'
                ];
                
                // Establecer todos los días como false primero
                foreach ($dayMapping as $method) {
                    $professionalService->$method(false);
                }
                
                // Establecer los días seleccionados como true
                if (isset($serviceConfigs[$serviceId]['days']) && is_array($serviceConfigs[$serviceId]['days'])) {
                    foreach ($serviceConfigs[$serviceId]['days'] as $day) {
                        if (isset($dayMapping[$day])) {
                            $professionalService->{$dayMapping[$day]}(true);
                        }
                    }
                } else {
                    // Si no hay configuración específica, usar todos los días como true por defecto
                    $professionalService->setAvailableMonday(true);
                    $professionalService->setAvailableTuesday(true);
                    $professionalService->setAvailableWednesday(true);
                    $professionalService->setAvailableThursday(true);
                    $professionalService->setAvailableFriday(true);
                    $professionalService->setAvailableSaturday(true);
                    $professionalService->setAvailableSunday(true);
                }
                
                $entityManager->persist($professionalService);
                $professional->addProfessionalService($professionalService);
            }
        }
    }

    private function populateServiceConfigs(FormInterface $form, Professional $professional): array
    {
        $serviceConfigs = [];
        
        // Obtener todas las configuraciones de servicios del profesional
        foreach ($professional->getProfessionalServices() as $professionalService) {
            $serviceId = $professionalService->getService()->getId();
            
            // Crear array de días seleccionados
            $selectedDays = [];
            if ($professionalService->isAvailableMonday()) $selectedDays[] = 0;
            if ($professionalService->isAvailableTuesday()) $selectedDays[] = 1;
            if ($professionalService->isAvailableWednesday()) $selectedDays[] = 2;
            if ($professionalService->isAvailableThursday()) $selectedDays[] = 3;
            if ($professionalService->isAvailableFriday()) $selectedDays[] = 4;
            if ($professionalService->isAvailableSaturday()) $selectedDays[] = 5;
            if ($professionalService->isAvailableSunday()) $selectedDays[] = 6;
            
            $serviceConfigs[$serviceId] = [
                'id' => $serviceId,
                'name' => $professionalService->getService()->getName(), // AGREGAR ESTA LÍNEA
                'days' => $selectedDays,
                'customDurationMinutes' => $professionalService->getCustomDurationMinutes(),
                'customPrice' => $professionalService->getCustomPrice()
            ];
        }
        
        // Log para debug
        error_log('Preloaded service configs: ' . json_encode($serviceConfigs));
        
        // Nota: Como serviceConfigs tiene mapped => false, no podemos usar setData directamente
        // El frontend JavaScript deberá leer esta información desde el template
        return $serviceConfigs;
    }
}