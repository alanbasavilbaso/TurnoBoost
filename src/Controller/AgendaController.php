<?php

namespace App\Controller;

use App\Entity\Appointment;
use App\Entity\Patient;
use App\Entity\Professional;
use App\Entity\ProfessionalService;
use App\Entity\ProfessionalBlock;
use App\Entity\Company;
use App\Entity\Service;
use App\Entity\StatusEnum;
use App\Entity\AppointmentSourceEnum;
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
    private AppointmentService $appointmentService;

    public function __construct(
        EntityManagerInterface $entityManager,
        ProfessionalRepository $professionalRepository,
        ServiceRepository $serviceRepository,
        TimeSlot $timeSlotService,
        PatientService $patientService,
        NotificationService $notificationService,
        PhoneUtilityService $phoneUtilityService,
        AppointmentService $appointmentService,
    ) {
        $this->entityManager = $entityManager;
        $this->professionalRepository = $professionalRepository;
        $this->serviceRepository = $serviceRepository;
        $this->timeSlotService = $timeSlotService;
        $this->patientService = $patientService;
        $this->notificationService = $notificationService;
        $this->phoneUtilityService = $phoneUtilityService;
        $this->appointmentService = $appointmentService;
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
            'company' => $company,
            'app_domain' => $_ENV['APP_URL'] . '/' . $company->getDomain()
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

            // Verificar si el paciente está eliminado (soft delete)
            $patient = $appointment->getPatient();
            $isPatientDeleted = $patient->isDeleted();
            
            // Construir el título con indicador de eliminado si es necesario
            $patientFullName = $patient->getName();
            if ($isPatientDeleted) {
                $patientFullName .= ' (eliminado)';
            }

            $events[] = [
                'id' => $appointment->getId(),
                'title' => sprintf('%s (%s)', 
                    $patientFullName,
                    $appointment->getService()?->getName() ?? 'Sin servicio'
                ),
                'start' => $appointment->getScheduledAt()->format('Y-m-d\\TH:i:s'),
                'end' => $endTime->format('Y-m-d\\TH:i:s'),
                'backgroundColor' => $this->getStatusColor($appointment->getStatus()),
                'extendedProps' => [
                    'professionalId' => $appointment->getProfessional()->getId(),
                    'professionalName' => $appointment->getProfessional()->getName(),
                    'patientId' => $appointment->getPatient()->getId(),
                    'patientEmail' => $appointment->getPatient()->getEmail(),
                    'patientPhone' => $appointment->getPatient()->getPhone(),
                    'patientFirstName' => $appointment->getPatient()->getFirstName(),
                    'patientLastName' => $appointment->getPatient()->getLastName() . ($isPatientDeleted ? ' (eliminado)' : ''),
                    'patientDeleted' => $isPatientDeleted,
                    'email' => $appointment->getPatient()->getEmail(),
                    'serviceName' => $appointment->getService()?->getName(),
                    'serviceId' => $appointment->getService()?->getId(),
                    'status' => $appointment->getStatus(),
                    'phone' => $appointment->getPatient()->getPhone(),
                    'notes' => $appointment->getNotes(),
                    'source' => $appointment->getSource()->value
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
        $professionalIds = $request->query->all('professionalIds');
        $serviceId = $request->query->get('service_id');
        $locationId = $request->query->get('locationId');

        // Verificar que se proporcione un locationId
        if (!$locationId) {
            return new JsonResponse(['error' => 'Se requiere un locationId'], 400);
        }

        // Obtener el location y verificar que pertenezca a la empresa
        $location = $this->entityManager->getRepository('App\Entity\Location')
            ->findOneBy(['id' => $locationId, 'company' => $company]);
        
        if (!$location) {
            return new JsonResponse(['error' => 'Location no encontrado o no pertenece a la empresa'], 404);
        }

        // Obtener los horarios del location desde location_availability
        $queryBuilder = $this->entityManager->createQueryBuilder()
            ->select([
                'la.weekDay',
                'MIN(la.startTime) as minStartTime',
                'MAX(la.endTime) as maxEndTime'
            ])
            ->from('App\Entity\LocationAvailability', 'la')
            ->where('la.location = :location')
            ->setParameter('location', $location)
            ->groupBy('la.weekDay')
            ->orderBy('la.weekDay', 'ASC');

        $results = $queryBuilder->getQuery()->getResult();
        
        // Calcular valores globales a partir de los resultados
        $workingDays = [];
        $allStartTimes = [];
        $allEndTimes = [];
        
        foreach ($results as $result) {
            $workingDays[] = $result['weekDay'];
            $allStartTimes[] = $result['minStartTime'];
            $allEndTimes[] = $result['maxEndTime'];
        }
        
        // Calcular los valores globales reales
        $globalMinStart = !empty($allStartTimes) ? min($allStartTimes) : null;
        $globalMaxEnd = !empty($allEndTimes) ? max($allEndTimes) : null;
        
        // Calcular días que NO trabajan
        $allDays = [0, 1, 2, 3, 4, 5, 6]; // 0=Lunes, 1=Martes, ..., 6=Domingo (BD format)
        
        // Convertir días de trabajo a formato FullCalendar (0=Domingo, 1=Lunes, ..., 6=Sábado)
        $daysOfWeek = array_map(function($day) {
            // Convertir de BD format (0=Lunes) a FullCalendar format (0=Domingo)
            // return ($day + 1) % 7; // 0->1, 1->2, ..., 5->6, 6->0
            return ($day); // 0->1, 1->2, ..., 5->6, 6->0
        }, $workingDays);
        

        // Si no hay días disponibles, usar configuración por defecto
        if (empty($daysOfWeek)) {
            $daysOfWeek = [1, 2, 3, 4, 5, 6]; // Lunes a Sábado en formato FullCalendar
        }

        // Formatear horarios - usar el rango COMPLETO (mínimo y máximo)
        $startTime = $globalMinStart ? substr($globalMinStart, 0, 5) : '08:00';
        $endTime = $globalMaxEnd ? substr($globalMaxEnd, 0, 5) : '18:00';

        return new JsonResponse([
            'daysOfWeek' => array_unique($daysOfWeek),
            'startTime' => $startTime,
            'endTime' => $endTime,
            'slotMinTime' => $startTime . ':00',
            'slotMaxTime' => $endTime . ':00',
            'slotDuration' => '00:15:00'
        ]);
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
            // Crear la cita con origen ADMIN
            $appointment = $appointmentService->createAppointment($data, $company, $force, AppointmentSourceEnum::ADMIN);
                        
            return new JsonResponse([
                'success' => true,
                'appointment' => $appointmentService->appointmentToArray($appointment)
            ]);
            
        } catch (\InvalidArgumentException $e) {
            // Detectar errores de disponibilidad para el modal
            $isAvailabilityError = str_contains($e->getMessage(), 'disponibilidad') || 
                                 str_contains($e->getMessage(), 'superpone') ||
                                 str_contains($e->getMessage(), 'ocupado');
            
            // Detectar errores de bloqueo para el modal de confirmación
            $isBlockError = str_contains($e->getMessage(), 'bloqueo de agenda');
            
            $errorType = 'validation';
            if ($e->getCode() == 1) {
                $errorType = 'not_force';
            } else {
                if ($isAvailabilityError) {
                    $errorType = 'availability';
                } elseif ($isBlockError) {
                    $errorType = 'block';
                }
            }
            
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
                'error_type' => $errorType
            ], 400);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Error interno del servidor: ' . $e->getMessage()
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

        // Verificar si el paciente está eliminado (soft delete)
        $patient = $appointment->getPatient();
        $isPatientDeleted = $patient->isDeleted();
        
        // Construir el nombre del paciente con indicador si está eliminado
        $patientFullName = $patient->getName();
        if ($isPatientDeleted) {
            $patientFullName .= ' (eliminado)';
        }

        // Calcular hora de finalización
        $endTime = clone $appointment->getScheduledAt();
        $endTime->modify('+' . $appointment->getDurationMinutes() . ' minutes');

        return new JsonResponse([
            'id' => $appointment->getId(),
            'title' => sprintf('%s - %s (%s)', 
                $appointment->getProfessional()->getName(),
                $patientFullName,
                $appointment->getService()?->getName() ?? 'Sin servicio'
            ),
            'start' => $appointment->getScheduledAt()->format('Y-m-d\\TH:i:s'),
            'end' => $endTime->format('Y-m-d\\TH:i:s'),
            'professionalId' => $appointment->getProfessional()->getId(),
            'professionalName' => $appointment->getProfessional()->getName(),
            'serviceId' => $appointment->getService()?->getId(),
            'serviceName' => $appointment->getService()?->getName(),
            'patientId' => $appointment->getPatient()->getId(),
            'patientFirstName' => $appointment->getPatient()->getFirstName(),
            'patientLastName' => $appointment->getPatient()->getLastName() . ($isPatientDeleted ? ' (eliminado)' : ''),
            'patientEmail' => $appointment->getPatient()->getEmail(),
            'patientPhone' => $appointment->getPatient()->getPhone(),
            'patientDeleted' => $isPatientDeleted,
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
            if (isset($data['patient_first_name'])) {
                $patientData['first_name'] = $data['patient_first_name'];
            }
            if (isset($data['patient_last_name'])) {
                $patientData['last_name'] = $data['patient_last_name'];
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
                    'patientFirstName' => $appointment->getPatient()->getFirstName(),
                    'patientLastName' => $appointment->getPatient()->getLastName(),
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
            ->andWhere('p.deletedAt IS NULL') // Solo pacientes no eliminados
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
            
            $professionalService = $this->entityManager->getRepository(ProfessionalService::class)->findOneBy([
                'professional' => $professionalId,
                'service' => $serviceId
            ]);
            
            if (!$professionalService) {
                return new JsonResponse([
                    'available' => false,
                    'message' => 'Profesional o servicio no encontrado'
                ], 404);
            }
            
            $dateTime = new \DateTime($date . ' ' . $time);
            
            // Usar el método optimizado para validar el slot específico
            // $available = $this->timeSlotService->isSlotAvailableForDateTime(
            //     $professionalService->getProfessional(),
            //     $professionalService->getService(),
            //     $dateTime,
            //     $professionalService->getEffectiveDuration(),
            //     $appointmentId ? (int)$appointmentId : null
            // );

            $locationRepository = $this->entityManager->getRepository('App\\Entity\\Location');
            $location = $locationRepository->findOneBy(['company' => $company, 'active' => true]);

            $available = $this->appointmentService->validateAppointment(
                $dateTime,
                $professionalService->getEffectiveDuration(),
                $professionalService->getProfessional(),
                $professionalService->getService(),
                $location
            );
            $available = is_null($available) ? true : false;
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
                'El horario seleccionado está fuera de la disponibilidad del profesional.',
                1
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

    /**
     * Crear un nuevo bloqueo de horario
     */
    #[Route('/blocks', name: 'app_agenda_create_block', methods: ['POST'])]
    public function createBlock(Request $request, AuditService $auditService): JsonResponse
    {
        $user = $this->getUser();
        $company = $user->getCompany();
        
        if (!$company) {
            return new JsonResponse(['error' => 'No se encontró la empresa'], 404);
        }

        $data = json_decode($request->getContent(), true);
        
        // Validar datos requeridos
        if (!isset($data['professional_id'], $data['block_type'], $data['reason'])) {
            return new JsonResponse(['error' => 'Datos requeridos faltantes'], 400);
        }

        $professional = $this->professionalRepository->find($data['professional_id']);
        if (!$professional || $professional->getCompany() !== $company) {
            return new JsonResponse(['error' => 'Profesional no encontrado'], 404);
        }

        try {
            $block = new ProfessionalBlock();
            $block->setCompany($company);
            $block->setProfessional($professional);
            $block->setBlockType($data['block_type']);
            $block->setReason($data['reason']);

            // Procesar fechas según el tipo de bloqueo
            switch ($data['block_type']) {
                case 'single_day':
                    $block->setStartDate(new \DateTime($data['block_date']));
                    break;
                    
                case 'date_range':
                    $block->setStartDate(new \DateTime($data['start_date']));
                    $block->setEndDate(new \DateTime($data['end_date']));
                    break;
                    
                case 'weekdays_pattern':
                    $block->setStartDate(new \DateTime($data['pattern_start_date']));
                    $block->setEndDate(new \DateTime($data['pattern_end_date']));
                    
                    // Procesar días de la semana (convertir array a string)
                    if (isset($data['weekdays']) && is_array($data['weekdays'])) {
                        $weekdays = array_map('intval', $data['weekdays']);
                        $block->setWeekdaysPattern(implode(',', $weekdays));
                    }
                    break;
                    
                case 'monthly_recurring':
                    $block->setStartDate(new \DateTime($data['monthly_start_date']));
                    if (!empty($data['monthly_end_date'])) {
                        $block->setEndDate(new \DateTime($data['monthly_end_date']));
                    }
                    break;
            }

            // Procesar horarios si no es todo el día
            if (!isset($data['all_day']) || !$data['all_day']) {
                if (isset($data['start_time'])) {
                    $block->setStartTime(new \DateTime($data['start_time']));
                }
                if (isset($data['end_time'])) {
                    $block->setEndTime(new \DateTime($data['end_time']));
                }
            }

            $this->entityManager->persist($block);
            $this->entityManager->flush();

            // Registrar en auditoría
            $auditService->logChange(
                'professional_block',
                $block->getId(),
                'create',
                [],
                [
                    'professional_id' => $professional->getId(),
                    'block_type' => $block->getBlockType(),
                    'reason' => $block->getReason()
                ]
            );

            return new JsonResponse([
                'success' => true,
                'message' => 'Bloqueo creado exitosamente',
                'block' => [
                    'id' => $block->getId(),
                    'professional_name' => $professional->getName(),
                    'reason' => $block->getReason(),
                    'block_type' => $block->getBlockType()
                ]
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Error al crear el bloqueo: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener bloqueos de horario para mostrar en el calendario
     */
    #[Route('/blocks', name: 'app_agenda_get_blocks', methods: ['GET'])]
    public function getBlocks(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $company = $user->getCompany();
        
        if (!$company) {
            return new JsonResponse(['error' => 'No se encontró la empresa'], 404);
        }

        $startDate = new \DateTime($request->query->get('start', 'now'));
        $endDate = new \DateTime($request->query->get('end', '+1 month'));
        $professionalIds = $request->query->all('professionals');

        // Obtener bloques manuales existentes
        $queryBuilder = $this->entityManager->createQueryBuilder()
            ->select('b', 'p')
            ->from(ProfessionalBlock::class, 'b')
            ->leftJoin('b.professional', 'p')
            ->where('b.company = :company')
            ->andWhere('p.active = true')
            ->andWhere('b.active = true')
            ->andWhere('b.startDate <= :endDate')
            ->andWhere('(b.endDate IS NULL OR b.endDate >= :startDate)')
            ->setParameter('company', $company)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('b.startDate', 'ASC');

        // Filtrar por profesionales si se especifican
        if (!empty($professionalIds)) {
            $queryBuilder->andWhere('p.id IN (:professionalIds)')
                        ->setParameter('professionalIds', $professionalIds);
        }

        $blocks = $queryBuilder->getQuery()->getResult();
        $events = [];

        // Generar eventos de bloques manuales
        foreach ($blocks as $block) {
            $events = array_merge($events, $this->generateBlockEvents($block, $startDate, $endDate));
        }
        // var_dump($events);
        // exit;
        // Agregar bloques no laborables si se solicitan y hay múltiples profesionales
        $nonWorkingBlocks = $this->generateNonWorkingBlocks($professionalIds, $startDate, $endDate, $company);
        // var_dump('-----------------------------');
        // var_dump($nonWorkingBlocks);

        // exit;
        $events = array_merge($events, $nonWorkingBlocks);
        // var_dump($events);
        // exit;
        return new JsonResponse($events);
    }

    /**
     * Eliminar un bloqueo de horario
     */
    #[Route('/blocks/{id}', name: 'app_agenda_delete_block', methods: ['DELETE'])]
    public function deleteBlock(int $id, AuditService $auditService): JsonResponse
    {
        $user = $this->getUser();
        $company = $user->getCompany();
        
        if (!$company) {
            return new JsonResponse(['error' => 'No se encontró la empresa'], 404);
        }

        $block = $this->entityManager->getRepository(ProfessionalBlock::class)->find($id);
        
        if (!$block || $block->getCompany() !== $company) {
            return new JsonResponse(['error' => 'Bloqueo no encontrado'], 404);
        }

        try {
            // Registrar en auditoría antes de eliminar
            $auditService->logChange(
                'professional_block',
                $block->getId(),
                'delete',
                [
                    'professional_id' => $block->getProfessional()->getId(),
                    'block_type' => $block->getBlockType(),
                    'reason' => $block->getReason()
                ],
                []
            );

            $this->entityManager->remove($block);
            $this->entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'message' => 'Bloqueo eliminado exitosamente'
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Error al eliminar el bloqueo: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generar eventos de calendario para un bloqueo específico
     */
    private function generateBlockEvents(ProfessionalBlock $block, \DateTime $rangeStart, \DateTime $rangeEnd): array
    {
        $events = [];
        $blockStart = $block->getStartDate();
        $blockEnd = $block->getEndDate() ?? $rangeEnd;
        
        // Asegurar que estamos dentro del rango solicitado
        $effectiveStart = max($blockStart, $rangeStart);
        $effectiveEnd = min($blockEnd, $rangeEnd);
        
        if ($effectiveStart > $effectiveEnd) {
            return [];
        }
        
        switch ($block->getBlockType()) {
            case 'single_day':
                $events[] = $this->createBlockEvent($block, $blockStart);
                break;
                
            case 'date_range':
                $current = clone $effectiveStart;
                while ($current <= $effectiveEnd) {
                    $events[] = $this->createBlockEvent($block, $current);
                    $current->modify('+1 day');
                }
                break;
                
            case 'weekdays_pattern':
                if ($block->getWeekdaysPattern()) {
                    $weekdays = $block->getWeekdaysAsArray();
                    $current = clone $effectiveStart;
                    
                    while ($current <= $effectiveEnd) {
                        $dayOfWeek = (int)$current->format('N') % 7; // 0=Domingo, 1=Lunes, ..., 6=Sábado
                        if (in_array($dayOfWeek, $weekdays)) {
                            $events[] = $this->createBlockEvent($block, $current);
                        }
                        $current->modify('+1 day');
                    }
                }
                break;
                
            case 'monthly_recurring':
                $dayOfMonth = (int)$blockStart->format('d');
                $current = clone $effectiveStart;
                
                // Ajustar al día correcto del mes
                $current->setDate(
                    (int)$current->format('Y'),
                    (int)$current->format('m'),
                    min($dayOfMonth, (int)$current->format('t')) // Último día del mes si el día no existe
                );
                
                while ($current <= $effectiveEnd) {
                    if ($current >= $effectiveStart) {
                        $events[] = $this->createBlockEvent($block, $current);
                    }
                    
                    // Avanzar al siguiente mes
                    $current->modify('first day of next month');
                    $current->setDate(
                        (int)$current->format('Y'),
                        (int)$current->format('m'),
                        min($dayOfMonth, (int)$current->format('t'))
                    );
                }
                break;
        }

        return $events;
    }

    /**
     * Crear un evento de calendario para un bloqueo en una fecha específica
     */
    private function createBlockEvent(ProfessionalBlock $block, \DateTime $date): array
    {
        $startTime = $block->getStartTime();
        $endTime = $block->getEndTime();
        
        if ($startTime && $endTime) {
            // Bloqueo con horario específico
            $start = clone $date;
            $start->setTime(
                (int)$startTime->format('H'),
                (int)$startTime->format('i'),
                (int)$startTime->format('s')
            );
            
            $end = clone $date;
            $end->setTime(
                (int)$endTime->format('H'),
                (int)$endTime->format('i'),
                (int)$endTime->format('s')
            );
            
            return [
                'id' => 'block_' . $block->getId() . '_' . $date->format('Y-m-d'),
                'title' => '🚫 ' . $block->getReason(),
                'start' => $start->format('Y-m-d\TH:i:s'),
                'end' => $end->format('Y-m-d\TH:i:s'),
                'backgroundColor' => '#dc3545',
                'className' => 'professional-block-event',
                'borderColor' => '#1f2937',
                'textColor' => 'white',
                'display' => 'block',
                'extendedProps' => [
                    'type' => 'block',
                    'blockId' => $block->getId(),
                    'professionalId' => $block->getProfessional()->getId(),
                    'professionalName' => $block->getProfessional()->getName(),
                    'reason' => $block->getReason(),
                    'blockType' => $block->getBlockType(),
                    // Agregar información adicional
                    'startDate' => $block->getStartDate()->format('Y-m-d'),
                    'endDate' => $block->getEndDate() ? $block->getEndDate()->format('Y-m-d') : null,
                    'allDay' => !$startTime || !$endTime,
                    'weekdaysPattern' => $block->getWeekdaysPattern(),
                    'monthlyDayOfMonth' => $block->getMonthlyDayOfMonth(),
                    'monthlyEndDate' => $block->getMonthlyEndDate() ? $block->getMonthlyEndDate()->format('Y-m-d') : null
                ]
            ];
        } else {
            // En lugar de 00:00 a 23:59, usar horario de negocio
            $start = clone $date;
            $start->setTime(0, 0); // Hora de inicio de negocio
            
            $end = clone $date;
            $end->setTime(23, 59); // Hora de fin de negocio
            
            return [
                'id' => 'block_' . $block->getId() . '_' . $date->format('Y-m-d'),
                'title' => '🚫 ' . $block->getReason(),
                'start' => $start->format('Y-m-d\TH:i:s'),
                'end' => $end->format('Y-m-d\TH:i:s'),
                'backgroundColor' => '#dc3545',
                'borderColor' => '#dc3545',
                'className' => 'professional-block-event',
                'textColor' => '#ffffff',
                'display' => 'block', // Cambiar de 'background' a 'block'
                'extendedProps' => [
                    'type' => 'block',
                    'blockId' => $block->getId(),
                    'professionalId' => $block->getProfessional()->getId(),
                    'professionalName' => $block->getProfessional()->getName(),
                    'reason' => $block->getReason(),
                    'blockType' => $block->getBlockType(),
                    // Agregar información adicional
                    'startDate' => $block->getStartDate()->format('Y-m-d'),
                    'endDate' => $block->getEndDate() ? $block->getEndDate()->format('Y-m-d') : null,
                    'allDay' => !$startTime || !$endTime,
                    'weekdaysPattern' => $block->getWeekdaysPattern(),
                    'monthlyDayOfMonth' => $block->getMonthlyDayOfMonth(),
                    'monthlyEndDate' => $block->getMonthlyEndDate() ? $block->getMonthlyEndDate()->format('Y-m-d') : null
                ]
            ];
        }
    }
    
    /**
     * Generar bloques no laborables basados en horarios individuales de cada profesional
     */
    private function generateNonWorkingBlocks(array $professionalIds, \DateTime $startDate, \DateTime $endDate, $company): array
    {
        $events = [];
        
        // Si no se especifican profesionales, no generar bloques no laborables
        if (empty($professionalIds)) {
            return $events;
        }
        
        // Obtener el location de la empresa
        $locationRepository = $this->entityManager->getRepository('App\\Entity\\Location');
        $location = $locationRepository->findOneBy(['company' => $company, 'active' => true]);
        
        if (!$location) {
            return $events;
        }
        
        // Obtener horarios globales del location para definir el rango total
        $locationQueryBuilder = $this->entityManager->createQueryBuilder()
            ->select('MIN(la.startTime) as globalMinStart', 'MAX(la.endTime) as globalMaxEnd')
            ->from('App\\Entity\\LocationAvailability', 'la')
            ->where('la.location = :location')
            ->setParameter('location', $location);

        $globalTimes = $locationQueryBuilder->getQuery()->getSingleResult();
        $globalMinTimeStr = $globalTimes['globalMinStart'] ? $globalTimes['globalMinStart'] : '09:00:00';
        $globalMaxTimeStr = $globalTimes['globalMaxEnd'] ? $globalTimes['globalMaxEnd'] : '20:00:00';
        
        // Obtener horarios individuales de cada profesional
        $professionalRepository = $this->entityManager->getRepository('App\\Entity\\Professional');
        $professionalNames = [];
        $professionalSchedules = [];
        
        foreach ($professionalIds as $profId) {
            $professional = $professionalRepository->find($profId);
            if ($professional && $professional->getCompany() === $company && $professional->isActive()) {
                $professionalNames[$profId] = $professional->getName();
                
                // Obtener horarios del profesional desde professional_availability
                $profQueryBuilder = $this->entityManager->createQueryBuilder()
                    ->select('pa.weekday', 'pa.startTime', 'pa.endTime')
                    ->from('App\\Entity\\ProfessionalAvailability', 'pa')
                    ->where('pa.professional = :professional')
                    ->setParameter('professional', $professional)
                    ->orderBy('pa.weekday', 'ASC')
                    ->addOrderBy('pa.startTime', 'ASC');

                $availabilities = $profQueryBuilder->getQuery()->getResult();
                
                // Agrupar horarios por día de la semana
                $schedule = [];
                foreach ($availabilities as $availability) {
                    $weekDay = $availability['weekday']; // Corregido: weekDay en lugar de weekday
                    if (!isset($schedule[$weekDay])) {
                        $schedule[$weekDay] = [];
                    }
                    $schedule[$weekDay][] = [
                        'start' => $availability['startTime'],
                        'end' => $availability['endTime']
                    ];
                }
                
                $professionalSchedules[$profId] = $schedule;
            }
        }
        
        // Generar bloques no laborables para cada día en el rango
        $current = clone $startDate;
        while ($current <= $endDate) {
            // Convertir formato PHP (0=Domingo) a formato BD (0=Lunes)
            $phpDayOfWeek = (int)$current->format('w'); // 0=Domingo, 6=Sábado
            // var_dump($current);
            // var_dump($phpDayOfWeek);
            // exit;
            // Generar bloques para cada profesional seleccionado
            foreach ($professionalIds as $profId) {
                $profName = $professionalNames[$profId] ?? 'Profesional ' . $profId;
                $profSchedule = $professionalSchedules[$profId] ?? [];
                
                // Obtener horarios específicos para esta fecha exacta
                $daySchedules = $this->professionalRepository->getProfessionalSchedulesForDay(
                    $this->professionalRepository->find($profId),
                    $phpDayOfWeek,
                    $current // Pasar la fecha específica para incluir horarios especiales
                );
                
                if (empty($daySchedules)) {
                    // Profesional no trabaja este día - crear bloque completo
                    $events[] = $this->createNonWorkingBlockEvent(
                        $profId,
                        $profName,
                        $current,
                        $globalMinTimeStr,
                        $globalMaxTimeStr,
                        'Día no laborable'
                    );
                } else {
                    // Convertir cada horario al formato esperado
                    $formattedSchedules = [];
                    foreach ($daySchedules as $schedule) {
                        $formattedSchedules[] = [
                            'start' => \DateTime::createFromFormat('H:i:s', $schedule['start_time']),
                            'end' => \DateTime::createFromFormat('H:i:s', $schedule['end_time']),
                            'tipo' => $schedule['tipo']
                        ];
                    }
                    
                    // Crear bloques para horas no laborables según horarios del profesional
                    $events = array_merge($events, $this->createNonWorkingHoursForDay(
                        $profId,
                        $profName,
                        $current,
                        $formattedSchedules, // Array de horarios formateados
                        $globalMinTimeStr,
                        $globalMaxTimeStr
                    ));
                }
            }
            
            $current->modify('+1 day');
        }
        
        return $events;
    }

    /**
     * Crear bloques no laborables para horas específicas de un día
     */
    private function createNonWorkingHoursForDay(int $profId, string $profName, \DateTime $date, array $daySchedule, string $globalMin, string $globalMax): array
    {
        $events = [];
        
        // Ordenar horarios por hora de inicio
        usort($daySchedule, function($a, $b) {
            return $a['start'] <=> $b['start'];
        });
        
        // Convertir strings a DateTime para comparación
        $globalMinTime = \DateTime::createFromFormat('H:i:s', $globalMin);
        $globalMaxTime = \DateTime::createFromFormat('H:i:s', $globalMax);
        
        // Bloque antes del primer horario laboral
        $firstStart = $daySchedule[0]['start']; // Es un objeto DateTime
        $firstStartTime = \DateTime::createFromFormat('H:i:s', $firstStart->format('H:i:s'));
        
        if ($firstStartTime > $globalMinTime) {
            $events[] = $this->createNonWorkingBlockEvent(
                $profId,
                $profName,
                $date,
                $globalMin,
                $firstStart->format('H:i:s'),
                'Fuera de horario'
            );
        }
        
        // Bloques entre horarios laborales (si hay gaps)
        for ($i = 0; $i < count($daySchedule) - 1; $i++) {
            $currentEnd = $daySchedule[$i]['end'];
            $nextStart = $daySchedule[$i + 1]['start'];
            
            $currentEndTime = \DateTime::createFromFormat('H:i:s', $currentEnd->format('H:i:s'));
            $nextStartTime = \DateTime::createFromFormat('H:i:s', $nextStart->format('H:i:s'));
            
            if ($currentEndTime < $nextStartTime) {
                $events[] = $this->createNonWorkingBlockEvent(
                    $profId,
                    $profName,
                    $date,
                    $currentEnd->format('H:i:s'),
                    $nextStart->format('H:i:s'),
                    'Descanso'
                );
            }
        }
        
        // Bloque después del último horario laboral
        $lastEnd = $daySchedule[count($daySchedule) - 1]['end'];
        $lastEndTime = \DateTime::createFromFormat('H:i:s', $lastEnd->format('H:i:s'));
        
        if ($lastEndTime < $globalMaxTime) {
            $events[] = $this->createNonWorkingBlockEvent(
                $profId,
                $profName,
                $date,
                $lastEnd->format('H:i:s'),
                $globalMax,
                'Fuera de horario'
            );
        }
        
        return $events;
    }

    /**
     * Crear un evento de bloque no laborable
     */
    private function createNonWorkingBlockEvent(int $profId, string $profName, \DateTime $date, string $startTime, string $endTime, string $reason): array
    {
        $start = clone $date;
        $startTimeParts = explode(':', $startTime);
        $start->setTime((int)$startTimeParts[0], (int)$startTimeParts[1], (int)($startTimeParts[2] ?? 0));
        
        $end = clone $date;
        $endTimeParts = explode(':', $endTime);
        $end->setTime((int)$endTimeParts[0], (int)$endTimeParts[1], (int)($endTimeParts[2] ?? 0));
        
        return [
            'id' => 'non-working-' . $profId . '-' . $date->format('Y-m-d') . '-' . str_replace(':', '', $startTime),
            'title' => $profName . ' - ' . $reason,
            'start' => $start->format('Y-m-d\TH:i:s'),
            'end' => $end->format('Y-m-d\TH:i:s'),
            'backgroundColor' => '#f0f0f0', // Gris claro
            'borderColor' => '#d0d0d0',
            'textColor' => '#666666',
            'display' => 'background',
            'className' => 'block-element',
            'extendedProps' => [
                'type' => 'non-working',
                'professionalId' => $profId,
                'professionalName' => $profName,
                'reason' => $reason
            ]
        ];
    }


    /**
     * @Route("/profesionales/{professionalId}/special-schedules", name="create_special_schedule", methods={"POST"})
     */
    public function createSpecialSchedule(int $professionalId, Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            $professional = $this->entityManager->getRepository(Professional::class)->find($professionalId);
            if (!$professional) {
                return new JsonResponse(['success' => false, 'message' => 'Profesional no encontrado'], 404);
            }
            
            // Verificar que el profesional pertenezca a la empresa del usuario
            if ($professional->getCompany() !== $this->getUser()->getCompany()) {
                return new JsonResponse(['success' => false, 'message' => 'Acceso denegado'], 403);
            }
            
            // Validar datos
            if (!isset($data['fecha'], $data['horaDesde'], $data['horaHasta'])) {
                return new JsonResponse(['success' => false, 'message' => 'Datos incompletos'], 400);
            }
            
            // Crear jornada especial
            $specialSchedule = new SpecialSchedule();
            $specialSchedule->setProfessional($professional);
            $specialSchedule->setDate(new \DateTime($data['fecha']));
            $specialSchedule->setStartTime(new \DateTime($data['horaDesde']));
            $specialSchedule->setEndTime(new \DateTime($data['horaHasta']));
            
            // NUEVO: Agregar servicios
            if (isset($data['services']) && is_array($data['services'])) {
                foreach ($data['services'] as $serviceId) {
                    $service = $this->entityManager->getRepository(Service::class)->find($serviceId);
                    if ($service && $service->getCompany() === $this->getUser()->getCompany()) {
                        $specialSchedule->addService($service);
                    }
                }
            } else {
                // Si no se especifican servicios, agregar todos los del profesional
                foreach ($professional->getProfessionalServices() as $professionalService) {
                    $specialSchedule->addService($professionalService->getService());
                }
            }
            
            $this->entityManager->persist($specialSchedule);
            $this->entityManager->flush();
            
            return new JsonResponse([
                'success' => true, 
                'message' => 'Jornada especial creada exitosamente',
                'id' => $specialSchedule->getId()
            ]);
            
        } catch (\Exception $e) {
            return new JsonResponse(['success' => false, 'message' => 'Error interno del servidor'], 500);
        }
    }

}