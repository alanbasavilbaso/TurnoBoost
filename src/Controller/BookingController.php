<?php

namespace App\Controller;

use App\Entity\Company;
use App\Entity\Location;
use App\Entity\Service;
use App\Entity\Professional;
use App\Entity\ProfessionalService;
use App\Entity\Appointment;
use App\Entity\StatusEnum;
use App\Service\AppointmentService;
use App\Service\TimeSlot;
use App\Service\SettingsService;
use App\Service\DomainRoutingService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use App\Entity\AppointmentSourceEnum;
use App\Service\AuditService;
use phpDocumentor\Reflection\DocBlock\Tags\Var_;

class BookingController extends AbstractController
{
    private EntityManagerInterface $entityManager;
    private TimeSlot $timeSlotService;
    private SettingsService $settingsService;
    private DomainRoutingService $domainRoutingService;
    private AppointmentService $appointmentService;
    private AuditService $auditService;

    public function __construct(
        EntityManagerInterface $entityManager, 
        TimeSlot $timeSlotService,
        DomainRoutingService $domainRoutingService,
        AppointmentService $appointmentService,
        AuditService $auditService
    ) {
        $this->entityManager = $entityManager;
        $this->timeSlotService = $timeSlotService;
        $this->domainRoutingService = $domainRoutingService;
        $this->appointmentService = $appointmentService;
        $this->auditService = $auditService;
    }

    /**
     * Página principal de reservas - Dominio directo
     */
    #[Route('/{domain}', name: 'booking_index_direct', requirements: ['domain' => '[a-z0-9-]+'], priority: -10)]
    public function indexDirect(string $domain, Request $request): Response
    {
        // $a = $this->appointmentService->findActiveAppointmentFromChain(81);
        // var_dump($a->getOriginalAppointment()->getId());
        // var_dump($a->getOriginalAppointment()->getModificationCount());
        // exit;
        if (!$this->domainRoutingService->isValidDomainRoute($domain)) {
            // En lugar de lanzar excepción, renderizar página 404 personalizada
            return $this->render('error/domain_not_found.html.twig', [
                'domain' => $domain,
                'message' => 'La página que buscas no existe.'
            ], new Response('', 404));
        }
        
        return $this->renderBookingPage($domain, $request);
    }

    /**
     * Página principal de reservas - Subdominio (mantener compatibilidad)
     */
    #[Route('/', name: 'booking_index_subdomain', host: '{domain}.{base_domain}', requirements: ['domain' => '[a-z0-9-]+'], priority: 2)]
    public function indexSubdomain(string $domain, Request $request): Response
    {
        return $this->renderBookingPage($domain, $request);
    }

    /**
     * Página principal de reservas - Path con /reservas (mantener compatibilidad)
     */
    #[Route('/reservas/{domain}', name: 'booking_index_path', requirements: ['domain' => '[a-z0-9-]+'], priority: 1)]
    public function indexPath(string $domain, Request $request): Response
    {
        return $this->renderBookingPage($domain, $request);
    }

    /**
     * Método centralizado para renderizar la página de booking
     */
    private function renderBookingPage(string $domain, Request $request): Response
    {
        $company = $this->getCompanyByDomain($domain);
        
        // Verificar si es una modificación de cita
        $preloadData = null;
        $isModification = false;
        
        if ($request->query->get('preload') && $request->query->get('modify_token') && $request->query->get('appointment_id')) {
            $modifyToken = $request->query->get('modify_token');
            $appointmentId = (int) $request->query->get('appointment_id');
            // Validar el token de modificación usando el nuevo método
            $preloadResult = $this->appointmentService->validateTokenForPreload($appointmentId, $modifyToken);
            
            if ($preloadResult && $preloadResult['appointment']->getCompany()->getId() === $company->getId()) {
                $isModification = true;
                $preloadData = [
                    'appointment_id' => $appointmentId,
                    'modify_token' => $modifyToken,
                    'patient' => $preloadResult['patient'],
                    'service_id' => $preloadResult['service_id'],
                    'professional_id' => $preloadResult['professional_id'],
                    'location_id' => $preloadResult['location_id'],
                    'original_date' => $preloadResult['original_date'],
                    'original_time' => $preloadResult['original_time'],
                ];
            }
        }
        
        // Obtener todas las ubicaciones activas de la empresa
        $locations = $this->entityManager->getRepository(Location::class)
            ->findBy(['company' => $company, 'active' => true]);
            
        if (empty($locations)) {
            throw new NotFoundHttpException(sprintf('No se encontraron ubicaciones activas para la empresa con dominio "%s"', $domain));
        }

        // Determinar la ubicación a usar (priorizar datos de precarga)
        $selectedLocationId = $preloadData['location_id'] ?? $request->query->get('location');
        $selectedLocation = null;
        
        if ($selectedLocationId) {
            // Si se especifica una ubicación, verificar que pertenezca a la empresa
            $selectedLocation = $this->entityManager->getRepository(Location::class)
                ->findOneBy(['id' => $selectedLocationId, 'company' => $company, 'active' => true]);
                
            if (!$selectedLocation) {
                throw new NotFoundHttpException('Ubicación no encontrada o no pertenece a esta empresa');
            }
        } else if (count($locations) === 1) {
            // Si solo hay una ubicación, usarla automáticamente
            $selectedLocation = $locations[0];
        }

        // Obtener el servicio seleccionado (priorizar datos de precarga)
        $selectedServiceId = $preloadData['service_id'] ?? $request->query->get('service');
        $selectedService = null;
        
        if ($selectedServiceId && $selectedLocation) {
            $selectedService = $this->entityManager->getRepository(Service::class)
                ->findOneBy(['id' => $selectedServiceId, 'company' => $selectedLocation->getCompany(), 'active' => true]);
        }

        // Obtener servicios disponibles para la ubicación seleccionada
        $services = [];
        $showServiceSelector = false;
        if ($selectedLocation) {
            if (!$selectedService) {
                // Obtener servicios disponibles en esta ubicación a través de profesionales activos
                $services = $this->entityManager->getRepository(Service::class)
                    ->createQueryBuilder('s')
                    ->join('s.professionalServices', 'ps')
                    ->join('ps.professional', 'p')
                    ->where('s.company = :company')
                    ->andWhere('s.active = true')
                    ->andWhere('p.active = true')
                    ->andWhere('p.onlineBookingEnabled = true')
                    ->setParameter('company', $selectedLocation->getCompany())
                    ->groupBy('s.id')
                    ->orderBy('s.name', 'ASC')
                    ->getQuery()
                    ->getResult();

                $showServiceSelector = count($services) > 1;
            } else {
                $services = [];
                $showServiceSelector = false;
            }
        }

        // Obtener profesionales disponibles para el servicio y ubicación
        $professionals = [];
        $selectedProfessional = null;
        $wizardStep1Complete = false;
        
        if ($selectedService && $selectedLocation) {
            // Obtener profesionales que ofrecen este servicio en la misma empresa que la ubicación
            $professionals = $this->entityManager->getRepository(Professional::class)
                ->createQueryBuilder('p')
                ->join('p.professionalServices', 'ps')
                ->where('ps.service = :service')
                ->andWhere('p.company = :company')
                ->andWhere('p.active = true')
                ->andWhere('p.onlineBookingEnabled = true')
                ->setParameter('service', $selectedService)
                ->setParameter('company', $selectedLocation->getCompany())
                ->orderBy('p.name', 'ASC')
                ->getQuery()
                ->getResult();
                
            // Si hay un solo profesional, seleccionarlo automáticamente
            if (count($professionals) === 1) {
                $selectedProfessional = $professionals[0];
                $wizardStep1Complete = true;
            } elseif ($preloadData && isset($preloadData['professional_id'])) {
                // Si estamos modificando, seleccionar el profesional de la cita original
                foreach ($professionals as $professional) {
                    if ($professional->getId() === $preloadData['professional_id']) {
                        $selectedProfessional = $professional;
                        $wizardStep1Complete = true;
                        break;
                    }
                }
            }
        }

        // Preparar horarios de la ubicación seleccionada (similar a ProfessionalController)
        $locationSchedule = [];
        if ($selectedLocation) {
            $dayNames = ['Dom', 'Lun', 'Mar', 'Mier', 'Jue', 'Vier', 'Sab'];
            
            for ($day = 0; $day <= 6; $day++) {
                $availabilities = $selectedLocation->getAvailabilitiesForWeekDay($day);
                
                if (!$availabilities->isEmpty()) {
                    $ranges = [];
                    foreach ($availabilities as $availability) {
                        $ranges[] = $availability->getStartTime()->format('H:i') . ' - ' . $availability->getEndTime()->format('H:i');
                    }
                    
                    $locationSchedule[] = [
                        'day' => $dayNames[$day],
                        'times' => implode(', ', $ranges)
                    ];
                }
            }
        }

        return $this->render('booking/index.html.twig', [
            'company' => $company,
            'locations' => $locations,
            'selectedLocation' => $selectedLocation,
            'services' => $services,
            'selectedService' => $selectedService,
            'professionals' => $professionals,
            'selectedProfessional' => $selectedProfessional,
            'wizardStep1Complete' => $wizardStep1Complete,
            'locationSchedule' => $locationSchedule,
            'domain' => $domain,
            'showLocationSelector' => count($locations) > 1 && !$selectedLocation,
            'showServiceSelector' => $showServiceSelector,
            'isModification' => $isModification,
            'preloadData' => $preloadData
        ]);
    }

    /**
     * API: Obtener ubicaciones de la empresa
     * /booking/beati/api/locations
     */
    #[Route('/booking/{domain}/api/locations', name: 'booking_api_locations_direct', methods: ['GET'], requirements: ['domain' => '[a-z0-9-]+'], priority: -10)]
    public function getLocationsDirect(string $domain): JsonResponse
    {
        if (!$this->domainRoutingService->isValidDomainRoute($domain)) {
            throw $this->createNotFoundException('Domain not found or not available');
        }
        
        return $this->getLocationsResponse($domain);
    }

    /**
     * API: Obtener ubicaciones - Path con /reservas (mantener compatibilidad)
     */
    #[Route('/reservas/{domain}/api/locations', name: 'booking_api_locations', methods: ['GET'])]
    public function getLocations(string $domain): JsonResponse
    {
        return $this->getLocationsResponse($domain);
    }

    /**
     * API: Obtener servicios activos - Dominio directo
     * /booking/beati/api/services?location_id=8
     */
    #[Route('/booking/{domain}/api/services', name: 'booking_api_services_direct', methods: ['GET'], requirements: ['domain' => '[a-z0-9-]+'], priority: -10)]
    public function getServicesDirect(string $domain, Request $request): JsonResponse
    {
        if (!$this->domainRoutingService->isValidDomainRoute($domain)) {
            throw $this->createNotFoundException('Domain not found or not available');
        }
        
        return $this->getServicesResponse($domain, $request);
    }

    /**
     * API: Obtener servicios activos - Path con /reservas (mantener compatibilidad)
     */
    #[Route('/reservas/{domain}/api/services', name: 'booking_api_services', methods: ['GET'])]
    public function getServices(string $domain, Request $request): JsonResponse
    {
        return $this->getServicesResponse($domain, $request);
    }

    /**
     * API: Obtener profesionales por servicio - Dominio directo
     * /booking/beati/api/professionals/{serviceId}?location_id=8
     */
    #[Route('/booking/{domain}/api/professionals/{serviceId}', name: 'booking_api_professionals_direct', methods: ['GET'], requirements: ['domain' => '[a-z0-9-]+', 'serviceId' => '\d+'], priority: -10)]
    public function getProfessionalsDirect(string $domain, int $serviceId, Request $request): JsonResponse
    {
        if (!$this->domainRoutingService->isValidDomainRoute($domain)) {
            throw $this->createNotFoundException('Domain not found or not available');
        }
        
        return $this->getProfessionalsResponse($domain, $serviceId, $request);
    }

    /**
     * API: Obtener profesionales por servicio - Path con /reservas (mantener compatibilidad)
     */
    #[Route('/booking/{domain}/api/professionals/{serviceId}', name: 'booking_api_professionals', methods: ['GET'])]
    public function getProfessionals(string $domain, int $serviceId, Request $request): JsonResponse
    {
        return $this->getProfessionalsResponse($domain, $serviceId, $request);
    }

    /**
     * API: Obtener horarios disponibles - Dominio directo
     * /booking/beati/api/timeslots
     */
    #[Route('/booking/{domain}/api/timeslots', name: 'booking_api_timeslots_direct', methods: ['GET'], requirements: ['domain' => '[a-z0-9-]+'], priority: -10)]
    public function getTimeslotsDirect(string $domain, Request $request): JsonResponse
    {
        if (!$this->domainRoutingService->isValidDomainRoute($domain)) {
            throw $this->createNotFoundException('Domain not found or not available');
        }
        
        return $this->getTimeslotsResponse($domain, $request);
    }

    /**
     * API: Obtener horarios disponibles - Path con /reservas (mantener compatibilidad)
     */
    #[Route('/reservas/{domain}/api/timeslots', name: 'booking_api_timeslots', methods: ['GET'])]
    public function getTimeSlotsPath(string $domain, Request $request): JsonResponse
    {
        return $this->getTimeslotsResponse($domain, $request);
    }

    /**
     * API: Crear cita - Dominio directo
     */
    #[Route('/{domain}/api/create', name: 'booking_api_create_direct', methods: ['POST'], requirements: ['domain' => '[a-z0-9-]+'], priority: -10)]
    public function createAppointmentDirect(string $domain, Request $request, AppointmentService $appointmentService): JsonResponse
    {
        return $this->createAppointmentResponse($domain, $request, $appointmentService);
    }

    /**
     * API: Crear cita - Path con /reservas
     */
    #[Route('/reservas/{domain}/api/create', name: 'booking_api_create', methods: ['POST'])]
    public function createAppointmentPath(string $domain, Request $request, AppointmentService $appointmentService): JsonResponse
    {
        return $this->createAppointmentResponse($domain, $request, $appointmentService);
    }

    /**
     * Organiza los slots por períodos (mañana y tarde)
     */
    private function organizeSlotsByPeriod(array $slots): array
    {
        $organized = [
            'morning' => [],
            'afternoon' => []
        ];

        foreach ($slots as $slot) {
            $hour = (int)substr($slot['time'], 0, 2);
            
            // Considerar mañana hasta las 12:00 (no inclusive)
            if ($hour < 12) {
                $organized['morning'][] = [
                    'time' => $slot['time'],
                    'available' => $slot['available'],
                    'duration' => $slot['duration'] ?? null,
                    'datetime' => $slot['datetime'] ?? null
                ];
            } else {
                $organized['afternoon'][] = [
                    'time' => $slot['time'],
                    'available' => $slot['available'],
                    'duration' => $slot['duration'] ?? null,
                    'datetime' => $slot['datetime'] ?? null
                ];
            }
        }

        return $organized;
    }

    /**
     * Método auxiliar para obtener local por dominio
     */
    private function getCompanyByDomain(string $domain): Company
    {
        $company = $this->domainRoutingService->getCompanyByDomain($domain);
            
        if (!$company) {
            throw new NotFoundHttpException(sprintf('No se encontró una empresa con el dominio "%s"', $domain));
        }
        
        return $company;
    }

    /**
     * Método auxiliar para obtener ubicación específica (usado en APIs)
     */
    private function getLocationByDomain(string $domain, ?int $locationId = null): Location
    {
        $company = $this->getCompanyByDomain($domain);
        
        if ($locationId) {
            $location = $this->entityManager->getRepository(Location::class)
                ->findOneBy(['id' => $locationId, 'company' => $company, 'active' => true]);
                
            if (!$location) {
                throw new NotFoundHttpException('Ubicación no encontrada o no pertenece a esta empresa');
            }
            
            return $location;
        }
        
        // Si no se especifica ubicación, obtener la primera activa
        $location = $this->entityManager->getRepository(Location::class)
            ->findOneBy(['company' => $company, 'active' => true]);
            
        if (!$location) {
            throw new NotFoundHttpException(sprintf('No se encontró una ubicación activa para la empresa con dominio "%s"', $domain));
        }
        
        return $location;
    }

    // Métodos auxiliares para evitar duplicación de código
    
    private function getLocationsResponse(string $domain): JsonResponse
    {
        $company = $this->getCompanyByDomain($domain);
        
        $locations = $this->entityManager->getRepository(Location::class)
            ->findBy(['company' => $company, 'active' => true]);

        $locationsData = [];
        foreach ($locations as $location) {
            $locationsData[] = [
                'id' => $location->getId(),
                'name' => $location->getName(),
                'address' => $location->getAddress(),
                'phone' => $location->getPhone(),
                'description' => $location->getDescription()
            ];
        }

        return new JsonResponse($locationsData);
    }
    
    private function getServicesResponse(string $domain, Request $request): JsonResponse
    {
        $locationId = $request->query->get('location_id');
        $location = $this->getLocationByDomain($domain, $locationId);
        
        $services = $this->entityManager->getRepository(Service::class)
            ->createQueryBuilder('s')
            ->where('s.company = :company')
            ->andWhere('s.active = true')
            ->setParameter('company', $location->getCompany())
            ->getQuery()
            ->getResult();
    
        $servicesData = [];
        foreach ($services as $service) {
            $servicesData[] = [
                'id' => $service->getId(),
                'name' => $service->getName(),
                'description' => $service->getDescription(),
                'duration' => $service->getDurationMinutes(),
                'price' => $service->getPrice(),
                'formattedDuration' => $this->formatDuration($service->getDurationMinutes())
            ];
        }
    
        return new JsonResponse($servicesData);
    }

    private function getProfessionalsResponse(string $domain, int $serviceId, Request $request): JsonResponse
    {
        $locationId = $request->query->get('location_id');
        $location = $this->getLocationByDomain($domain, $locationId);
        
        $service = $this->entityManager->getRepository(Service::class)->find($serviceId);
        // Corregir: validar que el servicio pertenece a la misma empresa que la ubicación
        if (!$service || $service->getCompany() !== $location->getCompany()) {
            throw new NotFoundHttpException('Service not found');
        }

        $professionalServices = $this->entityManager->getRepository(ProfessionalService::class)
            ->createQueryBuilder('ps')
            ->join('ps.professional', 'p')
            ->where('ps.service = :service')
            ->andWhere('p.company = :company')
            ->andWhere('p.active = true')
            ->andWhere('p.onlineBookingEnabled = true')
            ->setParameter('service', $service)
            ->setParameter('company', $location->getCompany())
            ->getQuery()
            ->getResult();

        $professionalsData = [];
        foreach ($professionalServices as $professionalService) {
            $professional = $professionalService->getProfessional();
            $professionalsData[] = [
                'id' => $professional->getId(),
                'name' => $professional->getName(),
            ];
        }

        return new JsonResponse($professionalsData);
    }

    private function getTimeslotsResponse(string $domain, Request $request): JsonResponse
    {
        $locationId = $request->query->get('location_id');
        $location = $this->getLocationByDomain($domain, $locationId);
        
        $serviceId = $request->query->get('service_id');
        $professionalId = $request->query->get('professional_id');
        $date = $request->query->get('date');

        if (!$serviceId || !$professionalId || !$date) {
            return new JsonResponse(['error' => 'Missing required parameters'], 400);
        }

        try {
            $dateTime = new \DateTime($date);

            // Buscar la relación ProfessionalService directamente
            $professionalService = $this->entityManager->getRepository(ProfessionalService::class)->findOneBy([
                'professional' => $professionalId,
                'service' => $serviceId
            ]);

            if (!$professionalService) {
                throw new NotFoundHttpException('Professional not available for this service');
            }

            $professional = $professionalService->getProfessional();
            $service = $professionalService->getService();

            if (!$professional->isActive()) {
                throw new NotFoundHttpException('Professional is not active');
            }

            if ($service->getCompany() !== $location->getCompany()) {
                throw new NotFoundHttpException('Service does not belong to this company');
            }
            // Generar slots disponibles
            $timeSlots = $this->timeSlotService->generateAvailableSlots(
                $professional,
                $service,
                $dateTime
            );
            
            // Filtrar slots bloqueados usando AppointmentService
            $filteredSlots = $this->appointmentService->filterAvailableSlots(
                $timeSlots, 
                $professional->getId(), 
                $dateTime->format('Y-m-d')
            );
            // var_dump($filteredSlots);
            // var_dump('--');
            // exit;
            $timeSlotsData = [];
            $now = new \DateTime(); // Obtener la fecha y hora actual
            $company = $location->getCompany(); // Obtener la empresa para validaciones
            
            foreach ($filteredSlots as $slot) {
                $slotDateTime = new \DateTime($slot['datetime']);
                
                // Solo incluir slots que sean en el futuro Y que cumplan con el tiempo mínimo de reserva
                if ($slotDateTime > $now && $company->isWithinMinimumTime($slotDateTime)) {
                    $timeSlotsData[] = [
                        'time' => $slot['time'],
                        'datetime' => $slot['datetime'],
                        'available' => $slot['available']
                    ];
                }
            }

            return new JsonResponse($timeSlotsData);

        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }
    }

     /**
     * API: Crear reserva - Dominio directo
     * /booking/beati/api/create
     */
    #[Route('/booking/{domain}/api/create', name: 'booking_api_create_direct', methods: ['POST'], requirements: ['domain' => '[a-z0-9-]+'], priority: -10)]
    public function createBookingDirect(string $domain, Request $request, AppointmentService $appointmentService): JsonResponse
    {
        if (!$this->domainRoutingService->isValidDomainRoute($domain)) {
            throw $this->createNotFoundException('Domain not found or not available');
        }
        
        return $this->createAppointmentResponse($domain, $request, $appointmentService);
    }

    /**
     * Obtiene las fechas disponibles para un profesional y servicio
     */
    #[Route('/booking/{domain}/api/available-dates', name: 'booking_api_available_dates_direct', methods: ['GET'], requirements: ['domain' => '[a-z0-9-]+'], priority: -10)]
    #[Route('/reservas/{domain}/api/available-dates', name: 'booking_api_available_dates', methods: ['GET'])]
    public function getAvailableDates(string $domain, Request $request): JsonResponse
    {
        $locationIdParam = $request->query->get('location_id');
        $locationId = ($locationIdParam && $locationIdParam !== '') ? (int)$locationIdParam : null;
        
        $location = $this->getLocationByDomain($domain, $locationId);
        
        $serviceId = $request->query->get('service_id');
        $professionalId = $request->query->get('professional_id');
        $startDate = $request->query->get('start_date');

        if (!$serviceId || !$professionalId) {
            return new JsonResponse(['error' => 'Missing required parameters'], 400);
        }

        try {
            // Buscar la relación ProfessionalService directamente
            $professionalService = $this->entityManager->getRepository(ProfessionalService::class)->findOneBy([
                'professional' => $professionalId,
                'service' => $serviceId
            ]);

            if (!$professionalService) {
                throw new NotFoundHttpException('Professional not available for this service');
            }

            // Obtener las entidades desde el ProfessionalService
            $professional = $professionalService->getProfessional();
            $service = $professionalService->getService();

            // Validaciones adicionales
            if (!$professional->isActive()) {
                throw new NotFoundHttpException('Professional is not active');
            }

            if ($service->getCompany() !== $location->getCompany()) {
                throw new NotFoundHttpException('Service does not belong to this company');
            }

            // Procesar fechas de inicio y fin
            $startDateTime = $startDate ? new \DateTime($startDate) : new \DateTime();
            

            // Generar fechas disponibles para el rango especificado
            $availableDates = $this->generateAvailableDates($professional, $service, $location, $startDateTime);

            return new JsonResponse($availableDates);

        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }
    }

    /**
     *  Confirmar cita - Dominio directo
     */
    #[Route('/{domain}/confirm/{appointmentId}/{token}', name: 'booking_confirm_direct', methods: ['GET'], requirements: ['domain' => '[a-z0-9-]+'], priority: -10)]
    public function confirmAppointmentDirect(string $domain, int $appointmentId, string $token): Response
    {
        return $this->processAppointmentActionDirect($domain, $appointmentId, $token, 'confirm');
    }

    /**
     *  Confirmar cita - Path con /reservas
     */
    #[Route('/reservas/{domain}/confirm/{appointmentId}/{token}', name: 'booking_confirm', methods: ['GET'])]
    public function confirmAppointmentPath(string $domain, int $appointmentId, string $token): Response
    {
        return $this->processAppointmentActionDirect($domain, $appointmentId, $token, 'confirm');
    }

    /**
     * Procesa automáticamente las acciones de confirmación,
     */
    private function processAppointmentActionDirect(string $domain, int $appointmentId, string $token, string $action): Response
    {
        // Validaciones básicas
        $validationResult = $this->validateAppointmentAction($domain, $appointmentId, $token, $action);
        if ($validationResult['error']) {
            return $this->render('booking/appointment_error.html.twig', [
                'error' => $validationResult['error'],
                'domain' => $domain,
                'company' => $validationResult['company'],
                'action' => $action
            ]);
        }

        $appointment = $validationResult['appointment'];
        $company = $validationResult['company'];
 
        try {
            $originalStatus = $appointment->getStatus();
            $actionPerformed = false;
            
            switch ($action) {
                case 'confirm':
                    // Solo confirmar si no está ya confirmada
                    if ($originalStatus !== StatusEnum::CONFIRMED) {
                        $appointment->setStatus(StatusEnum::CONFIRMED);
                        $appointment->setConfirmedAt(new \DateTime());
                        $actionPerformed = true;
                    }
                    break;
                case 'cancel':
                    // Solo cancelar si no está ya cancelada
                    if ($originalStatus !== StatusEnum::CANCELLED) {
                        $appointment->setStatus(StatusEnum::CANCELLED);
                        $appointment->setCancelledAt(new \DateTime());
                        $actionPerformed = true;
                    }
                    break;
                default:
                    throw new \InvalidArgumentException('Acción no válida.');
            }
            
            // Solo actualizar la base de datos si se realizó un cambio
            if ($actionPerformed) {
                $appointment->setUpdatedAt(new \DateTime());
                $this->entityManager->flush();
                
                // Registrar en auditoría solo si se realizó la acción
                $this->auditService->logChange(
                    'Appointment',
                    $appointment->getId(),
                    $action . '_by_link',
                    ['status' => $originalStatus->value],
                    ['status' => $appointment->getStatus()->value]
                );
            }
            
            return $this->render('booking/appointment_success.html.twig', [
                'message' => '',
                'appointment' => $appointment,
                'company' => $company,
                'domain' => $domain,
                'action' => $action,
                'actionPerformed' => $actionPerformed,
                'originalStatus' => $originalStatus
            ]);

        } catch (\Exception $e) {
            // var_dump($e->getMessage());
            return $this->render('booking/appointment_error.html.twig', [
                'error' => 'Ocurrió un error al procesar tu solicitud. Por favor, inténtalo de nuevo.',
                'domain' => $domain,
                'company' => $company,
                'action' => $action
            ]);
        }
    }

    /**
     *  Cancelar cita - Dominio directo
     */
    #[Route('/{domain}/cancel/{appointmentId}/{token}', name: 'booking_cancel_direct', methods: ['GET', 'POST'], requirements: ['domain' => '[a-z0-9-]+'], priority: -10)]
    public function cancelAppointmentDirect(string $domain, int $appointmentId, string $token, Request $request): Response
    {
        return $this->handleAppointmentAction($domain, $appointmentId, $token, 'cancel', $request);
    }

    /**
     *  Cancelar cita - Path con /reservas
     */
    #[Route('/reservas/{domain}/cancel/{appointmentId}/{token}', name: 'booking_cancel', methods: ['GET', 'POST'])]
    public function cancelAppointmentPath(string $domain, int $appointmentId, string $token, Request $request): Response
    {
        return $this->handleAppointmentAction($domain, $appointmentId, $token, 'cancel', $request);
    }

    /**
     *  Modificar cita - Dominio directo
     */
    #[Route('/{domain}/modify/{appointmentId}/{token}', name: 'booking_modify_direct', methods: ['GET'], requirements: ['domain' => '[a-z0-9-]+'], priority: -10)]
    public function modifyAppointmentDirect(string $domain, int $appointmentId, string $token, Request $request): Response
    {
        return $this->handleAppointmentAction($domain, $appointmentId, $token, 'modify', $request);
    }

    /**
     *  Modificar cita - Path con /reservas
     */
    #[Route('/reservas/{domain}/modify/{appointmentId}/{token}', name: 'booking_modify', methods: ['GET'])]
    public function modifyAppointmentPath(string $domain, int $appointmentId, string $token, Request $request): Response
    {
        return $this->handleAppointmentAction($domain, $appointmentId, $token, 'modify', $request);
    }


    /**
     * Maneja las acciones de confirmación, cancelación y modificación de citas
     */
    private function handleAppointmentAction(string $domain, int $appointmentId, string $token, string $action, Request $request = null): Response
    {
        // Validaciones básicas
        $validationResult = $this->validateAppointmentAction($domain, $appointmentId, $token, $action);
        
        if ($validationResult['error']) {
            return $this->render('booking/appointment_error.html.twig', [
                'error' => $validationResult['error'],
                'domain' => $domain,
                'company' => $validationResult['company']
            ]);
        }

        $appointment = $validationResult['appointment'];
        $company = $validationResult['company'];

        // Si es POST, procesar la acción
        if ($request && $request->isMethod('POST')) {
            return $this->processAppointmentAction($appointment, $company, $action, $domain, $token, $this->auditService);
        }

        // Si es GET, mostrar la página correspondiente
        return $this->showAppointmentActionPage($appointment, $company, $action, $domain, $token);
    }

    /**
     * Valida que la acción se puede realizar sobre la cita
     */
    private function validateAppointmentAction(string $domain, int $appointmentId, string $token, string $action): array
    {
        // Verificar que el dominio existe
        $company = $this->domainRoutingService->getCompanyByDomain($domain);
        if (!$company) {
            return [
                'error' => 'Dominio no encontrado.',
                'company' => null,
                'appointment' => null
            ];
        }

        // Buscar el turno activo de la cadena
        $activeAppointment = $this->appointmentService->findActiveAppointmentFromChain($appointmentId);
        if (!$activeAppointment) {
            return [
                'error' => 'No se encontró un turno activo.',
                'company' => $company,
                'appointment' => null
            ];
        }
        
        // Verificar que la cita pertenece a la empresa
        if ($activeAppointment->getCompany()->getId() !== $company->getId()) {
            return [
                'error' => 'Esta cita no pertenece a este dominio.',
                'company' => $company,
                'appointment' => null
            ];
        }

        // Verificar el token (usando el ID original para validación)
        $originalAppointment = $this->entityManager->getRepository(Appointment::class)->find($appointmentId);
        if (!$originalAppointment || !$this->verifyAppointmentToken($originalAppointment, $token)) {
            return [
                'error' => 'Token de acceso inválido.',
                'company' => $company,
                'appointment' => $activeAppointment
            ];
        }

        // Validaciones específicas por acción
        $actionValidation = $this->validateSpecificAction($activeAppointment, $company, $action);
        if ($actionValidation['error']) {
            return [
                'error' => $actionValidation['error'],
                'company' => $company,
                'appointment' => $activeAppointment
            ];
        }
        return [
            'error' => null,
            'company' => $company,
            'appointment' => $activeAppointment
        ];
    }

    /**
     * Validaciones específicas por tipo de acción
     */
    private function validateSpecificAction(Appointment $appointment, Company $company, string $action): array
    {
        $now = new \DateTime();
        
        // Verificar que la cita no haya pasado
        if ($appointment->getScheduledAt() <= $now) {
            return ['error' => 'Esta cita ya ha pasado y no puede ser ' . ($action === 'confirm' ? 'confirmada' : ($action === 'cancel' ? 'cancelada' : 'modificada')) . '.'];
        }

        switch ($action) {
            case 'confirm':
                return $this->validateConfirmation($appointment, $company);
            case 'cancel':
                return $this->validateCancellation($appointment, $company);
            case 'modify':
                return $this->validateModification($appointment, $company);
            default:
                return ['error' => 'Acción no válida.'];
        }
    }

    /**
     * Validaciones para confirmación
     */
    private function validateConfirmation(Appointment $appointment, Company $company): array
    {
        // Verificar que la cita no esté cancelada
        if ($appointment->getStatus() === StatusEnum::CANCELLED) {
            return ['error' => 'Esta cita ya ha sido cancelada y no puede ser confirmada.'];
        }

        // Verificar tiempo mínimo para confirmar
        $now = new \DateTime();
        $minEditTime = $company->getMinimumEditTime(); // en minutos
        $timeDiff = $appointment->getScheduledAt()->getTimestamp() - $now->getTimestamp();
        
        if ($timeDiff < $minEditTime * 60) {
            $hours = round($minEditTime / 60, 1);
            return ['error' => "Ya no es posible confirmar esta cita. Debe confirmarse con al menos {$hours} horas de anticipación."];
        }

        return ['error' => null];
    }

    /**
     * Validaciones para cancelación
     */
    private function validateCancellation(Appointment $appointment, Company $company): array
    {
        // Verificar que la empresa permite cancelaciones
        if (!$company->isCancellableBookings()) {
            return ['error' => 'Esta empresa no permite cancelar citas en línea.'];
        }

        // Verificar que la cita no esté ya cancelada
        if ($appointment->getStatus() === StatusEnum::CANCELLED) {
            return ['error' => 'Esta cita ya ha sido cancelada anteriormente.'];
        }

        // Verificar tiempo mínimo para cancelar
        $now = new \DateTime();
        $minEditTime = $company->getMinimumEditTime(); // en minutos
        $timeDiff = $appointment->getScheduledAt()->getTimestamp() - $now->getTimestamp();
        
        if ($timeDiff < $minEditTime * 60) {
            $hours = round($minEditTime / 60, 1);
            return ['error' => "Ya no es posible cancelar esta cita. Debe cancelarse con al menos {$hours} horas de anticipación."];
        }

        return ['error' => null];
    }

    /**
     * Validaciones para modificación
     */
    private function validateModification(Appointment $appointment, Company $company): array
    {
        // Verificar que la empresa permite modificaciones
        if (!$company->isEditableBookings()) {
            return ['error' => 'Esta empresa no permite modificar citas en línea.'];
        }

        // Verificar que la cita no esté cancelada
        if ($appointment->getStatus() === StatusEnum::CANCELLED) {
            return ['error' => 'Esta cita ha sido cancelada y no puede ser modificada.'];
        }

        // Verificar tiempo mínimo para modificar
        $now = new \DateTime();
        $minEditTime = $company->getMinimumEditTime(); // en minutos
        $timeDiff = $appointment->getScheduledAt()->getTimestamp() - $now->getTimestamp();
        
        if ($timeDiff < $minEditTime * 60) {
            $hours = round($minEditTime / 60, 1);
            return ['error' => "Ya no es posible modificar esta cita. Debe modificarse con al menos {$hours} horas de anticipación."];
        }

        return ['error' => null];
    }

    /**
     * Muestra la página correspondiente a la acción
     */
    private function showAppointmentActionPage(Appointment $appointment, Company $company, string $action, string $domain, string $token): Response
    {
        $templateData = [
            'appointment' => $appointment,
            'company' => $company,
            'domain' => $domain,
            'token' => $token,
            'action' => $action
        ];

        switch ($action) {
            case 'confirm':
                return $this->render('booking/appointment_confirm.html.twig', $templateData);
            case 'cancel':
                return $this->render('booking/appointment_cancel.html.twig', $templateData);
            case 'modify':
                // Usar la cita original para generar el token de modificación
                $rootAppointment = $appointment->getRootAppointment();
                $modifyToken = $this->appointmentService->generateSecureToken($rootAppointment->getId(), 'modify');
                
                // Construir URL con query parameters para precarga
                $queryParams = http_build_query([
                    'modify_token' => $modifyToken,
                    'appointment_id' => $rootAppointment->getId(),
                    'preload' => '1'
                ]);
                
                $redirectUrl = "/{$domain}?{$queryParams}";
                return $this->redirect($redirectUrl);
            default:
                return $this->render('booking/appointment_error.html.twig', [
                    'error' => 'Acción no válida.',
                    'domain' => $domain,
                    'company' => $company
                ]);
        }
    }

    /**
     * Procesa la acción (POST)
     */
    private function processAppointmentAction(Appointment $appointment, Company $company, string $action, string $domain, string $token): Response
    {
        try {
            $oldStatus = $appointment->getStatus()->value;
            
            switch ($action) {
                case 'confirm':
                    $appointment->setStatus(StatusEnum::CONFIRMED);
                    $appointment->setConfirmedAt(new \DateTime());
                    $message = 'Tu cita ha sido confirmada exitosamente.';
                    break;
                case 'cancel':
                    $appointment->setStatus(StatusEnum::CANCELLED);
                    $appointment->setCancelledAt(new \DateTime());
                    $message = 'Tu cita ha sido cancelada exitosamente.';
                    break;
                default:
                    throw new \InvalidArgumentException('Acción no válida.');
            }

            $appointment->setUpdatedAt(new \DateTime());
            $this->entityManager->flush();

            // Registrar en auditoría
            $this->auditService->logChange(
                'Appointment',
                $appointment->getId(),
                $action . '_by_link',
                ['status' => $oldStatus],
                ['status' => $appointment->getStatus()->value]
            );

            return $this->render('booking/appointment_success.html.twig', [
                'message' => $message,
                'appointment' => $appointment,
                'company' => $company,
                'domain' => $domain,
                'action' => $action
            ]);

        } catch (\Exception $e) {
            // var_dump($e->getMessage());
            return $this->render('booking/appointment_error.html.twig', [
                'error' => 'Ocurrió un error al procesar tu solicitud. Por favor, inténtalo de nuevo.',
                'domain' => $domain,
                'company' => $company,
                'action' => $action
            ]);
        }
    }

    /**
     * Genera las fechas disponibles basadas en location_availability y professional_availability
     */
    private function generateAvailableDates(Professional $professional, Service $service, Location $location, \DateTime $startDate = null): array
    {
        $availableDates = [];
        $today = $startDate ?? new \DateTime();
        $endDate = (clone $today)->add(new \DateInterval('P10D')); // 10 días por defecto

        $currentDate = clone $today;
        while ($currentDate <= $endDate) {
            $dayOfWeek = (int)$currentDate->format('w'); // Convertir a 0=Lunes, 6=Domingo
            // if ($dayOfWeek === 7) $dayOfWeek = 0; // Domingo

            // Verificar si la ubicación está disponible este día
            $locationAvailable = $location->getAvailabilitiesForWeekDay($dayOfWeek)->count() > 0;
            
            // Verificar si el profesional está disponible este día
            $professionalAvailable = $professional->getAvailabilities()->filter(
                fn($availability) => $availability->getWeekday() === $dayOfWeek
            )->count() > 0;

            // Verificar si el servicio está disponible para este profesional en este día
            $professionalService = $this->entityManager->getRepository(ProfessionalService::class)
                ->findOneBy(['professional' => $professional, 'service' => $service]);
            
            $serviceAvailable = $professionalService && $professionalService->isAvailableOnDay($dayOfWeek);

            if ($locationAvailable && $professionalAvailable && $serviceAvailable) {
                // Verificar si hay al menos un slot disponible
                $timeSlots = $this->timeSlotService->generateAvailableSlots(
                    $professional,
                    $service,
                    $currentDate
                );

                if (!empty($timeSlots)) {
                    $availableDates[] = [
                        'date' => $currentDate->format('Y-m-d'),
                        'dayName' => $this->getDayName($dayOfWeek),
                        'dayNumber' => (int)$currentDate->format('d'),
                        'isWeekend' => in_array($dayOfWeek, [0, 6]), // Sábado y Domingo
                        'slotsCount' => count($timeSlots)
                    ];
                }
            }

            $currentDate->add(new \DateInterval('P1D'));
        }

        return $availableDates;
    }

    /**
     * Obtiene el nombre del día en español
     */
    private function getDayName(int $dayOfWeek): string
    {
        $dayNames = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
        return $dayNames[$dayOfWeek] ?? 'Desconocido';
    }

    private function createAppointmentResponse(string $domain, Request $request, AppointmentService $appointmentService): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            if (!$data) {
                return new JsonResponse(['success' => false, 'message' => 'Invalid JSON data'], 400);
            }

            // Obtener la empresa por dominio
            $company = $this->getCompanyByDomain($domain);
            
            // Validar datos requeridos básicos
            $requiredFields = ['service_id', 'professional_id', 'date', 'time', 'location_id', 'name', 'lastname'];
            
            // Validar campos de contacto según la configuración de la empresa
            $emailRequired = $company->isRequireEmail();
            $phoneRequired = $company->isRequirePhone();
            $contactDataRequired = $company->isRequireContactData();
            
            // Agregar email y/o phone a campos requeridos si están específicamente requeridos
            if ($emailRequired) {
                $requiredFields[] = 'email';
            }
            
            if ($phoneRequired) {
                $requiredFields[] = 'phone';
            }
            
            // Validar campos básicos
            foreach ($requiredFields as $field) {
                if (!isset($data[$field]) || empty($data[$field])) {
                    $fieldNames = [
                        'service_id' => 'servicio',
                        'professional_id' => 'profesional',
                        'date' => 'fecha',
                        'time' => 'hora',
                        'location_id' => 'ubicación',
                        'name' => 'nombre',
                        'lastname' => 'apellido',
                        'email' => 'email',
                        'phone' => 'teléfono'
                    ];
                    
                    $fieldName = $fieldNames[$field] ?? $field;
                    return new JsonResponse([
                        'success' => false, 
                        'message' => "El campo {$fieldName} es obligatorio"
                    ], 400);
                }
            }
            
            // Si requiere datos de contacto pero no específicamente email o phone,
            // entonces debe tener al menos uno de los dos
            if ($contactDataRequired && !$emailRequired && !$phoneRequired) {
                $hasEmail = isset($data['email']) && !empty($data['email']);
                $hasPhone = isset($data['phone']) && !empty($data['phone']);
                
                if (!$hasEmail && !$hasPhone) {
                    return new JsonResponse([
                        'success' => false, 
                        'message' => 'Se requiere al menos un email o teléfono para realizar la reserva'
                    ], 400);
                }
            }
            
            // Validaciones de formato para email y teléfono
            if (isset($data['email']) && !empty($data['email'])) {
                if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                    return new JsonResponse([
                        'success' => false, 
                        'message' => 'El email no es válido'
                    ], 400);
                }
            }
            
            if (isset($data['phone']) && !empty($data['phone'])) {
                // Limpiar el teléfono: remover espacios, guiones, paréntesis
                $cleanPhone = preg_replace('/[^\d+]/', '', $data['phone']);
                
                // Validar formato de teléfono argentino
                // Puede empezar con +54 o no, y tener entre 8 y 12 dígitos después del código de país
                if (!preg_match('/^(\+54)?[0-9]{8,12}$/', $cleanPhone)) {
                    return new JsonResponse([
                        'success' => false, 
                        'message' => 'El teléfono no es válido. Debe contener entre 8 y 12 dígitos'
                    ], 400);
                }
                
                // Actualizar el dato con el teléfono limpio
                $data['phone'] = $cleanPhone;
            }
            
            // Preparar los datos en el formato que espera AppointmentService
            $appointmentData = [
                'professional_id' => (int)$data['professional_id'],
                'service_id' => (int)$data['service_id'],
                'location_id' => (int)$data['location_id'],
                'date' => $data['date'],
                'appointment_time_from' => $data['time'],
                'patient_first_name' => $data['name'],
                'patient_last_name' => $data['lastname'],
                'patient_email' => $data['email'],
                'patient_phone' => $data['phone'],
                'notes' => $data['notes'] ?? null
            ];

            // Verificar si es una modificación de cita existente
            if (isset($data['is_modification']) && $data['is_modification'] && isset($data['appointment_id']) && isset($data['modify_token'])) {
                $appointmentId = (int)$data['appointment_id'];
                $modifyToken = $data['modify_token'];
                
                try {
                    // Usar el nuevo método modifyAppointment del servicio
                    $appointment = $appointmentService->modifyAppointment(
                        $appointmentId,
                        $modifyToken,
                        $appointmentData,
                        $company
                    );
                } catch (\InvalidArgumentException $e) {
                    return new JsonResponse(['success' => false, 'message' => $e->getMessage()], 400);
                }
            } else {
                // Crear la cita usando el AppointmentService con origen USER
                // Las validaciones de usuario se aplicarán automáticamente
                $appointment = $appointmentService->createAppointment($appointmentData, $company, false, AppointmentSourceEnum::USER);
            }

            // Extraer date y time del scheduledAt para evitar problemas de zona horaria en el frontend
            $scheduledAt = $appointment->getScheduledAt();
            $date = $scheduledAt->format('Y-m-d');
            $time = $scheduledAt->format('H:i:s');

            return new JsonResponse([
                'success' => true, 
                'message' => 'Cita creada exitosamente',
                'appointment_id' => $appointment->getId(),
                'appointment' => $appointmentService->appointmentToArray($appointment),
                'date' => $date,
                'time' => $time
            ]);

        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['success' => false, 'message' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            var_dump($e->getMessage());
            return new JsonResponse(['success' => false, 'message' => 'Error interno del servidor'], 500);
        }
    }

    /**
     * Formatea la duración en minutos a formato legible
     */
    private function formatDuration(int $minutes): string
    {
        if ($minutes < 60) {
            return $minutes . ' min';
        }
        
        $hours = intval($minutes / 60);
        $remainingMinutes = $minutes % 60;
        
        if ($remainingMinutes === 0) {
            return $hours . 'h';
        }
        
        return $hours . 'h ' . $remainingMinutes . 'min';
    }

    /**
     * Verificar token de seguridad para una cita
     */
    private function verifyAppointmentToken(Appointment $appointment, string $token): bool
    {
        // Generar token esperado basado en datos de la cita
        $expectedToken = $this->generateAppointmentToken($appointment);
        return hash_equals($expectedToken, $token);
    }

    /**
     * Generar token de seguridad para una cita
     */
    private function generateAppointmentToken(Appointment $appointment): string
    {
        // Usar datos únicos de la cita para generar el token
        $data = $appointment->getId() . 
                $appointment->getPatient()->getEmail() . 
                $appointment->getScheduledAt()->format('Y-m-d H:i:s') .
                $appointment->getCompany()->getId();
        
        return hash('sha256', $data . $_ENV['APP_SECRET'] ?? 'default_secret');
    }
}