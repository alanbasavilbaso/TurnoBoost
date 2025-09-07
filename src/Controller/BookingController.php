<?php

namespace App\Controller;

use App\Entity\Location;
use App\Entity\Service;
use App\Entity\Professional;
use App\Entity\ProfessionalService;
use App\Service\AppointmentService;
use App\Service\TimeSlot;
use App\Service\SettingsService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class BookingController extends AbstractController
{
    private EntityManagerInterface $entityManager;
    private TimeSlot $timeSlotService;
    private SettingsService $settingsService;

    public function __construct(EntityManagerInterface $entityManager, TimeSlot $timeSlotService)
    {
        $this->entityManager = $entityManager;
        $this->timeSlotService = $timeSlotService;
    }

    /**
     * Página principal de reservas
     * Detecta automáticamente si es subdominio o path
     */
    #[Route('/', name: 'booking_index_subdomain', host: '{domain}.{base_domain}', requirements: ['domain' => '[a-z0-9-]+'], priority: 2)]
    #[Route('/reservas/{domain}', name: 'booking_index_path', requirements: ['domain' => '[a-z0-9-]+'], priority: 1)]
    public function index(string $domain = null, Request $request): Response
    {
        // Si no hay dominio en la ruta, intentar obtenerlo del host
        if (!$domain) {
            $host = $request->getHost();
            $parts = explode('.', $host);
            $domain = $parts[0] ?? null;
        }
        
        if (!$domain) {
            throw $this->createNotFoundException('Domain not found');
        }
        
        $location = $this->getLocationByDomain($domain);
        
        return $this->render('booking/index.html.twig', [
            'location' => $location,
            'domain' => $domain
        ]);
    }
    

    /**
     * API: Obtener servicios activos de el local
     */
    #[Route('/reservas/{domain}/api/services', name: 'booking_api_services', methods: ['GET'])]
    public function getServices(string $domain): JsonResponse
    {
        $location = $this->getLocationByDomain($domain);
        
        $services = $this->entityManager->getRepository(Service::class)
            ->createQueryBuilder('s')
            ->where('s.location = :location')
            ->setParameter('location', $location)
            ->getQuery()
            ->getResult();

        $servicesData = [];
        foreach ($services as $service) {
            $servicesData[] = [
                'id' => $service->getId(),
                'name' => $service->getName(),
                'description' => $service->getDescription(),
                'duration' => $service->getDurationMinutes(),
                'durationFormatted' => $this->formatDuration($service->getDurationMinutes()),
                'type' => $service->getServiceType()->value
            ];
        }

        return new JsonResponse($servicesData);
    }

    /**
     * API: Obtener profesionales que ofrecen un servicio específico
     */
    #[Route('/reservas/{domain}/api/professionals/{serviceId}', name: 'booking_api_professionals', methods: ['GET'])]
    public function getProfessionalsByService(string $domain, int $serviceId): JsonResponse
    {
        $location = $this->getLocationByDomain($domain);
        
        $service = $this->entityManager->getRepository(Service::class)->find($serviceId);
        if (!$service || $service->getLocation() !== $location) {
            throw new NotFoundHttpException('Servicio no encontrado');
        }

        $professionalServices = $this->entityManager->getRepository(ProfessionalService::class)
            ->createQueryBuilder('ps')
            ->join('ps.professional', 'p')
            ->where('ps.service = :service')
            ->andWhere('p.location = :location')
            ->setParameter('service', $service)
            ->setParameter('location', $location)
            ->getQuery()
            ->getResult();

        $professionalsData = [];
        foreach ($professionalServices as $professionalService) {
            $professional = $professionalService->getProfessional();
            $professionalsData[] = [
                'id' => $professional->getId(),
                'name' => $professional->getName(),
                'fullName' => $professional->getName(), // Solo usar el nombre completo
                'specialization' => $professional->getSpecialty(), // Cambiar getSpecialization() por getSpecialty()
                'phone' => $professional->getPhone(),
                'email' => $professional->getEmail(),
                'price' => $professionalService->getEffectivePrice(),
                'priceFormatted' => number_format($professionalService->getEffectivePrice(), 2, '.', ','),
                'customPrice' => $professionalService->getCustomPrice(),
                'professionalServiceId' => $professionalService->getId()
            ];
        }

        return new JsonResponse($professionalsData);
    }

    /**
     * API: Obtener horarios disponibles para un profesional en una fecha
     */
    #[Route('/reservas/{domain}/api/timeslots', name: 'booking_api_timeslots', methods: ['GET'])]
    public function getTimeSlots(string $domain, Request $request): JsonResponse
    {
        $location = $this->getLocationByDomain($domain);
        $professionalId = $request->query->get('professional');
        $serviceId = $request->query->get('service');
        $date = $request->query->get('date');
        
        if (!$professionalId || !$serviceId || !$date) {
            return new JsonResponse(['error' => 'Faltan parámetros requeridos (professional, service, date)'], 400);
        }

        try {
            $dateObj = new \DateTime($date);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Formato de fecha inválido'], 400);
        }

        $professional = $this->entityManager->getRepository(Professional::class)->find($professionalId);
        if (!$professional || $professional->getLocation() !== $location) {
            return new JsonResponse(['error' => 'Profesional no encontrado'], 404);
        }

        $service = $this->entityManager->getRepository(Service::class)->find($serviceId);
        if (!$service || $service->getLocation() !== $location) {
            return new JsonResponse(['error' => 'Servicio no encontrado'], 404);
        }

        // Generar slots disponibles usando el servicio TimeSlot
        // En getTimeSlots:
        $dateObj = $location->createLocalDateTime($date);
        $slots = $this->timeSlotService->generateAvailableSlots(
            $professional, 
            $service, 
            $dateObj,
            $location // Pasar location
        );
        
        // Organizar slots por mañana y tarde
        $timeSlots = $this->organizeSlotsByPeriod($slots);

        return new JsonResponse([
            'date' => $date,
            'professional' => $professional->getName() . ' ' . $professional->getSpecialty(),
            'service' => $service->getName(),
            'timeSlots' => $timeSlots
        ]);
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
    private function getLocationByDomain(string $domain): Location
    {
        // Buscar la empresa por dominio
        $company = $this->entityManager->getRepository(Company::class)
            ->findOneBy(['domain' => $domain]);
            
        if (!$company) {
            throw new NotFoundHttpException(sprintf('No se encontró una empresa con el dominio "%s"', $domain));
        }
        
        // Obtener la primera ubicación activa de la empresa
        $location = $this->entityManager->getRepository(Location::class)
            ->findOneBy(['company' => $company, 'active' => true]);
            
        if (!$location) {
            throw new NotFoundHttpException(sprintf('No se encontró una ubicación activa para la empresa con dominio "%s"', $domain));
        }
        
        return $location;
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
     * API: Crear nueva cita desde el sistema de reservas público
     */
    #[Route('/reservas/{domain}/api/appointments', name: 'booking_api_create_appointment', methods: ['POST'])]
    public function createAppointment(string $domain, Request $request, AppointmentService $appointmentService): JsonResponse
    {
        $location = $this->getLocationByDomain($domain);
        $data = json_decode($request->getContent(), true);
        
        if (!$data) {
            return new JsonResponse(['error' => 'Datos inválidos'], 400);
        }
        
        try {
            $appointment = $appointmentService->createAppointment($data, $location);
            
            return new JsonResponse([
                'success' => true,
                'message' => 'Cita creada exitosamente',
                'appointment' => $appointmentService->appointmentToArray($appointment)
            ]);
            
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        } catch (\Throwable $e) {
            error_log('Error creating public appointment: ' . $e->getMessage());
            return new JsonResponse([
                'success' => false,
                'error' => 'Ha ocurrido un error interno. Por favor, inténtelo nuevamente.'
            ], 500);
        }
    }

    /**
     * Confirmar turno mediante URL segura
     */
    #[Route('/reservas/{domain}/appointment/{id}/confirm/{token}', name: 'booking_confirm_appointment', methods: ['GET'])]
    public function confirmAppointment(string $domain, int $id, string $token, AppointmentService $appointmentService): Response
    {
        $location = $this->getLocationByDomain($domain);
        
        try {
            // Validar token y confirmar turno
            $appointment = $appointmentService->confirmAppointmentByToken($id, $token, $location);
            
            return $this->render('booking/appointment_action.html.twig', [
                'action' => 'confirmed',
                'appointment' => $appointment,
                'location' => $location,
                'success' => true,
                'message' => '✅ Tu turno ha sido confirmado exitosamente'
            ]);
            
        } catch (\InvalidArgumentException $e) {
            return $this->render('booking/appointment_action.html.twig', [
                'action' => 'confirm',
                'success' => false,
                'error' => $e->getMessage(),
                'location' => $location
            ]);
        }
    }

    /**
     * Cancelar turno mediante URL segura
     */
    #[Route('/reservas/{domain}/appointment/{id}/cancel/{token}', name: 'booking_cancel_appointment', methods: ['GET'])]
    public function cancelAppointment(string $domain, int $id, string $token, AppointmentService $appointmentService): Response
    {
        $location = $this->getLocationByDomain($domain);
        
        try {
            // Validar token y cancelar turno
            $appointment = $appointmentService->cancelAppointmentByToken($id, $token, $location);
            
            return $this->render('booking/appointment_action.html.twig', [
                'action' => 'cancelled',
                'appointment' => $appointment,
                'location' => $location,
                'success' => true,
                'message' => '❌ Tu turno ha sido cancelado'
            ]);
            
        } catch (\InvalidArgumentException $e) {
            return $this->render('booking/appointment_action.html.twig', [
                'action' => 'cancel',
                'success' => false,
                'error' => $e->getMessage(),
                'location' => $location
            ]);
        }
    }

    #[Route('/booking/validate-date', name: 'booking_validate_date', methods: ['POST'])]
    public function validateDate(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $appointmentDate = new \DateTime($data['date'] ?? '');
        
        /** @var User $user */
        $user = $this->getUser();
        
        $errors = $this->settingsService->validateAppointmentDate($user, $appointmentDate);
        
        return new JsonResponse([
            'valid' => empty($errors),
            'errors' => $errors
        ]);
    }

    #[Route('/booking/create', name: 'booking_create', methods: ['POST'])]
    public function create(Request $request, AppointmentService $appointmentService): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            $appointmentDate = new \DateTime($data['appointment_date']);
            
            /** @var User $user */
            $user = $this->getUser();
            
            // Validar fecha según configuración
            $errors = $this->settingsService->validateAppointmentDate($user, $appointmentDate);
            
            if (!empty($errors)) {
                return new JsonResponse([
                    'success' => false,
                    'errors' => $errors
                ], 400);
            }

            // ... resto del código de creación de cita ...
            
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Error al crear la cita: ' . $e->getMessage()
            ], 500);
        }
    }
}