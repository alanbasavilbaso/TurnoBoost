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

class BookingController extends AbstractController
{
    private EntityManagerInterface $entityManager;
    private TimeSlot $timeSlotService;
    private SettingsService $settingsService;
    private DomainRoutingService $domainRoutingService;

    public function __construct(
        EntityManagerInterface $entityManager, 
        TimeSlot $timeSlotService,
        DomainRoutingService $domainRoutingService
    ) {
        $this->entityManager = $entityManager;
        $this->timeSlotService = $timeSlotService;
        $this->domainRoutingService = $domainRoutingService;
    }

    /**
     * Página principal de reservas - Dominio directo
     */
    #[Route('/{domain}', name: 'booking_index_direct', requirements: ['domain' => '[a-z0-9-]+'], priority: -10)]
    public function indexDirect(string $domain, Request $request): Response
    {
        if (!$this->domainRoutingService->isValidDomainRoute($domain)) {
            throw $this->createNotFoundException('Domain not found or not available');
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
        
        // Obtener todas las ubicaciones activas de la empresa
        $locations = $this->entityManager->getRepository(Location::class)
            ->findBy(['company' => $company, 'active' => true]);
            
        if (empty($locations)) {
            throw new NotFoundHttpException(sprintf('No se encontraron ubicaciones activas para la empresa con dominio "%s"', $domain));
        }

        // Determinar la ubicación a usar
        $selectedLocationId = $request->query->get('location');
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

        // Obtener el servicio seleccionado desde la query string
        $selectedServiceId = $request->query->get('service');
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
            'showServiceSelector' => $showServiceSelector
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
            $service = $this->entityManager->getRepository(Service::class)->find($serviceId);
            $professional = $this->entityManager->getRepository(Professional::class)->find($professionalId);
            $dateTime = new \DateTime($date);

            // Corregir: validar que el servicio pertenece a la misma empresa que la ubicación
            if (!$service || $service->getCompany() !== $location->getCompany()) {
                throw new NotFoundHttpException('Service not found');
            }

            if (!$professional || !$professional->isActive()) {
                throw new NotFoundHttpException('Professional not found');
            }

            // Verificar que el profesional esté asociado al servicio
            $professionalService = $this->entityManager->getRepository(ProfessionalService::class)
                ->findOneBy(['professional' => $professional, 'service' => $service]);
                
            if (!$professionalService) {
                throw new NotFoundHttpException('Professional not available for this service');
            }

            $timeSlots = $this->timeSlotService->generateAvailableSlots(
                $professional,
                $service,
                $dateTime
            );
          
            $timeSlotsData = [];
            $now = new \DateTime(); // Obtener la fecha y hora actual
            $company = $location->getCompany(); // Obtener la empresa para validaciones
            
            foreach ($timeSlots as $slot) {
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

        if (!$serviceId || !$professionalId) {
            return new JsonResponse(['error' => 'Missing required parameters'], 400);
        }

        try {
            $service = $this->entityManager->getRepository(Service::class)->find($serviceId);
            $professional = $this->entityManager->getRepository(Professional::class)->find($professionalId);

            if (!$service || $service->getCompany() !== $location->getCompany()) {
                throw new NotFoundHttpException('Service not found');
            }

            if (!$professional || !$professional->isActive()) {
                throw new NotFoundHttpException('Professional not found');
            }

            // Verificar que el profesional esté asociado al servicio
            $professionalService = $this->entityManager->getRepository(ProfessionalService::class)
                ->findOneBy(['professional' => $professional, 'service' => $service]);
                
            if (!$professionalService) {
                throw new NotFoundHttpException('Professional not available for this service');
            }

            // Generar fechas disponibles para los próximos 30 días
            $availableDates = $this->generateAvailableDates($professional, $service, $location);

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
            
            switch ($action) {
                case 'confirm':
                    // Solo confirmar si no está ya confirmada
                    if ($originalStatus !== StatusEnum::CONFIRMED) {
                        $appointment->setStatus(StatusEnum::CONFIRMED);
                        $actionPerformed = true;
                    } else {
                        $actionPerformed = false;
                    }
                    break;
                default:
                    throw new \InvalidArgumentException('Acción no válida.');
            }
            $message = '';
            // Solo actualizar la base de datos si se realizó un cambio
            if ($actionPerformed) {
                $appointment->setUpdatedAt(new \DateTime());
                $this->entityManager->flush();
            }
            
            return $this->render('booking/appointment_success.html.twig', [
                'message' => $message,
                'appointment' => $appointment,
                'company' => $company,
                'domain' => $domain,
                'action' => $action,
                'actionPerformed' => $actionPerformed,
                'originalStatus' => $originalStatus
            ]);

        } catch (\Exception $e) {
            return $this->render('booking/appointment_error.html.twig', [
                'error' => 'Ocurrió un error al procesar tu solicitud. Por favor, inténtalo de nuevo.',
                'domain' => $domain,
                'company' => $company,
                'appointment' => $appointment,
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
            return $this->processAppointmentAction($appointment, $company, $action, $domain, $token);
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

        // Buscar la cita
        $appointment = $this->entityManager->getRepository(Appointment::class)->find($appointmentId);
        if (!$appointment) {
            return [
                'error' => 'Cita no encontrada.',
                'company' => $company,
                'appointment' => null
            ];
        }
        
        // Verificar que la cita pertenece a la empresa
        if ($appointment->getCompany()->getId() !== $company->getId()) {
            return [
                'error' => 'Esta cita no pertenece a este dominio.',
                'company' => $company,
                'appointment' => null
            ];
        }

        // Verificar el token
        if (!$this->verifyAppointmentToken($appointment, $token)) {
            return [
                'error' => 'Token de acceso inválido.',
                'company' => $company,
                'appointment' => $appointment
            ];
        }

        // Validaciones específicas por acción
        $actionValidation = $this->validateSpecificAction($appointment, $company, $action);
        if ($actionValidation['error']) {
            return [
                'error' => $actionValidation['error'],
                'company' => $company,
                'appointment' => $appointment
            ];
        }
        return [
            'error' => null,
            'company' => $company,
            'appointment' => $appointment
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
                // Para modificar, redirigir al sistema de reservas con parámetros
                $redirectUrl = "/{$domain}?edit_appointment={$appointment->getId()}&token={$token}";
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
            switch ($action) {
                case 'confirm':
                    $appointment->setStatus(StatusEnum::CONFIRMED);
                    $message = 'Tu cita ha sido confirmada exitosamente.';
                    break;
                case 'cancel':
                    $appointment->setStatus(StatusEnum::CANCELLED);
                    $message = 'Tu cita ha sido cancelada exitosamente.';
                    break;
                default:
                    throw new \InvalidArgumentException('Acción no válida.');
            }

            $appointment->setUpdatedAt(new \DateTime());
            $this->entityManager->flush();

            return $this->render('booking/appointment_success.html.twig', [
                'message' => $message,
                'appointment' => $appointment,
                'company' => $company,
                'domain' => $domain,
                'action' => $action
            ]);

        } catch (\Exception $e) {
            return $this->render('booking/appointment_error.html.twig', [
                'error' => 'Ocurrió un error al procesar tu solicitud. Por favor, inténtalo de nuevo.',
                'domain' => $domain,
                'company' => $company,
                'appointment' => $appointment
            ]);
        }
    }

    /**
     * Genera las fechas disponibles basadas en location_availability y professional_availability
     */
    private function generateAvailableDates(Professional $professional, Service $service, Location $location): array
    {
        $availableDates = [];
        $today = new \DateTime();
        $endDate = (clone $today)->add(new \DateInterval('P30D')); // 30 días desde hoy

        $currentDate = clone $today;
        while ($currentDate <= $endDate) {
            $dayOfWeek = (int)$currentDate->format('N'); // Convertir a 0=Lunes, 6=Domingo
            if ($dayOfWeek === 7) $dayOfWeek = 0; // Domingo

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

            // Validar datos requeridos
            $requiredFields = ['service_id', 'professional_id', 'date', 'time', 'location_id', 'name', 'lastname', 'email', 'phone'];
            foreach ($requiredFields as $field) {
                if (!isset($data[$field]) || empty($data[$field])) {
                    return new JsonResponse(['success' => false, 'message' => "Missing required field: $field"], 400);
                }
            }

            // Obtener la empresa por dominio
            $company = $this->getCompanyByDomain($domain);
            
            
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

            // Crear la cita usando el AppointmentService con origen USER
            // Las validaciones de usuario se aplicarán automáticamente
            $appointment = $appointmentService->createAppointment($appointmentData, $company, false, AppointmentSourceEnum::USER);

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
     * Confirmar cita
     */
    private function confirmAppointmentResponse(string $domain, int $appointmentId, string $token): JsonResponse
    {
        try {
            // Verificar que el dominio existe
            $company = $this->domainRoutingService->getCompanyByDomain($domain);
            if (!$company) {
                return new JsonResponse(['success' => false, 'message' => 'Dominio no encontrado'], 404);
            }

            // Buscar la cita
            $appointment = $this->entityManager->getRepository(Appointment::class)->find($appointmentId);
            if (!$appointment || $appointment->getCompany() !== $company) {
                return new JsonResponse(['success' => false, 'message' => 'Cita no encontrada'], 404);
            }

            // Verificar token de seguridad
            if (!$this->verifyAppointmentToken($appointment, $token)) {
                return new JsonResponse(['success' => false, 'message' => 'Token inválido'], 403);
            }

            // Verificar que la cita se puede confirmar
            if (!in_array($appointment->getStatus(), [StatusEnum::SCHEDULED])) {
                return new JsonResponse(['success' => false, 'message' => 'La cita no se puede confirmar en su estado actual'], 400);
            }

            // Confirmar la cita
            $appointment->setStatus(StatusEnum::CONFIRMED);
            $appointment->setUpdatedAt(new \DateTime());
            $this->entityManager->flush();

            return new JsonResponse([
                'success' => true, 
                'message' => 'Cita confirmada exitosamente',
                'appointment' => [
                    'id' => $appointment->getId(),
                    'status' => $appointment->getStatus()->value,
                    'scheduled_at' => $appointment->getScheduledAt()->format('Y-m-d H:i:s')
                ]
            ]);

        } catch (\Exception $e) {
            return new JsonResponse(['success' => false, 'message' => 'Error interno del servidor'], 500);
        }
    }

    /**
     * Cancelar cita
     */
    private function cancelAppointmentResponse(string $domain, int $appointmentId, string $token): JsonResponse
    {
        try {
            // Verificar que el dominio existe
            $company = $this->domainRoutingService->getCompanyByDomain($domain);
            if (!$company) {
                return new JsonResponse(['success' => false, 'message' => 'Dominio no encontrado'], 404);
            }

            // Buscar la cita
            $appointment = $this->entityManager->getRepository(Appointment::class)->find($appointmentId);
            if (!$appointment || $appointment->getCompany() !== $company) {
                return new JsonResponse(['success' => false, 'message' => 'Cita no encontrada'], 404);
            }

            // Verificar token de seguridad
            if (!$this->verifyAppointmentToken($appointment, $token)) {
                return new JsonResponse(['success' => false, 'message' => 'Token inválido'], 403);
            }

            // Verificar que la cita se puede cancelar
            if (!$appointment->canBeCancelled()) {
                return new JsonResponse(['success' => false, 'message' => 'La cita no se puede cancelar'], 400);
            }

            // Cancelar la cita
            $appointment->setStatus(StatusEnum::CANCELLED);
            $appointment->setUpdatedAt(new \DateTime());
            $this->entityManager->flush();

            return new JsonResponse([
                'success' => true, 
                'message' => 'Cita cancelada exitosamente',
                'appointment' => [
                    'id' => $appointment->getId(),
                    'status' => $appointment->getStatus()->value,
                    'scheduled_at' => $appointment->getScheduledAt()->format('Y-m-d H:i:s')
                ]
            ]);

        } catch (\Exception $e) {
            return new JsonResponse(['success' => false, 'message' => 'Error interno del servidor'], 500);
        }
    }

    /**
     * Redirigir a modificar cita (redirige al sistema de reservas)
     */
    private function modifyAppointmentResponse(string $domain, int $appointmentId, string $token): JsonResponse
    {
        try {
            // Verificar que el dominio existe
            $company = $this->domainRoutingService->getCompanyByDomain($domain);
            if (!$company) {
                return new JsonResponse(['success' => false, 'message' => 'Dominio no encontrado'], 404);
            }

            // Buscar la cita
            $appointment = $this->entityManager->getRepository(Appointment::class)->find($appointmentId);
            if (!$appointment || $appointment->getCompany() !== $company) {
                return new JsonResponse(['success' => false, 'message' => 'Cita no encontrada'], 404);
            }

            // Verificar token de seguridad
            if (!$this->verifyAppointmentToken($appointment, $token)) {
                return new JsonResponse(['success' => false, 'message' => 'Token inválido'], 403);
            }

            // Verificar que la cita se puede modificar
            if (!$company->canEditAppointment($appointment)) {
                return new JsonResponse(['success' => false, 'message' => 'La cita no se puede modificar'], 400);
            }

            // Redirigir al sistema de reservas con información de la cita
            $redirectUrl = "https://{$domain}?edit_appointment={$appointmentId}&token={$token}";
            
            return new JsonResponse([
                'success' => true, 
                'message' => 'Redirigiendo al sistema de modificación',
                'redirect_url' => $redirectUrl,
                'appointment' => [
                    'id' => $appointment->getId(),
                    'status' => $appointment->getStatus()->value,
                    'scheduled_at' => $appointment->getScheduledAt()->format('Y-m-d H:i:s')
                ]
            ]);

        } catch (\Exception $e) {
            return new JsonResponse(['success' => false, 'message' => 'Error interno del servidor'], 500);
        }
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