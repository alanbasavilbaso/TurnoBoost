<?php

namespace App\Service;

use App\Entity\Appointment;
use App\Entity\AppointmentSourceEnum;
use App\Entity\AuditLog;
use App\Entity\Company;
use App\Entity\Professional;
use App\Entity\Service;
use App\Entity\ProfessionalService;
use App\Entity\StatusEnum;
use App\Repository\ProfessionalRepository;
use App\Repository\ServiceRepository;
use App\Repository\AppointmentRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Location;
use App\Entity\ProfessionalBlock;
use App\Service\AuditService;

class AppointmentService
{
    private EntityManagerInterface $entityManager;
    private ProfessionalRepository $professionalRepository;
    private ServiceRepository $serviceRepository;
    private PatientService $patientService;
    private AppointmentRepository $appointmentRepository;
    private NotificationService $notificationService;
    private AuditService $auditService;

    public function __construct(
        EntityManagerInterface $entityManager,
        ProfessionalRepository $professionalRepository,
        ServiceRepository $serviceRepository,
        PatientService $patientService,
        AppointmentRepository $appointmentRepository,
        NotificationService $notificationService,
        AuditService $auditService
    ) {
        $this->entityManager = $entityManager;
        $this->professionalRepository = $professionalRepository;
        $this->serviceRepository = $serviceRepository;
        $this->patientService = $patientService;
        $this->appointmentRepository = $appointmentRepository;
        $this->notificationService = $notificationService;
        $this->auditService = $auditService;
    }

    /**
     * Crea una nueva cita con todas las validaciones necesarias
     */
    public function createAppointment(
        array $data, 
        Company $company, 
        bool $force = false, 
        AppointmentSourceEnum $source = AppointmentSourceEnum::USER,
        bool $sendNotifications = true
    ): Appointment
    {
        // Validar y obtener datos básicos
        $appointmentData = $this->validateAndParseData($data);
        
        // Obtener entidades relacionadas
        $professional = $this->getProfessional($appointmentData['professionalId'], $company);
        $service = $this->getService($appointmentData['serviceId'], $company);
        $location = $this->getLocation($appointmentData['locationId'], $company);
        
        // Calcular duración y hora de finalización
        $duration = $this->calculateDuration($professional, $service);
        
        // Validaciones específicas para usuarios (no para admin)
        if ($source === AppointmentSourceEnum::USER && !$force) {
            $this->validateUserRestrictions($appointmentData, $company);
        }
        
        
        // Ejecutar validaciones generales (solo si no se fuerza)
        if (!$force) {
            $this->validateAppointment(
                $appointmentData['scheduledAt'],
                $duration,
                $professional,
                $service,
                $location
            );
        }

        // Crear o buscar paciente
        $patient = $this->patientService->findOrCreatePatient($appointmentData['patientData'], $company, $source);
                
        // Crear la cita
        $appointment = new Appointment();
        $appointment->setCompany($company)
               ->setLocation($location)
               ->setProfessional($professional)
               ->setService($service)
               ->setPatient($patient)
               ->setScheduledAt($appointmentData['scheduledAt'])
               ->setDurationMinutes($duration)
               ->setNotes($appointmentData['notes'])
               ->setSource($source);
        
        $this->entityManager->persist($appointment);
        $this->entityManager->flush();
        
        // Registrar en audit_log
        $this->auditService->logChange(
            'Appointment',
            $appointment->getId(),
            'create',
            null,
            [
                'patient_id' => $patient->getId(),
                'professional_id' => $professional->getId(),
                'service_id' => $service->getId(),
                'location_id' => $location->getId(),
                'scheduled_at' => $appointment->getScheduledAt()->format('Y-m-d H:i:s'),
                'duration_minutes' => $duration,
                'source' => $source->value,
                'notes' => $appointmentData['notes']
            ]
        );
        
        // Programar notificaciones automáticamente (solo si se especifica)
        if ($sendNotifications) {
            $this->notificationService->scheduleAppointmentNotifications($appointment);
        }
        
        return $appointment;
    }

    /**
     * Validaciones específicas para citas creadas por usuarios
     */
    private function validateUserRestrictions(array $appointmentData, Company $company): void
    {
        // Validar tiempo máximo futuro
        $now = new \DateTime();
        $maxFutureTime = $company->getMaximumFutureTime(); // en días
        $maxDate = (clone $now)->add(new \DateInterval('P' . $maxFutureTime . 'D'));
        
        if ($appointmentData['scheduledAt'] > $maxDate) {
            throw new \InvalidArgumentException(
                "No se pueden agendar citas con más de {$maxFutureTime} días de anticipación."
            );
        }

        // Validar límite de reservas pendientes
        $pendingCount = $this->entityManager->createQueryBuilder()
            ->select('COUNT(a.id)')
            ->from(Appointment::class, 'a')
            ->join('a.patient', 'p')
            ->where('a.company = :company')
            ->andWhere('p.email = :email')
            ->andWhere('a.source = :source')
            ->andWhere('a.status IN (:pendingStatuses)')
            ->andWhere('a.scheduledAt > :now')
            ->setParameter('company', $company)
            ->setParameter('email', $appointmentData['patientData']['email'])
            ->setParameter('source', AppointmentSourceEnum::USER)
            ->setParameter('pendingStatuses', [StatusEnum::SCHEDULED])
            ->setParameter('now', $now)
            ->getQuery()
            ->getSingleScalarResult();
        
        if ($pendingCount >= $company->getMaxPendingBookings()) {
            throw new \InvalidArgumentException(
                "Has alcanzado el límite máximo de {$company->getMaxPendingBookings()} reservas pendientes."
            );
        }
    }

    /**
     * Verifica si una cita puede ser cancelada considerando su origen
     */
    public function canBeCancelled(Appointment $appointment): bool
    {
        $baseCondition = in_array($appointment->getStatus(), [StatusEnum::SCHEDULED, StatusEnum::CONFIRMED]) 
                        && $appointment->isFuture();
        
        // Si fue creada por admin, siempre puede ser cancelada
        if ($appointment->isAdminCreated()) {
            return $baseCondition;
        }
        
        // Si fue creada por usuario, verificar configuración de la empresa
        return $baseCondition && $appointment->getCompany()->isCancellableBookings();
    }

    /**
     * Verifica si una cita puede ser editada considerando su origen
     */
    public function canBeEdited(Appointment $appointment): bool
    {
        $baseCondition = in_array($appointment->getStatus(), [StatusEnum::SCHEDULED, StatusEnum::CONFIRMED]) 
                        && $appointment->isFuture();
        
        // Si fue creada por admin, siempre puede ser editada
        if ($appointment->isAdminCreated()) {
            return $baseCondition;
        }
        
        // Si fue creada por usuario, verificar configuración de la empresa
        if (!$appointment->getCompany()->isEditableBookings()) {
            return false;
        }
        
        // Verificar tiempo mínimo para edición
        $now = new \DateTime();
        $minEditTime = $appointment->getCompany()->getMinimumEditTime(); // en minutos
        $timeDiff = $appointment->getScheduledAt()->getTimestamp() - $now->getTimestamp();
        
        return $baseCondition && ($timeDiff >= $minEditTime * 60);
    }

    /**
     * Valida y parsea los datos de entrada
     */
    private function validateAndParseData(array $data): array
    {
        // Mapear nombres de campos del formulario a los esperados
        $professionalId = $data['professional_id'] ?? $data['professional'] ?? null;
        $serviceId = $data['service_id'] ?? $data['service'] ?? null;
        $locationId = $data['location_id'] ?? null; // Agregar esta línea
        
        // Construir la fecha y hora programada
        if (isset($data['scheduled_at'])) {
            $scheduledAt = new \DateTime($data['scheduled_at']);
        } elseif (isset($data['date']) && isset($data['time'])) {
            if (str_contains($data['time'], 'T')) {
                $scheduledAt = new \DateTime($data['time']);
            } else {
                $scheduledAt = new \DateTime($data['date'] . ' ' . $data['time']);
            }
        } elseif (isset($data['date']) && isset($data['appointment_time_from'])) {
            // Manejar el formato del frontend: date + appointment_time_from
            $scheduledAt = new \DateTime($data['date'] . ' ' . $data['appointment_time_from']);
        } else {
            throw new \InvalidArgumentException('Fecha y hora son requeridas');
        }
        
        if (!$professionalId || !$serviceId || !$locationId) {
            throw new \InvalidArgumentException('Profesional, servicio y ubicación son requeridos');
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
            $patientData['phone'] = $data['patient_phone'];
        }
        if (isset($data['patient_birth_date'])) {
            $patientData['birth_date'] = $data['patient_birth_date'];
        }
        
        return [
            'professionalId' => $professionalId,
            'serviceId' => $serviceId,
            'locationId' => $locationId,
            'scheduledAt' => $scheduledAt,
            'patientData' => $patientData,
            'notes' => $data['notes'] ?? null
        ];
    }

    /**
     * Obtiene y valida el profesional
     */
    private function getProfessional(int $professionalId, Company $company): Professional
    {
        $professional = $this->professionalRepository->find($professionalId);
        
        if (!$professional || $professional->getCompany() !== $company) {
            throw new \InvalidArgumentException('Profesional no encontrado');
        }
        
        return $professional;
    }

    private function getService(int $serviceId, Company $company): Service
    {
        $service = $this->serviceRepository->find($serviceId);
        
        if (!$service || $service->getCompany() !== $company) {
            throw new \InvalidArgumentException('Servicio no encontrado');
        }
        
        return $service;
    }

    /**
     * Calcula la duración efectiva del servicio
     */
    private function calculateDuration(Professional $professional, Service $service): int
    {
        $professionalService = $this->entityManager->getRepository(ProfessionalService::class)
            ->findOneBy(['professional' => $professional, 'service' => $service]);
        
        return $professionalService ? $professionalService->getEffectiveDuration() : $service->getDurationMinutes();
    }

    /**
     * Ejecuta todas las validaciones de la cita
     */
    public function validateAppointment(
        \DateTime $scheduledAt,
        int $durationMinutes, 
        Professional $professional,
        Service $service,
        Location $location,
        ?int $appointmentId = null
    ): void {
        $available = $this->appointmentRepository->validateSlotAvailability(
            $scheduledAt,
            $durationMinutes,
            $professional->getId(),
            $service->getId(),
            $appointmentId
        );
        
        if (!$available['available']) {
            if (isset($available['details']['appointments']) && $available['details']['appointments'] > 0) {
                throw new \InvalidArgumentException(
                    'El horario seleccionado se superpone con una cita existente'
                );
            } else {
                // NUEVA VALIDACIÓN: Verificar si está dentro del horario de la location
                $weekDay = (int)$scheduledAt->format('w');
                $endTime = (clone $scheduledAt)->add(new \DateInterval('PT' . $durationMinutes . 'M'));
                
                // Verificar si el turno está dentro del horario de trabajo de la location
                if ($this->isAppointmentWithinLocationHours($scheduledAt, $endTime, $location, $weekDay)) {
                    // El turno está dentro del horario de la location, pero fuera del profesional
                    // Devolver código 2 para permitir que el profesional lo guarde de todas formas
                    throw new \InvalidArgumentException(
                        'El horario seleccionado está fuera de la disponibilidad del profesional.',
                        2
                    );
                } else {
                    // El turno está completamente fuera del horario de la location
                    throw new \InvalidArgumentException(
                        'El horario seleccionado está fuera de la disponibilidad del profesional.',
                        1
                    );
                }
            }
        }
        
        $endTime = (clone $scheduledAt)->add(new \DateInterval('PT' . $durationMinutes . 'M'));
        
        // Verificar bloqueos
        $this->validateBlockConflicts($scheduledAt, $endTime, $professional);
    }

    /**
     * Valida que no haya bloqueos que interfieran con la cita
     */
    /**
     * Verifica si una cita está dentro del horario de trabajo de la location
     */
    private function isAppointmentWithinLocationHours(
        \DateTime $startTime, 
        \DateTime $endTime, 
        Location $location, 
        int $weekDay
    ): bool {
        $dayAvailabilities = $location->getAvailabilitiesForWeekDay($weekDay);
        
        if ($dayAvailabilities->isEmpty()) {
            return false; // No hay horarios definidos para este día
        }
        
        foreach ($dayAvailabilities as $availability) {
            // Verificar si el inicio del turno está dentro del horario del local
            if ($availability->isTimeInRange($startTime)) {
            // Verificar si tanto el inicio como el fin de la cita están dentro de este horario
            // if ($availability->isTimeInRange($startTime) && $availability->isTimeInRange($endTime)) {
                return true;
            }
        }
        
        return false;
    }

    private function validateBlockConflicts(
        \DateTime $scheduledAt,
        \DateTime $endTime,
        Professional $professional
    ): void {
        $appointmentDate = $scheduledAt->format('Y-m-d');
        $appointmentStartTime = $scheduledAt->format('H:i:s');
        $appointmentEndTime = $endTime->format('H:i:s');
        $dayOfWeek = (int)$scheduledAt->format('N'); // 0=Lunes, 6=Domingo

        // var_dump($appointmentDate);
        // var_dump($dayOfWeek);
        // exit;
        $sql = "
            SELECT 
                pb.*
            FROM 
                professional_blocks pb

            WHERE pb.professional_id = :professionalId
            AND pb.active = true
            AND (
                (pb.block_type = 'single_day' AND pb.start_date = :appointmentDate)
            OR
                (pb.block_type = 'date_range' AND pb.start_date <= :appointmentDate AND pb.end_date >= :appointmentDate)
            OR
                (
                    pb.block_type = 'weekdays_pattern' 
                    AND 
                    pb.start_date <= :appointmentDate AND (pb.end_date IS NULL OR pb.end_date >= :appointmentDate)
                    AND 
                    pb.weekdays_pattern LIKE '%' || :dayOfWeek || '%'
                )
            OR 
                (
                    pb.block_type = 'monthly_recurring' 
	            	AND 
      	          	pb.start_date <= :appointmentDate AND (pb.end_date IS NULL OR pb.end_date >= :appointmentDate)
                )
        );";
        
        $params = [
            'professionalId' => $professional->getId(),
            'appointmentDate' => $appointmentDate,
            'dayOfWeek' => $dayOfWeek
        ];
        
        $stmt = $this->entityManager->getConnection()->prepare($sql);
        $blocks = $stmt->executeQuery($params)->fetchAllAssociative();
        

        // $finalSql = $sql;
        // foreach ($params as $key => $value) {
        //     $finalSql = str_replace(":$key", "'$value'", $finalSql);
        // }
        // var_dump($finalSql);
        // exit;

        foreach ($blocks as $block) {
            $blockAffectsAppointment = false;
            // Verificar si el bloque afecta la cita según su tipo
            switch ($block['block_type']) {
                case 'single_day':
                    // Verificar que la fecha del bloque coincida con la fecha de la cita
                    $blockDate = date('Y-m-d', strtotime($block['start_date']));
                    if ($blockDate === $appointmentDate) {
                        // Si start_time es null, bloquea todo el día
                        if ($block['start_time'] === null) {
                            $blockAffectsAppointment = true;
                        } else {
                            $blockStartTime = \DateTime::createFromFormat('H:i:s', $block['start_time']);
                            
                            // Si end_time es null, bloquea desde start_time hasta el final del día
                            if ($block['end_time'] === null) {
                                $blockEndTime = \DateTime::createFromFormat('H:i:s', '23:59:59');
                            } else {
                                $blockEndTime = \DateTime::createFromFormat('H:i:s', $block['end_time']);
                            }
                            
                            $blockAffectsAppointment = $this->checkTimeOverlap(
                                $appointmentStartTime, $appointmentEndTime,
                                $blockStartTime, $blockEndTime
                            );
                        }
                    }
                    break;
                    
                case 'date_range':
                    // Si start_time es null, bloquea todo el día
                    if ($block['start_time'] === null) {
                        $blockAffectsAppointment = true;
                    } else {
                        $blockStartTime = \DateTime::createFromFormat('H:i:s', $block['start_time']);
                        
                        // Si end_time es null, bloquea desde start_time hasta el final del día
                        if ($block['end_time'] === null) {
                            $blockEndTime = \DateTime::createFromFormat('H:i:s', '23:59:59');
                        } else {
                            $blockEndTime = \DateTime::createFromFormat('H:i:s', $block['end_time']);
                        }
                        
                        $blockAffectsAppointment = $this->checkTimeOverlap(
                            $appointmentStartTime, $appointmentEndTime,
                            $blockStartTime, $blockEndTime
                        );
                    }
                    break;
                
                case 'weekdays_pattern':
                    // Verificar si el día de la semana está en el patrón
                    $weekdays = explode(',', $block['weekdays_pattern']);
                    if (in_array((string)$dayOfWeek, $weekdays)) {
                        // Si start_time es null, bloquea todo el día
                        if ($block['start_time'] === null) {
                            $blockAffectsAppointment = true;
                        } else {
                            $blockStartTime = \DateTime::createFromFormat('H:i:s', $block['start_time']);
                            
                            // Si end_time es null, bloquea desde start_time hasta el final del día
                            if ($block['end_time'] === null) {
                                $blockEndTime = \DateTime::createFromFormat('H:i:s', '23:59:59');
                            } else {
                                $blockEndTime = \DateTime::createFromFormat('H:i:s', $block['end_time']);
                            }
                            
                            $blockAffectsAppointment = $this->checkTimeOverlap(
                                $appointmentStartTime, $appointmentEndTime,
                                $blockStartTime, $blockEndTime
                            );
                        }
                    }
                    break;

                case 'monthly_recurring':
                    // Verificar que el día del mes coincida
                    $appointmentDay = (int)$scheduledAt->format('d');
                    $blockDay = (int)date('d', strtotime($block['start_date']));
                    
                    if ($appointmentDay === $blockDay) {
                        // Si start_time es null, bloquea todo el día
                        if ($block['start_time'] === null) {
                            $blockAffectsAppointment = true;
                        } else {
                            $blockStartTime = \DateTime::createFromFormat('H:i:s', $block['start_time']);
                            
                            // Si end_time es null, bloquea desde start_time hasta el final del día
                            if ($block['end_time'] === null) {
                                $blockEndTime = \DateTime::createFromFormat('H:i:s', '23:59:59');
                            } else {
                                $blockEndTime = \DateTime::createFromFormat('H:i:s', $block['end_time']);
                            }
                            
                            $blockAffectsAppointment = $this->checkTimeOverlap(
                                $appointmentStartTime, $appointmentEndTime,
                                $blockStartTime, $blockEndTime
                            );
                        }
                    }
                    break;
            }
            
            if ($blockAffectsAppointment) {
                throw new \InvalidArgumentException(
                    'Hay un bloqueo de agenda para el horario elegido, con el motivo: ' . $block['reason']
                );
            }
        }
    }


    /**
     * Verifica si dos rangos de tiempo se superponen
     */
    private function checkTimeOverlap(
        string $start1, string $end1,
        ?\DateTimeInterface $start2, ?\DateTimeInterface $end2
    ): bool {
        // Si el bloque no tiene horarios específicos (todo el día), siempre hay conflicto
        if ($start2 === null || $end2 === null) {
            return true;
        }
        
        $blockStartTime = $start2->format('H:i:s');
        $blockEndTime = $end2->format('H:i:s');
        
        // Verificar superposición: (start1 < blockEnd) && (end1 > blockStart)
        return ($start1 < $blockEndTime) && ($end1 > $blockStartTime);
    }

    /**
     * Convierte una cita a array para respuestas JSON
     */
    public function appointmentToArray(Appointment $appointment): array
    {
        return [
            'id' => $appointment->getId(),
            'title' => $appointment->getPatient()->getName() . ' - ' . $appointment->getService()->getName(),
            'start' => $appointment->getScheduledAt()->format('c'),
            'end' => $appointment->getEndTime()->format('c'),
            'patientId' => $appointment->getPatient()->getId(),
            'patientFirstName' => $appointment->getPatient()->getFirstName(),
            'patientLastName' => $appointment->getPatient()->getLastName(),
            'patientEmail' => $appointment->getPatient()->getEmail(),
            'patientPhone' => $appointment->getPatient()->getPhone(),
            'professionalId' => $appointment->getProfessional()->getId(),
            'professionalName' => $appointment->getProfessional()->getName(),
            'serviceId' => $appointment->getService()->getId(),
            'serviceName' => $appointment->getService()->getName(),
            'status' => $appointment->getStatus()->value,
            'locationName' => $appointment->getLocation()->getName() . ' - ' . $appointment->getLocation()->getAddress(),
            'duration' => $appointment->getDurationMinutes(),
            'price' => $appointment->getPrice(),
            'notes' => $appointment->getNotes()
        ];
    }

    /**
     * Generar token seguro para un appointment
     */
    public function generateSecureToken(int $appointmentId, string $action = 'confirm'): string
    {
        $secret = $_ENV['APP_SECRET'] ?? 'default-secret';
        $expiration = time() + (24 * 60 * 60); // 24 horas
        
        $data = [
            'appointment_id' => $appointmentId,
            'action' => $action,
            'expires' => $expiration
        ];
        
        $payload = base64_encode(json_encode($data));
        $signature = hash_hmac('sha256', $payload, $secret);
        
        return $payload . '.' . $signature;
    }

    /**
     * Validar token y confirmar appointment
     */
    public function confirmAppointmentByToken(int $appointmentId, string $token, Company $company): Appointment
    {
        $this->validateToken($appointmentId, $token);
        
        // Buscar el turno activo de la cadena
        $activeAppointment = $this->findActiveAppointmentFromChain($appointmentId);
        if (!$activeAppointment) {
            throw new \InvalidArgumentException('No se encontró un turno activo para confirmar');
        }
        
        if ($activeAppointment->getProfessional()->getCompany() !== $company) {
            throw new \InvalidArgumentException('Turno no pertenece a esta empresa');
        }
        
        if ($activeAppointment->getStatus() === StatusEnum::CANCELLED) {
            throw new \InvalidArgumentException('Este turno ya fue cancelado');
        }
        
        if ($activeAppointment->getStatus() === StatusEnum::CONFIRMED) {
            throw new \InvalidArgumentException('Este turno ya fue confirmado');
        }
        
        // Confirmar el turno
        $oldStatus = $activeAppointment->getStatus()->value;
        $activeAppointment->setStatus(StatusEnum::CONFIRMED);
        $activeAppointment->setConfirmedAt(new \DateTime());
        $this->entityManager->flush();

        // Registrar en auditoría
        $this->auditService->logChange(
            'Appointment',
            $activeAppointment->getId(),
            'appointment_confirmed_by_link',
            ['status' => $oldStatus],
            ['status' => 'confirmed', 'confirmed_at' => $activeAppointment->getConfirmedAt()->format('Y-m-d H:i:s')]
        );
        
        return $activeAppointment;
    }

    /**
     * Validar token y cancelar appointment
     */
    public function cancelAppointmentByToken(int $appointmentId, string $token, Company $company): Appointment
    {
        $this->validateToken($appointmentId, $token);
        
        // Buscar el turno activo de la cadena
        $activeAppointment = $this->findActiveAppointmentFromChain($appointmentId);
        if (!$activeAppointment) {
            throw new \InvalidArgumentException('No se encontró un turno activo para cancelar');
        }
        
        if ($activeAppointment->getProfessional()->getCompany() !== $company) {
            throw new \InvalidArgumentException('Turno no pertenece a esta empresa');
        }
        
        if ($activeAppointment->getStatus() === StatusEnum::CANCELLED) {
            throw new \InvalidArgumentException('Este turno ya fue cancelado');
        }
        
        // Cancelar el turno
        $oldStatus = $activeAppointment->getStatus()->value;
        $activeAppointment->setStatus(StatusEnum::CANCELLED);
        $activeAppointment->setCancelledAt(new \DateTime());
        $this->entityManager->flush();

        // Enviar notificaciones de cancelación (respetando configuración de la empresa)
        $this->notificationService->sendAppointmentCancellationNotification($activeAppointment);

        // Registrar en auditoría
        $this->auditService->logChange(
            'Appointment',
            $activeAppointment->getId(),
            'appointment_cancelled_by_link',
            ['status' => $oldStatus],
            ['status' => 'cancelled', 'cancelled_at' => $activeAppointment->getCancelledAt()->format('Y-m-d H:i:s')]
        );
        
        return $activeAppointment;
    }

    /**
     * Validar token de seguridad
     */
    private function validateToken(int $appointmentId, string $token): void
    {
        $secret = $_ENV['APP_SECRET'] ?? 'default-secret';
        $parts = explode('.', $token);
        
        if (count($parts) !== 2) {
            throw new \InvalidArgumentException('Token inválido');
        }
        
        [$payload, $signature] = $parts;
        
        // Verificar firma
        $expectedSignature = hash_hmac('sha256', $payload, $secret);
        if (!hash_equals($expectedSignature, $signature)) {
            throw new \InvalidArgumentException('Token inválido');
        }
        
        // Decodificar payload
        $data = json_decode(base64_decode($payload), true);
        if (!$data) {
            throw new \InvalidArgumentException('Token inválido');
        }
        
        // Verificar que el appointment ID coincida
        if ($data['appointment_id'] !== $appointmentId) {
            throw new \InvalidArgumentException('Token inválido para este turno');
        }
    }

    private function getLocation(int $locationId, Company $company): Location
    {
        $location = $this->entityManager->getRepository(Location::class)->find($locationId);
        
        if (!$location || $location->getCompany() !== $company) {
            throw new \InvalidArgumentException('Ubicación no encontrada');
        }
        
        return $location;
    }

    /**
     * Modificar un turno existente
     */
    public function modifyAppointment(int $originalAppointmentId, string $token, array $newAppointmentData, Company $company): Appointment
    {
        // Validar token
        $this->validateToken($originalAppointmentId, $token);
        
        // Buscar el turno activo de la cadena
        $activeAppointment = $this->findActiveAppointmentFromChain($originalAppointmentId);
        if (!$activeAppointment) {
            throw new \InvalidArgumentException('No se encontró un turno activo para modificar');
        }
        
        // Verificar que el turno pertenezca a la empresa
        if ($activeAppointment->getCompany() !== $company) {
            throw new \InvalidArgumentException('Turno no encontrado');
        }
        
        // Validar reglas de modificación de la empresa
        $this->validateCompanyModificationRules($activeAppointment, $company);
        
        // Validar límite de modificaciones
        if (!$activeAppointment->canBeModified($company->getMaximumEdits())) {
            throw new \InvalidArgumentException('Se ha alcanzado el límite máximo de modificaciones para este turno');
        }
        
        // Crear el nuevo turno (sin enviar notificaciones de confirmación)
        $newAppointment = $this->createAppointment($newAppointmentData, $company, false, AppointmentSourceEnum::USER, false);
        
        // Configurar rastreo de modificaciones
        $rootAppointment = $activeAppointment->getRootAppointment();
        $newAppointment->setOriginalAppointment($rootAppointment);
        $newAppointment->setPreviousAppointment($activeAppointment);
        
        // Incrementar contador en la cita original
        $rootAppointment->incrementModificationCount();
        
        // Cancelar el turno activo (sin enviar email)
        $activeAppointment->setStatus(StatusEnum::CANCELLED);
        $activeAppointment->setCancelledAt(new \DateTime());
        
        // Persistir cambios
        $this->entityManager->flush();
        
        // Sincronizar el modification_count en todos los turnos relacionados
        $this->syncModificationCountForRelatedAppointments($rootAppointment);

        // Enviar notificaciones de modificación para el nuevo turno
        $this->notificationService->modifyAppointmentNotifications($newAppointment);
        
        // Registrar en auditoría
        $this->auditService->logChange(
            'Appointment',
            $activeAppointment->getId(),
            'modify',
            [
                'original_appointment_id' => $activeAppointment->getId(),
                'original_date' => $activeAppointment->getScheduledAt()->format('Y-m-d H:i:s'),
                'original_service' => $activeAppointment->getService()->getName(),
                'original_professional' => $activeAppointment->getProfessional()->getName(),
                'modification_count' => $rootAppointment->getModificationCount(),
                'root_appointment_id' => $rootAppointment->getId()
            ],
            [
                'new_appointment_id' => $newAppointment->getId(),
                'new_date' => $newAppointment->getScheduledAt()->format('Y-m-d H:i:s'),
                'new_service' => $newAppointment->getService()->getName(),
                'new_professional' => $newAppointment->getProfessional()->getName()
            ]
        );
        
        return $newAppointment;
    }

    /**
     * Validar reglas de modificación de la empresa
     */
    private function validateCompanyModificationRules(Appointment $appointment, Company $company): void
    {
        // Verificar si la empresa permite modificaciones
        if (!$company->isEditableBookings()) {
            throw new \InvalidArgumentException('Esta empresa no permite modificar turnos');
        }

        
        // Verificar si el turno puede ser editado
        if (!$appointment->canBeEdited()) {
            throw new \InvalidArgumentException('Este turno no puede ser modificado');
        }
        
        // Verificar tiempo mínimo para modificación
        $now = new \DateTime();
        $minEditTime = $company->getMinimumEditTime() ?? 0; // en minutos
        $timeDiff = $appointment->getScheduledAt()->getTimestamp() - $now->getTimestamp();
        
        if ($timeDiff < $minEditTime * 60) {
            throw new \InvalidArgumentException('No se puede modificar el turno con tan poca antelación');
        }
    }

    /**
     * Verificar si un turno puede ser modificado considerando el límite de modificaciones
     */
    public function canAppointmentBeModified(Appointment $appointment, Company $company): bool
    {
        try {
            $this->validateCompanyModificationRules($appointment, $company);
            return $appointment->canBeModified($company->getMaximumEdits());
        } catch (\InvalidArgumentException $e) {
            return false;
        }
    }

    /**
     * Validar token para preload de datos de modificación
     * 
     * @param int $appointmentId ID del turno
     * @param string $token Token de modificación
     * @return array|null Datos del turno si el token es válido, null si no
     */
    public function validateTokenForPreload(int $appointmentId, string $token): ?array
    {
        try {
            $secret = $_ENV['APP_SECRET'] ?? 'default-secret';
            $parts = explode('.', $token);
            
            if (count($parts) !== 2) {
                return null;
            }
            
            [$payload, $signature] = $parts;
            
            // Verificar firma
            $expectedSignature = hash_hmac('sha256', $payload, $secret);
            if (!hash_equals($expectedSignature, $signature)) {
                return null;
            }
            
            // Decodificar payload
            $data = json_decode(base64_decode($payload), true);
            if (!$data || $data['appointment_id'] !== $appointmentId) {
                return null;
            }
            
            // Buscar el turno
            $appointment = $this->entityManager->getRepository(Appointment::class)->find($appointmentId);
            if (!$appointment) {
                return null;
            }
            
            // Retornar datos del turno para preload
            return [
                'appointment' => $appointment,
                'patient' => [
                    'first_name' => $appointment->getPatient()?->getFirstName(),
                    'last_name' => $appointment->getPatient()?->getLastName(),
                    'email' => $appointment->getPatient()?->getEmail(),
                    'phone' => $appointment->getPatient()?->getPhone(),
                ],
                'service_id' => $appointment->getService()?->getId(),
                'professional_id' => $appointment->getProfessional()->getId(),
                'location_id' => $appointment->getLocation()?->getId(),
                'original_date' => $appointment->getScheduledAt()->format('Y-m-d'),
                'original_time' => $appointment->getScheduledAt()->format('H:i'),
            ];
            
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Encuentra el turno activo de una cadena de modificaciones
     * Si el turno dado es el root y no tiene modificaciones, lo retorna
     * Si el turno dado es una modificación o el root tiene modificaciones, busca el más reciente
     */
    public function findActiveAppointmentFromChain(int $appointmentId): ?Appointment
    {
        $appointment = $this->appointmentRepository->find($appointmentId);
        
        if (!$appointment) {
            return null;
        }
        
        // Determinar el ID del turno root
        $rootId = $appointment->getOriginalAppointment()?->getId() ?? $appointment->getId();
        
        // Buscar el turno más reciente de esta cadena
        // Primero intentamos encontrar modificaciones del root
        $latestModification = $this->appointmentRepository->createQueryBuilder('a')
            ->where('a.originalAppointment = :rootId')
            ->andWhere('a.status IN (:activeStatuses)')
            ->setParameter('rootId', $rootId)
            ->setParameter('activeStatuses', [StatusEnum::SCHEDULED, StatusEnum::CONFIRMED])
            ->orderBy('a.modificationCount', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
        
        // Si encontramos una modificación activa, la retornamos
        if ($latestModification) {
            return $latestModification;
        }
        
        // Si no hay modificaciones activas, verificamos si el root está activo
        $rootAppointment = $this->appointmentRepository->find($rootId);
        if ($rootAppointment && in_array($rootAppointment->getStatus(), [StatusEnum::SCHEDULED, StatusEnum::CONFIRMED])) {
            return $rootAppointment;
        }
        
        // Si llegamos aquí, no hay turnos activos en la cadena
        return null;
    }


    /**
     * Sincroniza el modification_count en todos los turnos relacionados
     */
    private function syncModificationCountForRelatedAppointments(Appointment $rootAppointment): void
    {
        $modificationCount = $rootAppointment->getModificationCount();
        
        // Buscar todos los turnos que tienen este como original_appointment_id
        $relatedAppointments = $this->entityManager->getRepository(Appointment::class)
            ->findBy(['originalAppointment' => $rootAppointment]);
        
        // Actualizar el modification_count en todos los turnos relacionados
        foreach ($relatedAppointments as $appointment) {
            $appointment->setModificationCount($modificationCount);
        }
        
        // No necesitamos flush aquí, se hará en el método que llama a este
    }

    /**
     * Filtra slots disponibles excluyendo aquellos que están bloqueados
     */
    public function filterAvailableSlots(array $slots, int $professionalId, string $appointmentDate): array
    {
        if (empty($slots)) {
            return $slots;
        }

        // Obtener todos los bloques activos para este profesional y fecha
        $blocks = $this->getActiveBlocks($professionalId, $appointmentDate);
        
        if (empty($blocks)) {
            return $slots;
        }

        $filteredSlots = [];
        $dayOfWeek = (int)(new \DateTime($appointmentDate))->format('w'); // 0=Domingo, 1=Lunes, ..., 6=Sábado
        $appointmentDay = (int)(new \DateTime($appointmentDate))->format('j'); // Día del mes

        foreach ($slots as $slot) {
            $slotBlocked = false;
            
            // Crear DateTime objects para el slot
            $slotStart = new \DateTime($appointmentDate . ' ' . $slot['time']);
            $slotEnd = new \DateTime($appointmentDate . ' ' . $slot['end_time']);
            
            foreach ($blocks as $block) {
                $blockAffectsSlot = false;
                
                switch ($block['block_type']) {
                    case 'single_day':
                        // Verificar si la fecha del bloque coincide con la fecha de la cita
                        if ($block['start_date'] === $appointmentDate) {
                            $blockAffectsSlot = $this->checkSlotTimeOverlap($block, $slotStart, $slotEnd);
                        }
                        break;
                        
                    case 'date_range':
                        // Verificar si la fecha de la cita está dentro del rango
                        if ($appointmentDate >= $block['start_date'] && ($block['end_date'] === null || $appointmentDate <= $block['end_date'])) {
                            $blockAffectsSlot = $this->checkSlotTimeOverlap($block, $slotStart, $slotEnd);
                        }
                        break;
                        
                    case 'weekdays_pattern':
                        // Verificar si el día de la semana está en el patrón
                        $weekdaysPattern = explode(',', $block['weekdays_pattern']);
                        if (in_array((string)$dayOfWeek, $weekdaysPattern)) {
                            $blockAffectsSlot = $this->checkSlotTimeOverlap($block, $slotStart, $slotEnd);
                        }
                        break;
                        
                    case 'monthly_recurring':
                        // Verificar si el día del mes coincide
                        $blockDay = (int)(new \DateTime($block['start_date']))->format('j');
                        if ($appointmentDay === $blockDay) {
                            $blockAffectsSlot = $this->checkSlotTimeOverlap($block, $slotStart, $slotEnd);
                        }
                        break;
                }
                
                if ($blockAffectsSlot) {
                    $slotBlocked = true;
                    break; // No necesitamos verificar más bloques para este slot
                }
            }
            
            // Solo agregar el slot si no está bloqueado
            if (!$slotBlocked) {
                $filteredSlots[] = $slot;
            }
        }
        
        return $filteredSlots;
    }

    /**
     * Verifica si un bloque afecta a un slot específico basado en tiempo
     */
    private function checkSlotTimeOverlap(array $block, \DateTime $slotStart, \DateTime $slotEnd): bool
    {
        // Si start_time o end_time son null, el bloque afecta todo el día
        if ($block['start_time'] === null || $block['end_time'] === null) {
            return true;
        }
        
        // Convertir las horas del bloque a DateTime objects
        $blockStart = new \DateTime($slotStart->format('Y-m-d') . ' ' . $block['start_time']);
        $blockEnd = new \DateTime($slotStart->format('Y-m-d') . ' ' . $block['end_time']);
        
        // Si end_time es null, el bloque va hasta el final del día
        if ($block['end_time'] === null) {
            $blockEnd = new \DateTime($slotStart->format('Y-m-d') . ' 23:59:59');
        }
        
        // Convertir DateTime objects a strings para checkTimeOverlap
        $slotStartTime = $slotStart->format('H:i:s');
        $slotEndTime = $slotEnd->format('H:i:s');
        
        // Verificar si hay solapamiento
        return $this->checkTimeOverlap($slotStartTime, $slotEndTime, $blockStart, $blockEnd);
    }

    /**
     * Obtiene todos los bloques activos para un profesional y fecha
     */
    private function getActiveBlocks(int $professionalId, string $appointmentDate): array
    {
        // Obtener el día de la semana (0=Domingo, 1=Lunes, ..., 6=Sábado)
        $dayOfWeek = (new \DateTime($appointmentDate))->format('w');
        
        $sql = "
            SELECT pb.*
            FROM professional_blocks pb
            WHERE pb.professional_id = :professional_id 
            AND pb.active = true 
            AND ( 
                (pb.block_type = 'single_day' AND pb.start_date = :appointment_date) 
            OR 
                (pb.block_type = 'date_range' AND pb.start_date <= :appointment_date AND (pb.end_date IS NULL OR pb.end_date >= :appointment_date)) 
            OR 
                ( 
                    pb.block_type = 'weekdays_pattern' 
                    AND 
                    pb.start_date <= :appointment_date AND (pb.end_date IS NULL OR pb.end_date >= :appointment_date) 
                    AND 
                    pb.weekdays_pattern LIKE '%' || :day_of_week || '%'
                ) 
            OR 
                (                
                    pb.block_type = 'monthly_recurring' 
                    AND 
                    pb.start_date <= :appointment_date AND (pb.end_date IS NULL OR pb.end_date >= :appointment_date) 
                ) 
            )
        ";
        
        $params = [
            'professional_id' => $professionalId,
            'appointment_date' => $appointmentDate,
            'day_of_week' => $dayOfWeek
        ];

        // $debugSql = $sql;
        // foreach ($params as $key => $value) {
        //     $debugSql = str_replace(':' . $key, "'" . $value . "'", $debugSql);
        // }
        // var_dump($debugSql);exit;
        
        return $this->entityManager->getConnection()->fetchAllAssociative($sql, $params);
    }
}