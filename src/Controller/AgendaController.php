<?php

namespace App\Controller;

use App\Entity\Appointment;
use App\Entity\Patient;
use App\Entity\Professional;
use App\Entity\ProfessionalService;
use App\Entity\Company;
use App\Entity\Service;
use App\Entity\StatusEnum;
use App\Entity\Location;
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
use App\Service\NotificationService;
use App\Service\PhoneUtilityService;

#[Route('/agenda')]
#[IsGranted('ROLE_ADMIN')]
class AgendaController extends AbstractController
{
    private EntityManagerInterface $entityManager;
    private ProfessionalRepository $professionalRepository;
    private ServiceRepository $serviceRepository;
    private TimeSlot $timeSlotService;
    private PatientService $patientService;
    private NotificationService $notificationService;
    private PhoneUtilityService $phoneUtilityService;

    public function __construct(
        EntityManagerInterface $entityManager,
        ProfessionalRepository $professionalRepository,
        ServiceRepository $serviceRepository,
        TimeSlot $timeSlotService,
        PatientService $patientService,
        NotificationService $notificationService,
        PhoneUtilityService $phoneUtilityService
    ) {
        $this->entityManager = $entityManager;
        $this->professionalRepository = $professionalRepository;
        $this->serviceRepository = $serviceRepository;
        $this->timeSlotService = $timeSlotService;
        $this->patientService = $patientService;
        $this->notificationService = $notificationService;
        $this->phoneUtilityService = $phoneUtilityService;
    }

    #[Route('/', name: 'app_agenda_index', methods: ['GET'])]
    public function index(): Response
    {
        $user = $this->getUser();
        $company = $user->getCompany();
        
        if (!$company) {
            throw $this->createNotFoundException('No se encontró la empresa.');
        }

        // Obtener profesionales, servicios y locations de la empresa
        $professionals = $this->professionalRepository->findBy(['company' => $company, 'active' => true]);
        $services = $this->serviceRepository->findBy(['company' => $company, 'active' => true]);
        
        // Agregar locations de la empresa
        $locations = $this->entityManager->getRepository(Location::class)
            ->createQueryBuilder('l')
            ->where('l.company = :company')
            ->andWhere('l.active = :active')
            ->setParameter('company', $company)
            ->setParameter('active', true)
            ->orderBy('l.name', 'ASC')
            ->getQuery()
            ->getResult();

        return $this->render('agenda/index.html.twig', [
            'professionals' => $professionals,
            'services' => $services,
            'locations' => $locations,
            'company' => $company
        ]);
    }

    #[Route('/appointments', name: 'app_agenda_appointments', methods: ['GET'])]
    // En el método getAppointments, agregar filtro de fecha
    public function getAppointments(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $company = $user->getCompany();
        
        // Verificar si se proporciona una fecha específica
        $specificDate = $request->query->get('date');
        
        if ($specificDate) {
            // Si hay fecha específica, usar solo esa fecha
            $startDate = new \DateTime($specificDate);
            $startDate->setTime(0, 0, 0);
            
            $endDate = new \DateTime($specificDate);
            $endDate->setTime(23, 59, 59);
        } else {
            // Si no hay fecha específica, usar el rango por defecto
            $startDate = new \DateTime($request->query->get('start', 'now'));
            $startDate->setTime(0, 0, 0);
    
            $endDate = new \DateTime($request->query->get('end', '+1 week'));
            $endDate->setTime(23, 59, 59);
        }
        
        // Manejar array de profesionales
        $professionalIds = $request->query->all('professionals');
        $serviceId = $request->query->get('service');
    
        $queryBuilder = $this->entityManager->createQueryBuilder()
            ->select('a', 'p', 's', 'pat')
            ->from(Appointment::class, 'a')
            ->leftJoin('a.professional', 'p')
            ->leftJoin('a.service', 's')
            ->leftJoin('a.patient', 'pat')
            ->where('a.company = :company')
            ->andWhere('a.scheduledAt BETWEEN :start AND :end')
            ->andWhere('a.status NOT IN (:cancelStatus)')
            ->setParameter('cancelStatus', [
                StatusEnum::CANCELLED,
            ])
            ->setParameter('company', $company)
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->orderBy('a.scheduledAt', 'ASC');
        
        // Filtrar por profesionales si se especifican
        if (!empty($professionalIds)) {
            $queryBuilder->andWhere('p.id IN (:professionalIds)')
                        ->setParameter('professionalIds', $professionalIds);
        }
    
        if ($serviceId) {
            $queryBuilder->andWhere('s.id = :serviceId')
                        ->setParameter('serviceId', $serviceId);
        }
    
        // Remover el filtro duplicado de fecha específica ya que ahora se maneja arriba
        
        $appointments = $queryBuilder->getQuery()->getResult();
    
        $events = [];
        foreach ($appointments as $appointment) {
            // En el método getAppointments, enriquecer la información
            $endTime = clone $appointment->getScheduledAt();
            $endTime->modify('+' . $appointment->getDurationMinutes() . ' minutes');

            $events[] = [
                'id' => $appointment->getId(),
                'title' => sprintf('%s (%s)', 
                    $appointment->getPatient()->getName(),
                    $appointment->getService()?->getName() ?? 'Sin servicio'
                ),
                'start' => $appointment->getScheduledAt()->format('Y-m-d\\TH:i:s'),
                'end' => $endTime->format('Y-m-d\\TH:i:s'),
                'backgroundColor' => $this->getStatusColor($appointment->getStatus()),
                'extendedProps' => [
                    'professionalId' => $appointment->getProfessional()->getId(),
                    'professionalName' => $appointment->getProfessional()->getName(),
                    'patientId' => $appointment->getPatient()->getId(), // Agregar esta línea
                    'patientEmail' => $appointment->getPatient()->getEmail(),
                    'patientPhone' => $appointment->getPatient()->getPhone(),
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
    
    #[Route('/business-hours', name: 'app_agenda_business_hours', methods: ['GET'])]
    public function getBusinessHours(Request $request): JsonResponse {
        $user = $this->getUser();
        $company = $user->getCompany();
        
        if (!$company) {
            return new JsonResponse(['error' => 'No se encontró la empresa'], 404);
        }

        // Obtener parámetros de filtro
        $professionalId = $request->query->get('professional');
        $serviceId = $request->query->get('service');
    
        // Construir consulta base para horarios de disponibilidad
        $queryBuilder = $this->entityManager->createQueryBuilder()
            ->select('pa.weekday', 'MIN(pa.startTime) as minStartTime', 'MAX(pa.endTime) as maxEndTime')
            ->from('App\Entity\ProfessionalAvailability', 'pa')
            ->join('pa.professional', 'p')
            ->where('p.company = :company')
            ->andWhere('p.active = true')
            ->setParameter('company', $company);
    
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
            ->where('p.company = :company')
            ->andWhere('p.active = true')
            ->setParameter('company', $company);
    
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
        $company = $user->getCompany();
        
        // Obtener el parámetro force del request
        $force = $data['force'] ?? false;
        
        try {
            $appointment = $appointmentService->createAppointment($data, $company, $force);
            
            // Programar notificaciones
            $this->notificationService->scheduleAppointmentNotifications($appointment);
            
            return new JsonResponse([
                'success' => true,
                'appointment' => $appointmentService->appointmentToArray($appointment)
            ]);
            
        } catch (\InvalidArgumentException $e) {
            // Detectar errores de disponibilidad para el modal
            $isAvailabilityError = str_contains($e->getMessage(), 'disponibilidad') || 
                                 str_contains($e->getMessage(), 'superpone') ||
                                 str_contains($e->getMessage(), 'ocupado');
            
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
                'error_type' => $isAvailabilityError ? 'availability' : 'validation'
            ], 400);
        } catch (\Throwable $e) {
            var_dump($e->getMessage());exit;
            error_log('Error creating appointment: ' . $e->getMessage());
            return new JsonResponse([
                'success' => false,
                'error' => 'Ha ocurrido un error interno. Por favor, inténtelo nuevamente.',
                'error_type' => 'server'
            ], 500);
        }
    }

    #[Route('/appointment/{id}', name: 'app_agenda_get_appointment', methods: ['GET'])]
    public function getAppointment(int $id): JsonResponse
    {
        $user = $this->getUser();
        $company = $user->getCompany();
        
        if (!$company) {
            return new JsonResponse(['error' => 'No se encontró la empresa'], 404);
        }

        $appointment = $this->entityManager->getRepository(Appointment::class)->find($id);
        
        if (!$appointment) {
            return new JsonResponse(['error' => 'Turno no encontrado'], 404);
        }

        // Verificar que la cita pertenece a la empresa del usuario
        if ($appointment->getCompany() !== $company) {
            return new JsonResponse(['error' => 'Acceso denegado'], 403);
        }

        // Calcular hora de finalización
        $endTime = clone $appointment->getScheduledAt();
        $endTime->modify('+' . $appointment->getDurationMinutes() . ' minutes');

        return new JsonResponse([
            'id' => $appointment->getId(),
            'title' => sprintf('%s - %s (%s)', 
                $appointment->getProfessional()->getName(),
                $appointment->getPatient()->getName(),
                $appointment->getService()?->getName() ?? 'Sin servicio'
            ),
            'start' => $appointment->getScheduledAt()->format('Y-m-d\\TH:i:s'),
            'end' => $endTime->format('Y-m-d\\TH:i:s'),
            'professionalId' => $appointment->getProfessional()->getId(),
            'professionalName' => $appointment->getProfessional()->getName(),
            'serviceId' => $appointment->getService()?->getId(),
            'serviceName' => $appointment->getService()?->getName(),
            'patientId' => $appointment->getPatient()->getId(),
            'patientName' => $appointment->getPatient()->getName(),
            'patientEmail' => $appointment->getPatient()->getEmail(),
            'patientPhone' => $appointment->getPatient()->getPhone(),
            // 'patientBirthDate' => $appointment->getPatient()->getBirthDate()?->format('Y-m-d'),
            'durationMinutes' => $appointment->getDurationMinutes(),
            'status' => $appointment->getStatus()->value,
            'notes' => $appointment->getNotes(),
            'scheduledAt' => $appointment->getScheduledAt()->format('Y-m-d\\TH:i:s'),
            'createdAt' => $appointment->getCreatedAt()->format('Y-m-d\\TH:i:s'),
            'updatedAt' => $appointment->getUpdatedAt()?->format('Y-m-d\\TH:i:s')
        ]);
    }

    #[Route('/appointment/{id}', name: 'app_agenda_update_appointment', methods: ['PUT'])]
    public function updateAppointment(Request $request, int $id, AuditService $auditService, AppointmentService $appointmentService): JsonResponse
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
        $company = $user->getCompany();
    
        // Obtener el parámetro force del request
        $force = $data['force'] ?? false;

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
            } elseif (isset($data['date']) && isset($data['appointment_time_from'])) {
                // Manejar el formato del frontend: date + appointment_time_from
                $scheduledAt = new \DateTime($data['date'] . ' ' . $data['appointment_time_from']);
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
            
            // VALIDAR DISPONIBILIDAD SOLO SI NO SE FUERZA
            if (!$force) {
                // Calcular hora de finalización
                $endTime = (clone $scheduledAt)->add(new \DateInterval('PT' . $appointment->getDurationMinutes() . 'M'));
                
                // Usar el AppointmentService para validar disponibilidad
                // Necesitamos excluir la cita actual de la validación de conflictos
                $this->validateAppointmentUpdate(
                    $scheduledAt,
                    $endTime,
                    $appointment->getProfessional(),
                    $company,
                    $appointment->getId()
                );
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
                $patientData['phone'] = $this->phoneUtilityService->cleanPhoneNumber($data['patient_phone']);
            }
            if (isset($data['patient_birth_date'])) {
                $patientData['birth_date'] = $data['patient_birth_date'];
            }
            
            // Actualizar o crear paciente
            if (!empty($patientData)) {
                $patient = $this->patientService->findOrCreatePatient($patientData, $company);
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
            
        } catch (\InvalidArgumentException $e) {
            // Detectar errores de disponibilidad para el modal
            $isAvailabilityError = str_contains($e->getMessage(), 'disponibilidad') || 
                                 str_contains($e->getMessage(), 'superpone') ||
                                 str_contains($e->getMessage(), 'ocupado');
            
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
                'error_type' => $isAvailabilityError ? 'availability' : 'validation'
            ], 400);
        } catch (\Throwable $e) {
            error_log('Error updating appointment: ' . $e->getMessage());
            return new JsonResponse([
                'success' => false,
                'error' => 'Ha ocurrido un error interno. Por favor, inténtelo nuevamente.',
                'error_type' => 'server'
            ], 500);
        }
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
            \App\Entity\StatusEnum::SCHEDULED => '#0dcaf0',
            \App\Entity\StatusEnum::CONFIRMED => '#0d6efd',
            \App\Entity\StatusEnum::CANCELLED => '#dc3545',
            \App\Entity\StatusEnum::COMPLETED => '#198754',
            default => '#ffc107'
        };
    }

    /**
     * Obtiene los servicios de un profesional específico
     */
    #[Route('/professional/{id}/services', name: 'app_agenda_professional_services', methods: ['GET'])]
    public function getProfessionalServices(int $id): JsonResponse
    {
        $user = $this->getUser();
        $company = $user->getCompany();
        
        if (!$company) {
            return new JsonResponse(['error' => 'No se encontró la empresa'], 404);
        }

        $professional = $this->professionalRepository->find($id);
        
        if (!$professional || $professional->getCompany() !== $company) {
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
        $company = $user->getCompany();
        
        if (!$company) {
            return new JsonResponse(['error' => 'No se encontró la empresa'], 404);
        }
    
        $query = $request->query->get('q', '');
        
        if (strlen($query) < 2) {
            return new JsonResponse([]);
        }
    
        $patients = $this->entityManager->getRepository(Patient::class)
            ->createQueryBuilder('p')
            ->where('p.company = :company')
            ->andWhere('(
                p.firstName LIKE :query OR 
                p.lastName LIKE :query OR
                p.idDocument LIKE :query OR
                p.email LIKE :query OR 
                p.phone LIKE :query
            )')
            ->setParameter('company', $company)
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('p.firstName', 'ASC')
            ->addOrderBy('p.lastName', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();
    
        $result = [];
        foreach ($patients as $patient) {
            $result[] = [
                'id' => $patient->getId(),
                'name' => $patient->getFullName(),
                'firstName' => $patient->getFirstName(),
                'lastName' => $patient->getLastName(),
                'idDocument' => $patient->getIdDocument() ?? '',
                'email' => $patient->getEmail() ?? '',
                'phone' => $patient->getPhone() ?? '',
                'birthdate' => $patient->getBirthdate() ? $patient->getBirthdate()->format('Y-m-d') : null
            ];
        }
    
        return new JsonResponse($result);
    }

    #[Route('/professionals', name: 'app_agenda_professionals', methods: ['GET'])]
    public function getProfessionals(): JsonResponse
    {
        $user = $this->getUser();
        $company = $user->getCompany();
        
        if (!$company) {
            return new JsonResponse(['error' => 'Empresa no encontrada'], 404);
        }

        $professionals = $this->professionalRepository->findBy([
            'company' => $company, 
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
        $company = $user->getCompany();
        
        if (!$company) {
            return new JsonResponse(['error' => 'No se encontró la empresa'], 404);
        }

        $appointment = $this->entityManager->getRepository(Appointment::class)->find($id);
        
        if (!$appointment || $appointment->getCompany() !== $company) {
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

        // En el método de actualización de turnos
        if (isset($data['status'])) {
            try {
                $statusEnum = \App\Entity\StatusEnum::from($data['status']);
                $appointment->setStatus($statusEnum);
            } catch (\ValueError $e) {
                return new JsonResponse(['error' => 'Estado inválido'], 400);
            }
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

    /**
     * Obtiene información de profesionales específicos por sus IDs
     */
    #[Route('/professionals/{ids}', name: 'app_agenda_professionals_by_ids', methods: ['GET'], requirements: ['ids' => '[\d,]+'])]
    public function getProfessionalsByIds(string $ids): JsonResponse
    {
        $user = $this->getUser();
        $company = $user->getCompany();
        
        if (!$company) {
            return new JsonResponse(['error' => 'Empresa no encontrada'], 404);
        }

        // Convertir string de IDs separados por comas a array
        $professionalIds = array_filter(array_map('intval', explode(',', $ids)));
        
        if (empty($professionalIds)) {
            return new JsonResponse(['error' => 'IDs de profesionales inválidos'], 400);
        }

        $professionals = $this->professionalRepository->createQueryBuilder('p')
            ->where('p.company = :company')
            ->andWhere('p.active = true')
            ->andWhere('p.id IN (:ids)')
            ->setParameter('company', $company)
            ->setParameter('ids', $professionalIds)
            ->getQuery()
            ->getResult();

        $professionalData = [];
        foreach ($professionals as $professional) {
            $professionalData[] = [
                'id' => $professional->getId(),
                'name' => $professional->getName(),
                'email' => $professional->getEmail(),
                'phone' => $professional->getPhone()
            ];
        }

        return new JsonResponse($professionalData);
    }
    
    #[Route('/validate-slot', name: 'agenda_validate_slot', methods: ['GET'])]
    public function validateSlot(Request $request): JsonResponse
    {
        $professionalId = $request->query->get('professional');
        $serviceId = $request->query->get('service');
        $date = $request->query->get('date');
        $time = $request->query->get('time');
        $appointmentId = $request->query->get('appointmentId');
        
        if (!$professionalId || !$serviceId || !$date || !$time) {
            return new JsonResponse([
                'available' => false,
                'message' => 'Parámetros faltantes'
            ], 400);
        }
        
        try {
            $user = $this->getUser();
            $company = $user->getCompany();
            
            if (!$company) {
                return new JsonResponse([
                    'available' => false,
                    'message' => 'No se encontró la empresa'
                ], 404);
            }
            
            $professional = $this->professionalRepository->find($professionalId);
            $service = $this->serviceRepository->find($serviceId);
            
            if (!$professional || !$service) {
                return new JsonResponse([
                    'available' => false,
                    'message' => 'Profesional o servicio no encontrado'
                ], 404);
            }
            
            // Verificar que el profesional pertenece a la empresa
            if ($professional->getCompany() !== $company) {
                return new JsonResponse([
                    'available' => false,
                    'message' => 'Profesional no válido'
                ], 403);
            }
            
            $dateTime = new \DateTime($date . ' ' . $time);
            
            // Usar el método optimizado para validar el slot específico
            $available = $this->timeSlotService->isSlotAvailableForDateTime(
                $professional,
                $service,
                $dateTime,
                $appointmentId ? (int)$appointmentId : null
            );
            
            return new JsonResponse([
                'available' => $available,
                'message' => $available ? 
                    'Horario disponible' : 
                    'El profesional no trabaja en este horario o ya está ocupado'
            ]);
            
        } catch (\Exception $e) {
            return new JsonResponse([
                'available' => false,
                'message' => 'Error al verificar disponibilidad: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Valida que no haya conflictos con otras citas al actualizar un turno
     */
    private function validateAppointmentUpdate(
        \DateTime $scheduledAt,
        \DateTime $endTime,
        Professional $professional,
        Company $company,
        int $appointmentId
    ): void {
        // Validar disponibilidad del profesional
        $this->validateProfessionalAvailabilityForUpdate($scheduledAt, $endTime, $professional);
        
        // Validar conflictos de horarios excluyendo el turno actual
        $this->validateTimeConflictsForUpdate($scheduledAt, $endTime, $professional, $company, $appointmentId);
    }

    /**
     * Valida que el horario esté dentro de la disponibilidad del profesional
     */
    private function validateProfessionalAvailabilityForUpdate(
        \DateTime $scheduledAt,
        \DateTime $endTime,
        Professional $professional
    ): void {
        $dayOfWeek = (int)$scheduledAt->format('N') - 1; // 0=Lunes, 6=Domingo
        $availabilities = $professional->getAvailabilitiesForWeekday($dayOfWeek);
        
        $isWithinAvailability = false;
        $startTimeSlot = $scheduledAt->format('H:i:s');
        $endTimeSlot = $endTime->format('H:i:s');
        
        foreach ($availabilities as $availability) {
            if ($startTimeSlot >= $availability->getStartTime()->format('H:i:s') && 
                $endTimeSlot <= $availability->getEndTime()->format('H:i:s')) {
                $isWithinAvailability = true;
                break;
            }
        }
        
        if (!$isWithinAvailability) {
            throw new \InvalidArgumentException(
                'El horario seleccionado está fuera de la disponibilidad del profesional.'
            );
        }
    }

    /**
     * Valida que no haya conflictos con otras citas excluyendo el turno actual
     */
    private function validateTimeConflictsForUpdate(
        \DateTime $scheduledAt,
        \DateTime $endTime,
        Professional $professional,
        Company $company,
        int $appointmentId
    ): void {
        $conflictCount = $this->entityManager->createQueryBuilder()
            ->select('COUNT(a.id)')
            ->from(Appointment::class, 'a')
            ->where('a.professional = :professional')
            ->andWhere('a.company = :company')
            ->andWhere('a.id != :appointmentId') // Excluir el turno actual
            ->andWhere('a.status NOT IN (:cancelledStatus)')
            ->andWhere(
                '(a.scheduledAt < :endTime AND ' .
                'DATE_ADD(a.scheduledAt, a.durationMinutes, \'MINUTE\') > :startTime)'
            )
            ->setParameter('professional', $professional)
            ->setParameter('company', $company)
            ->setParameter('appointmentId', $appointmentId)
            ->setParameter('cancelledStatus', [StatusEnum::CANCELLED])
            ->setParameter('startTime', $scheduledAt)
            ->setParameter('endTime', $endTime)
            ->getQuery()
            ->getSingleScalarResult();
        
        if ($conflictCount > 0) {
            throw new \InvalidArgumentException(
                'El horario seleccionado se superpone con una cita existente. Por favor, seleccione otro horario.'
            );
        }
    }
}