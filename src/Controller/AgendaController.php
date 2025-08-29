<?php

namespace App\Controller;

use App\Entity\Appointment;
use App\Entity\Patient;
use App\Entity\Professional;
use App\Entity\ProfessionalService;
use App\Entity\Service;
use App\Entity\StatusEnum;
use App\Repository\ProfessionalRepository;
use App\Repository\ServiceRepository;
use App\Service\PatientService;
use App\Service\AuditService;
use App\Service\AppointmentService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Service\TimeSlot;

#[Route('/agenda')]
#[IsGranted('ROLE_ADMIN')]
class AgendaController extends AbstractController
{
    private EntityManagerInterface $entityManager;
    private ProfessionalRepository $professionalRepository;
    private ServiceRepository $serviceRepository;
    private TimeSlot $timeSlotService;
    private PatientService $patientService;

    public function __construct(
        EntityManagerInterface $entityManager,
        ProfessionalRepository $professionalRepository,
        ServiceRepository $serviceRepository,
        TimeSlot $timeSlotService,
        PatientService $patientService
    ) {
        $this->entityManager = $entityManager;
        $this->professionalRepository = $professionalRepository;
        $this->serviceRepository = $serviceRepository;
        $this->timeSlotService = $timeSlotService;
        $this->patientService = $patientService;
    }

    #[Route('/', name: 'app_agenda_index', methods: ['GET'])]
    public function index(): Response
    {
        $user = $this->getUser();
        $clinic = $user->getOwnedClinics()->first();
        
        if (!$clinic) {
            throw $this->createNotFoundException('No se encontró la clínica.');
        }

        // Obtener profesionales y servicios de la clínica
        $professionals = $this->professionalRepository->findBy(['clinic' => $clinic, 'active' => true]);
        $services = $this->serviceRepository->findBy(['clinic' => $clinic, 'active' => true]);

        return $this->render('agenda/index.html.twig', [
            'professionals' => $professionals,
            'services' => $services,
            'clinic' => $clinic
        ]);
    }

    #[Route('/appointments', name: 'app_agenda_appointments', methods: ['GET'])]
    public function getAppointments(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $clinic = $user->getOwnedClinics()->first();
        
        $startDate = new \DateTime($request->query->get('start', 'now'));
        $endDate = new \DateTime($request->query->get('end', '+1 week'));
        // Cambiar los nombres de parámetros para coincidir con el frontend
        $professionalId = $request->query->get('professional'); // Era 'professional_id'
        $serviceId = $request->query->get('service'); // Era 'service_id'

        $queryBuilder = $this->entityManager->createQueryBuilder()
            ->select('a', 'p', 's', 'pat')
            ->from(Appointment::class, 'a')
            ->leftJoin('a.professional', 'p')
            ->leftJoin('a.service', 's')
            ->leftJoin('a.patient', 'pat')
            ->where('a.clinic = :clinic')
            ->andWhere('a.scheduledAt BETWEEN :start AND :end')
            // Opción alternativa: mostrar solo estados específicos
            ->andWhere('a.status IN (:allowedStatuses)')
            ->setParameter('allowedStatuses', [
                StatusEnum::SCHEDULED,
                StatusEnum::CONFIRMED,
                StatusEnum::COMPLETED,
            ])
            ->setParameter('clinic', $clinic)
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->orderBy('a.scheduledAt', 'ASC');

        if ($professionalId) {
            $queryBuilder->andWhere('p.id = :professionalId')
                        ->setParameter('professionalId', $professionalId);
        }

        if ($serviceId) {
            $queryBuilder->andWhere('s.id = :serviceId')
                        ->setParameter('serviceId', $serviceId);
        }

        $appointments = $queryBuilder->getQuery()->getResult();

        $events = [];
        foreach ($appointments as $appointment) {
            // En el método getAppointments, enriquecer la información
            $endTime = clone $appointment->getScheduledAt();
            $endTime->modify('+' . $appointment->getDurationMinutes() . ' minutes');

            $events[] = [
                'id' => $appointment->getId(),
                'title' => sprintf('%s - %s (%s)', 
                    $appointment->getProfessional()->getName(),
                    $appointment->getPatient()->getName(),
                    $appointment->getService()?->getName() ?? 'Sin servicio'
                ),
                'start' => $appointment->getScheduledAt()->format('Y-m-d\\TH:i:s'),
                'end' => $endTime->format('Y-m-d\\TH:i:s'),
                'backgroundColor' => $this->getProfessionalColor($appointment->getProfessional()->getId()),
                'borderColor' => $this->getStatusBorderColor($appointment->getStatus()),
                'extendedProps' => [
                    'professionalId' => $appointment->getProfessional()->getId(),
                    'professionalName' => $appointment->getProfessional()->getName(),
                    'patientId' => $appointment->getPatient()->getId(), // Agregar esta línea
                    'patientName' => $appointment->getPatient()->getName(),
                    'email' => $appointment->getPatient()->getEmail(), // También agregar email para consistencia
                    'serviceName' => $appointment->getService()?->getName(),
                    'serviceId' => $appointment->getService()?->getId(), // También agregar serviceId
                    'status' => $appointment->getStatus(),
                    'phone' => $appointment->getPatient()->getPhone(),
                    'notes' => $appointment->getNotes()
                ]
            ];
        }

        return new JsonResponse($events);
    }

    #[Route('/available-slots', name: 'app_agenda_available_slots', methods: ['GET'])]
    public function getAvailableSlots(Request $request): JsonResponse
    {
        $professionalId = $request->query->get('professional') ?? $request->query->get('professional_id');
        $serviceId = $request->query->get('service') ?? $request->query->get('service_id');
        $date = new \DateTime($request->query->get('date', 'today'));
        $interval = (int)$request->query->get('interval', 30);

        if (!$professionalId || !$serviceId) {
            return new JsonResponse(['error' => 'Professional y Service son requeridos'], 400);
        }

        $professional = $this->professionalRepository->find($professionalId);
        $service = $this->serviceRepository->find($serviceId);
        
        if (!$professional || !$service) {
            return new JsonResponse(['error' => 'Professional o Service no encontrado'], 404);
        }

        $slots = $this->timeSlotService->generateAvailableSlots(
            $professional, 
            $service, 
            $date, 
            $interval
        );
        
        return new JsonResponse($slots);
    }

    #[Route('/business-hours', name: 'app_agenda_business_hours', methods: ['GET'])]
    public function getBusinessHours(Request $request): JsonResponse {
        $user = $this->getUser();
        $clinic = $user->getOwnedClinics()->first();
        
        if (!$clinic) {
            return new JsonResponse(['error' => 'No se encontró la clínica'], 404);
        }

        // Obtener parámetros de filtro
        $professionalId = $request->query->get('professional');
        $serviceId = $request->query->get('service');
    
        // Construir consulta base para horarios de disponibilidad
        $queryBuilder = $this->entityManager->createQueryBuilder()
            ->select('pa.weekday', 'MIN(pa.startTime) as minStartTime', 'MAX(pa.endTime) as maxEndTime')
            ->from('App\Entity\ProfessionalAvailability', 'pa')
            ->join('pa.professional', 'p')
            ->where('p.clinic = :clinic')
            ->andWhere('p.active = true')
            ->setParameter('clinic', $clinic);
    
        // Filtrar por profesional si se especifica
        if ($professionalId) {
            $queryBuilder->andWhere('p.id = :professionalId')
                        ->setParameter('professionalId', $professionalId);
        }
    
        // Filtrar por servicio si se especifica (profesionales que ofrecen ese servicio)
        if ($serviceId) {
            $queryBuilder->join('p.professionalServices', 'ps')
                        ->join('ps.service', 's')
                        ->andWhere('s.id = :serviceId')
                        ->setParameter('serviceId', $serviceId);
        }
    
        $queryBuilder->groupBy('pa.weekday')
                    ->orderBy('pa.weekday', 'ASC');
    
        $availabilities = $queryBuilder->getQuery()->getResult();
        
        // Construir consulta para horarios globales con los mismos filtros
        $globalQuery = $this->entityManager->createQueryBuilder()
            ->select('MIN(pa.startTime) as globalMinStart', 'MAX(pa.endTime) as globalMaxEnd')
            ->from('App\Entity\ProfessionalAvailability', 'pa')
            ->join('pa.professional', 'p')
            ->where('p.clinic = :clinic')
            ->andWhere('p.active = true')
            ->setParameter('clinic', $clinic);
    
        // Aplicar los mismos filtros a la consulta global
        if ($professionalId) {
            $globalQuery->andWhere('p.id = :professionalId')
                       ->setParameter('professionalId', $professionalId);
        }
    
        if ($serviceId) {
            $globalQuery->join('p.professionalServices', 'ps')
                       ->join('ps.service', 's')
                       ->andWhere('s.id = :serviceId')
                       ->setParameter('serviceId', $serviceId);
        }
    
        $globalTimes = $globalQuery->getQuery()->getSingleResult();
    
        // Procesar días de la semana disponibles
        $daysOfWeek = [];
        foreach ($availabilities as $availability) {
            // Convertir de 0-6 (Lun-Dom) a 1-7 (Lun-Dom) para FullCalendar
            $daysOfWeek[] = $availability['weekday'] + 1;
        }
    
        // Si no hay días disponibles, usar configuración por defecto
        if (empty($daysOfWeek)) {
            $daysOfWeek = [1, 2, 3, 4, 5, 6]; // Lunes a Sábado por defecto
        }
    
        // Formatear horarios globales - las funciones MIN/MAX devuelven strings, no DateTime
        $startTime = $globalTimes['globalMinStart'] ? 
            substr($globalTimes['globalMinStart'], 0, 5) : '08:00'; // Extraer HH:MM del string
        $endTime = $globalTimes['globalMaxEnd'] ? 
            substr($globalTimes['globalMaxEnd'], 0, 5) : '18:00'; // Extraer HH:MM del string
    
        return new JsonResponse([
            'daysOfWeek' => array_unique($daysOfWeek),
            'startTime' => $startTime,
            'endTime' => $endTime,
            'slotMinTime' => $startTime . ':00',
            'slotMaxTime' => $endTime . ':00'
        ]);
    }


    /**
     * API para obtener el siguiente slot disponible
     */
    #[Route('/next-available-slot', name: 'app_agenda_next_slot', methods: ['GET'])]
    public function getNextAvailableSlot(Request $request): JsonResponse
    {
        $professionalId = $request->query->get('professional_id');
        $serviceId = $request->query->get('service_id');
        $fromDate = $request->query->get('from_date') ? 
            new \DateTime($request->query->get('from_date')) : 
            new \DateTime();

        if (!$professionalId || !$serviceId) {
            return new JsonResponse(['error' => 'Professional y Service son requeridos'], 400);
        }

        $professional = $this->professionalRepository->find($professionalId);
        $service = $this->serviceRepository->find($serviceId);
        
        if (!$professional || !$service) {
            return new JsonResponse(['error' => 'Professional o Service no encontrado'], 404);
        }

        $nextSlot = $this->timeSlotService->getNextAvailableSlot(
            $professional, 
            $service, 
            $fromDate
        );
        
        if (!$nextSlot) {
            return new JsonResponse(['error' => 'No hay slots disponibles en los próximos 30 días'], 404);
        }
        
        return new JsonResponse($nextSlot);
    }

    /**
     * API para obtener estadísticas de disponibilidad
     */
    #[Route('/availability-stats', name: 'app_agenda_stats', methods: ['GET'])]
    public function getAvailabilityStats(Request $request): JsonResponse
    {
        $professionalId = $request->query->get('professional_id');
        $serviceId = $request->query->get('service_id');
        $startDate = new \DateTime($request->query->get('start_date', 'today'));
        $endDate = new \DateTime($request->query->get('end_date', '+7 days'));

        if (!$professionalId || !$serviceId) {
            return new JsonResponse(['error' => 'Professional y Service son requeridos'], 400);
        }

        $professional = $this->professionalRepository->find($professionalId);
        $service = $this->serviceRepository->find($serviceId);
        
        if (!$professional || !$service) {
            return new JsonResponse(['error' => 'Professional o Service no encontrado'], 404);
        }

        $stats = $this->timeSlotService->getAvailabilityStats(
            $professional, 
            $service, 
            $startDate, 
            $endDate
        );
        
        return new JsonResponse($stats);
    }

    #[Route('/appointment', name: 'app_agenda_create_appointment', methods: ['POST'])]
    public function createAppointment(Request $request, AppointmentService $appointmentService): JsonResponse
    {
        // Manejar tanto FormData como JSON
        $contentType = $request->headers->get('Content-Type');
        
        if (str_contains($contentType, 'application/json')) {
            $data = json_decode($request->getContent(), true);
        } else {
            $data = $request->request->all();
        }
        
        $user = $this->getUser();
        $clinic = $user->getOwnedClinics()->first();
        
        try {
            $appointment = $appointmentService->createAppointment($data, $clinic);
            
            return new JsonResponse([
                'success' => true,
                'appointment' => $appointmentService->appointmentToArray($appointment)
            ]);
            
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        } catch (\Throwable $e) {
            error_log('Error creating appointment: ' . $e->getMessage());
            return new JsonResponse([
                'success' => false,
                'error' => 'Ha ocurrido un error interno. Por favor, inténtelo nuevamente.'
            ], 500);
        }
    }

    #[Route('/appointment/{id}', name: 'app_agenda_update_appointment', methods: ['PUT'])]
    public function updateAppointment(Request $request, int $id, AuditService $auditService): JsonResponse
    {
        $appointment = $this->entityManager->getRepository(Appointment::class)->find($id);
        
        if (!$appointment) {
            return new JsonResponse(['success' => false, 'message' => 'Turno no encontrado'], 404);
        }
    
        // Obtener datos JSON del request
        $data = json_decode($request->getContent(), true);
        
        if (!$data) {
            return new JsonResponse(['success' => false, 'message' => 'Datos inválidos'], 400);
        }
    
        $user = $this->getUser();
        $clinic = $user->getOwnedClinics()->first();
    
        try {
            // CAPTURAR VALORES ANTERIORES ANTES DE MODIFICAR
            $oldValues = [
                'patient_id' => $appointment->getPatient()?->getId(),
                'professional_id' => $appointment->getProfessional()?->getId(),
                'service_id' => $appointment->getService()?->getId(),
                'scheduled_at' => $appointment->getScheduledAt()?->format('Y-m-d H:i:s'),
                'duration_minutes' => $appointment->getDurationMinutes(),
                'status' => $appointment->getStatus()?->value,
                'notes' => $appointment->getNotes(),
            ];
            
            // Mapear nombres de campos del formulario a los esperados
            $professionalId = $data['professional_id'] ?? $data['professional'] ?? null;
            $serviceId = $data['service_id'] ?? $data['service'] ?? null;
            
            // Construir la fecha y hora programada
            if (isset($data['scheduled_at'])) {
                $scheduledAt = new \DateTime($data['scheduled_at']);
            } elseif (isset($data['date']) && isset($data['time'])) {
                // Si viene del formulario HTML, combinar date y time
                if (str_contains($data['time'], 'T')) {
                    // Si time ya es un datetime completo
                    $scheduledAt = new \DateTime($data['time']);
                } else {
                    // Si time es solo la hora
                    $scheduledAt = new \DateTime($data['date'] . ' ' . $data['time']);
                }
            } else {
                throw new \InvalidArgumentException('Fecha y hora son requeridas');
            }
            
            // Actualizar profesional si se proporciona
            if ($professionalId) {
                $professional = $this->professionalRepository->find($professionalId);
                if (!$professional) {
                    throw new \InvalidArgumentException('Profesional no encontrado');
                }
                $appointment->setProfessional($professional);
            }
            
            // Actualizar servicio si se proporciona
            if ($serviceId) {
                $service = $this->serviceRepository->find($serviceId);
                if (!$service) {
                    throw new \InvalidArgumentException('Servicio no encontrado');
                }
                $appointment->setService($service);
                
                // Obtener duración efectiva del servicio
                $professionalService = $this->entityManager->getRepository(ProfessionalService::class)
                    ->findOneBy(['professional' => $appointment->getProfessional(), 'service' => $service]);
                
                $duration = $professionalService ? $professionalService->getEffectiveDuration() : $service->getDurationMinutes();
                $appointment->setDurationMinutes($duration);
            }
            
            // Preparar datos del paciente
            $patientData = [];
            if (isset($data['patient_id']) && !empty($data['patient_id'])) {
                $patientData['id'] = $data['patient_id'];
            }
            if (isset($data['patient_name'])) {
                $patientData['name'] = $data['patient_name'];
            }
            if (isset($data['patient_email'])) {
                $patientData['email'] = $data['patient_email'];
            }
            if (isset($data['patient_phone'])) {
                $patientData['phone'] = $data['patient_phone'];
            }
            if (isset($data['patient_birth_date'])) {
                $patientData['birth_date'] = $data['patient_birth_date'];
            }
            
            // Actualizar o crear paciente
            if (!empty($patientData)) {
                $patient = $this->patientService->findOrCreatePatient($patientData, $clinic);
                $appointment->setPatient($patient);
            }
            
            // Actualizar fecha y hora programada
            $appointment->setScheduledAt($scheduledAt);
            
            // Actualizar notas si se proporcionan
            if (isset($data['notes'])) {
                $appointment->setNotes($data['notes']);
            }
            
            // Actualizar timestamp
            $appointment->setUpdatedAt(new \DateTime());
            
            $this->entityManager->flush();
            
            // CAPTURAR VALORES NUEVOS DESPUÉS DE MODIFICAR
            $newValues = [
                'patient_id' => $appointment->getPatient()?->getId(),
                'professional_id' => $appointment->getProfessional()?->getId(),
                'service_id' => $appointment->getService()?->getId(),
                'scheduled_at' => $appointment->getScheduledAt()?->format('Y-m-d H:i:s'),
                'duration_minutes' => $appointment->getDurationMinutes(),
                'status' => $appointment->getStatus()?->value,
                'notes' => $appointment->getNotes(),
            ];
            
            // REGISTRAR EN AUDITORÍA
            $auditService->logChange(
                'appointment',
                $appointment->getId(),
                'update',
                $oldValues,
                $newValues
            );
            
            // Calcular hora de finalización
            $endTime = clone $appointment->getScheduledAt();
            $endTime->modify('+' . $appointment->getDurationMinutes() . ' minutes');
            
            return new JsonResponse([
                'success' => true,
                'message' => 'Turno actualizado correctamente',
                'appointment' => [
                    'id' => $appointment->getId(),
                    'start' => $appointment->getScheduledAt()->format('Y-m-d\\TH:i:s'),
                    'end' => $endTime->format('Y-m-d\\TH:i:s'),
                    'title' => sprintf('%s - %s (%s)', 
                        $appointment->getProfessional()->getName(),
                        $appointment->getPatient()->getName(),
                        $appointment->getService()?->getName() ?? 'Sin servicio'
                    ),
                    'professionalId' => $appointment->getProfessional()->getId(),
                    'professionalName' => $appointment->getProfessional()->getName(),
                    'patientId' => $appointment->getPatient()->getId(),
                    'patientName' => $appointment->getPatient()->getName(),
                    'patientEmail' => $appointment->getPatient()->getEmail(),
                    'patientPhone' => $appointment->getPatient()->getPhone(),
                    'serviceId' => $appointment->getService()?->getId(),
                    'serviceName' => $appointment->getService()?->getName(),
                    'status' => $appointment->getStatus()->value,
                    'notes' => $appointment->getNotes(),
                    'durationMinutes' => $appointment->getDurationMinutes()
                ]
            ]);
            
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false, 
                'message' => 'Error al actualizar el turno: ' . $e->getMessage()
            ], 400);
        }
    }

    private function generateAvailableSlots(Professional $professional, Service $service, \DateTime $date): array
    {
        $slots = [];
        $dayOfWeek = (int)$date->format('N') - 1; // 0=Lunes, 6=Domingo
        
        // Obtener disponibilidad del profesional para este día
        $availabilities = $professional->getAvailabilities()->filter(
            fn($availability) => $availability->getWeekday() === $dayOfWeek
        );
        
        foreach ($availabilities as $availability) {
            $startTime = clone $date;
            $startTime->setTime(
                (int)$availability->getStartTime()->format('H'),
                (int)$availability->getStartTime()->format('i')
            );
            
            $endTime = clone $date;
            $endTime->setTime(
                (int)$availability->getEndTime()->format('H'),
                (int)$availability->getEndTime()->format('i')
            );
            
            // Generar slots cada 30 minutos
            $current = clone $startTime;
            while ($current < $endTime) {
                $slotEnd = clone $current;
                $slotEnd->modify('+' . $service->getDurationMinutes() . ' minutes');
                
                if ($slotEnd <= $endTime && !$this->isSlotOccupied($professional, $current, $slotEnd)) {
                    $slots[] = [
                        'time' => $current->format('H:i'),
                        'datetime' => $current->format('c'),
                        'available' => true
                    ];
                }
                
                $current->modify('+30 minutes');
            }
        }
        
        return $slots;
    }
    
    private function isSlotOccupied(Professional $professional, \DateTime $start, \DateTime $end): bool
    {
        $appointments = $this->entityManager->getRepository(Appointment::class)
            ->createQueryBuilder('a')
            ->where('a.professional = :professional')
            ->andWhere('a.scheduledAt < :end')
            ->andWhere('DATE_ADD(a.scheduledAt, a.durationMinutes, \'MINUTE\') > :start')
            ->setParameter('professional', $professional)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getResult();
            
        return count($appointments) > 0;
    }
    
    private function getAppointmentTitle(Appointment $appointment): string
    {
        $patientName = $appointment->getPatient()?->getName() ?? 'Sin paciente';
        $serviceName = $appointment->getService()?->getName() ?? 'Sin servicio';
        
        return $patientName . ' - ' . $serviceName;
    }
    
    private function getStatusColor(\App\Entity\StatusEnum $status): string
    {
        return match($status) {
            \App\Entity\StatusEnum::SCHEDULED => '#007bff',
            \App\Entity\StatusEnum::CONFIRMED => '#28a745',
            \App\Entity\StatusEnum::CANCELLED => '#dc3545',
            \App\Entity\StatusEnum::COMPLETED => '#6c757d',
            default => '#007bff'
        };
    }
    
    /**
     * Obtiene un color único para cada profesional basado en su ID
     */
    private function getProfessionalColor(int $professionalId): string
    {
        $colors = [
            '#3498db', // Azul
            '#e74c3c', // Rojo
            '#2ecc71', // Verde
            '#f39c12', // Naranja
            '#9b59b6', // Púrpura
            '#1abc9c', // Turquesa
            '#34495e', // Gris oscuro
            '#e67e22', // Naranja oscuro
            '#95a5a6', // Gris
            '#16a085', // Verde azulado
        ];
        
        return $colors[$professionalId % count($colors)];
    }
    
    /**
     * Obtiene el color del borde basado en el estado del turno
     */
    private function getStatusBorderColor(\App\Entity\StatusEnum $status): string
    {
        return match($status) {
            \App\Entity\StatusEnum::SCHEDULED => '#0056b3',    // Azul más oscuro
            \App\Entity\StatusEnum::CONFIRMED => '#1e7e34',    // Verde más oscuro
            \App\Entity\StatusEnum::CANCELLED => '#bd2130',    // Rojo más oscuro
            \App\Entity\StatusEnum::COMPLETED => '#545b62',    // Gris más oscuro
            default => '#0056b3'
        };
    }

    /**
     * Obtiene los servicios de un profesional específico
     */
    #[Route('/professional/{id}/services', name: 'app_agenda_professional_services', methods: ['GET'])]
    public function getProfessionalServices(int $id): JsonResponse
    {
        $user = $this->getUser();
        $clinic = $user->getOwnedClinics()->first();
        
        if (!$clinic) {
            return new JsonResponse(['error' => 'No se encontró la clínica'], 404);
        }

        $professional = $this->professionalRepository->find($id);
        
        if (!$professional || $professional->getClinic() !== $clinic) {
            return new JsonResponse(['error' => 'Profesional no encontrado'], 404);
        }

        $services = [];
        foreach ($professional->getProfessionalServices() as $professionalService) {
            $service = $professionalService->getService();
            if ($service->isActive()) {
                $services[] = [
                    'id' => $service->getId(),
                    'name' => $service->getName(),
                    'duration' => $professionalService->getEffectiveDuration() ?? $service->getDurationMinutes(),
                    'price' => $professionalService->getEffectivePrice() ?? $service->getPrice()
                ];
            }
        }

        return new JsonResponse($services);
    }

    /**
     * API para buscar pacientes
     */
    #[Route('/search-patients', name: 'app_agenda_search_patients', methods: ['GET'])]
    public function searchPatients(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $clinic = $user->getOwnedClinics()->first();
        
        if (!$clinic) {
            return new JsonResponse(['error' => 'No se encontró la clínica'], 404);
        }
    
        $query = $request->query->get('q', '');
        
        if (strlen($query) < 2) {
            return new JsonResponse([]);
        }
    
        $patients = $this->entityManager->getRepository(Patient::class)
            ->createQueryBuilder('p')
            ->where('p.clinic = :clinic')
            ->andWhere('(
                p.name LIKE :query OR 
                p.email LIKE :query OR 
                p.phone LIKE :query
            )')
            ->setParameter('clinic', $clinic)
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('p.name', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();
    
        $result = [];
        foreach ($patients as $patient) {
            $result[] = [
                'id' => $patient->getId(),
                'name' => $patient->getName(),
                'email' => $patient->getEmail(),
                'phone' => $patient->getPhone(),
                // 'birth_date' => $patient->getBirthDate() ? $patient->getBirthDate()->format('Y-m-d') : null
            ];
        }
    
        return new JsonResponse($result);
    }

    #[Route('/professionals', name: 'app_agenda_professionals', methods: ['GET'])]
    public function getProfessionals(): JsonResponse
    {
        $user = $this->getUser();
        $clinic = $user->getOwnedClinics()->first();
        
        if (!$clinic) {
            return new JsonResponse(['error' => 'Clínica no encontrada'], 404);
        }

        $professionals = $this->professionalRepository->findBy([
            'clinic' => $clinic, 
            'active' => true
        ]);

        $professionalData = [];
        foreach ($professionals as $professional) {
            $professionalData[] = [
                'id' => $professional->getId(),
                'name' => $professional->getName()
            ];
        }

        return new JsonResponse($professionalData);
    }

    /**
     * Actualiza el estado de un turno
     */
    #[Route('/appointments/{id}/status', name: 'app_agenda_update_appointment_status', methods: ['PATCH'])]
    public function updateAppointmentStatus(int $id, Request $request, AuditService $auditService): JsonResponse {
        $user = $this->getUser();
        $clinic = $user->getOwnedClinics()->first();
        
        if (!$clinic) {
            return new JsonResponse(['error' => 'No se encontró la clínica'], 404);
        }

        $appointment = $this->entityManager->getRepository(Appointment::class)->find($id);
        
        if (!$appointment || $appointment->getClinic() !== $clinic) {
            return new JsonResponse(['error' => 'Turno no encontrado'], 404);
        }

        $data = json_decode($request->getContent(), true);
        $newStatus = $data['status'] ?? null;
        
        if (!$newStatus) {
            return new JsonResponse(['error' => 'Estado requerido'], 400);
        }

        // Validar que el estado sea válido
        try {
            $statusEnum = \App\Entity\StatusEnum::from($newStatus);
        } catch (\ValueError $e) {
            return new JsonResponse(['error' => 'Estado inválido'], 400);
        }

        // Capturar valor anterior
        $oldStatus = $appointment->getStatus()?->value;
        
        $appointment->setStatus($statusEnum);
        $this->entityManager->flush();
        
        // Registrar cambio de estado
        $auditService->logChange(
            'appointment',
            $appointment->getId(),
            'status_change',
            ['status' => $oldStatus],
            ['status' => $statusEnum->value]
        );
    
        return new JsonResponse([
            'success' => true,
            'message' => 'Estado actualizado correctamente',
            'appointment' => [
                'id' => $appointment->getId(),
                'status' => $appointment->getStatus()->value
            ]
        ]);
    }
}