<?php

namespace App\Service;

use App\Entity\Appointment;
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
use phpDocumentor\Reflection\DocBlock\Tags\Var_;

class AppointmentService
{
    private EntityManagerInterface $entityManager;
    private ProfessionalRepository $professionalRepository;
    private ServiceRepository $serviceRepository;
    private PatientService $patientService;
    private AppointmentRepository $appointmentRepository;

    public function __construct(
        EntityManagerInterface $entityManager,
        ProfessionalRepository $professionalRepository,
        ServiceRepository $serviceRepository,
        PatientService $patientService,
        AppointmentRepository $appointmentRepository
    ) {
        $this->entityManager = $entityManager;
        $this->professionalRepository = $professionalRepository;
        $this->serviceRepository = $serviceRepository;
        $this->patientService = $patientService;
        $this->appointmentRepository = $appointmentRepository;
    }

    /**
     * Crea una nueva cita con todas las validaciones necesarias
     */
    public function createAppointment(array $data, Company $company, bool $force = false): Appointment
    {
        // Validar y obtener datos básicos
        $appointmentData = $this->validateAndParseData($data);
        
        // Obtener entidades relacionadas
        $professional = $this->getProfessional($appointmentData['professionalId'], $company);
        $service = $this->getService($appointmentData['serviceId'], $company);
        $location = $this->getLocation($appointmentData['locationId'], $company); // Agregar esta línea
        
        // Calcular duración y hora de finalización
        $duration = $this->calculateDuration($professional, $service);
        
        // Ejecutar todas las validaciones (solo si no se fuerza)
        if (!$force) {
            $this->validateAppointment(
                $appointmentData['scheduledAt'],
                $duration,
                $professional,
                $service,
                $location // Ahora $location está definida
            );
        }
        // var_dump($force);
        // var_dump($appointmentData['scheduledAt']);
        // var_dump($endTime);
        // exit;
        // Crear o buscar paciente
        $patient = $this->patientService->findOrCreatePatient($appointmentData['patientData'], $company);
        
        // Crear la cita
        $appointment = new Appointment();
        $appointment->setCompany($company) // Agregar esta línea
               ->setLocation($location)
               ->setProfessional($professional)
               ->setService($service)
               ->setPatient($patient)
               ->setScheduledAt($appointmentData['scheduledAt'])
               ->setDurationMinutes($duration)
               ->setNotes($appointmentData['notes']);
        
        $this->entityManager->persist($appointment);
        $this->entityManager->flush();
        
        return $appointment;
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
            'locationId' => $locationId, // Agregar esta línea
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
    private function validateAppointment(
        \DateTime $scheduledAt,
        int $durationMinutes, 
        Professional $professional,
        Service $service,
        Location $location
    ): void {
        $available = $this->appointmentRepository->validateSlotAvailability(
            $scheduledAt,
            $durationMinutes,
            $professional->getId(),
            $service->getId(),
            null
        );
        
      
        if (!$available['available']) {
            if (isset($available['details']['appointments']) && $available['details']['appointments'] > 0) {
                throw new \InvalidArgumentException(
                    'El horario seleccionado se superpone con una cita existente'
                );
            } else {
                throw new \InvalidArgumentException(
                    'El horario seleccionado está fuera de la disponibilidad del profesional.',
                    1
                );
            }
        }

        $endTime = (clone $scheduledAt)->add(new \DateInterval('PT' . $durationMinutes . 'M'));

        // Verificar bloqueos
        $this->validateBlockConflicts($scheduledAt, $endTime, $professional);
    }

    /**
     * Valida que no haya bloqueos que interfieran con la cita
     */
    // Corregir el método validateBlockConflicts (líneas 205-246)
    private function validateBlockConflicts(
        \DateTime $scheduledAt,
        \DateTime $endTime,
        Professional $professional
    ): void {
        $appointmentDate = $scheduledAt->format('Y-m-d');
        $appointmentStartTime = $scheduledAt->format('H:i:s');
        $appointmentEndTime = $endTime->format('H:i:s');
        $dayOfWeek = (int)$scheduledAt->format('N') - 1; // 0=Lunes, 6=Domingo
        
        // Buscar bloqueos que puedan interferir
        $qb = $this->entityManager->createQueryBuilder()
            ->select('pb')
            ->from(ProfessionalBlock::class, 'pb')
            ->where('pb.professional = :professional')
            ->andWhere('pb.active = true')
            ->setParameter('professional', $professional);
        
        // Filtrar por tipo de bloque
        $qb->andWhere(
            $qb->expr()->orX(
                // Bloque de día único - CORREGIDO: usar comparación directa de fechas
                $qb->expr()->andX(
                    $qb->expr()->eq('pb.blockType', ':singleDay'),
                    $qb->expr()->eq('pb.startDate', ':appointmentDateObj')
                ),
                // Bloque de rango de fechas
                $qb->expr()->andX(
                    $qb->expr()->eq('pb.blockType', ':dateRange'),
                    $qb->expr()->lte('pb.startDate', ':appointmentDateObj'),
                    $qb->expr()->gte('pb.endDate', ':appointmentDateObj')
                ),
                // Bloque de patrón semanal
                $qb->expr()->andX(
                    $qb->expr()->eq('pb.blockType', ':weekdaysPattern'),
                    $qb->expr()->like('pb.weekdaysPattern', ':dayOfWeekPattern')
                )
            )
        )
        ->setParameter('singleDay', 'single_day')
        ->setParameter('dateRange', 'date_range')
        ->setParameter('weekdaysPattern', 'weekdays_pattern')
        ->setParameter('appointmentDateObj', (clone $scheduledAt)->setTime(0, 0, 0)) // Fecha sin hora
        ->setParameter('dayOfWeekPattern', '%' . $dayOfWeek . '%');
        
        $blocks = $qb->getQuery()->getResult();
        
        foreach ($blocks as $block) {
            $blockAffectsAppointment = false;
            
            // Verificar si el bloque afecta la cita según su tipo
            switch ($block->getBlockType()) {
                case 'single_day':
                    $blockAffectsAppointment = $this->checkTimeOverlap(
                        $appointmentStartTime, $appointmentEndTime,
                        $block->getStartTime(), $block->getEndTime()
                    );
                    break;
                    
                case 'date_range':
                    $blockAffectsAppointment = $this->checkTimeOverlap(
                        $appointmentStartTime, $appointmentEndTime,
                        $block->getStartTime(), $block->getEndTime()
                    );
                    break;
                    
                case 'weekdays_pattern':
                    // Verificar si el día de la semana está en el patrón
                    $weekdays = explode(',', $block->getWeekdaysPattern());
                    if (in_array((string)$dayOfWeek, $weekdays)) {
                        $blockAffectsAppointment = $this->checkTimeOverlap(
                            $appointmentStartTime, $appointmentEndTime,
                            $block->getStartTime(), $block->getEndTime()
                        );
                    }
                    break;
            }
            
            if ($blockAffectsAppointment) {
                throw new \InvalidArgumentException(
                    'Hay un bloqueo de agenda para el horario elegido, con el motivo: ' . $block->getReason()
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
     * Valida que el horario esté dentro de la disponibilidad del profesional
     */
    private function validateProfessionalAvailability(
        \DateTime $scheduledAt,
        \DateTime $endTime,
        Professional $professional
    ): void {
        $dayOfWeek = (int)$scheduledAt->format('N'); // 0=Domingo, 1=Lunes
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
            'professionalId' => $appointment->getProfessional()->getId(),
            'serviceId' => $appointment->getService()->getId(),
            'status' => $appointment->getStatus()->value,
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
        
        $appointment = $this->entityManager->getRepository(Appointment::class)->find($appointmentId);
        
        if (!$appointment) {
            throw new \InvalidArgumentException('Turno no encontrado');
        }
        
        if ($appointment->getProfessional()->getCompany() !== $company) {
            throw new \InvalidArgumentException('Turno no pertenece a esta empresa');
        }
        
        if ($appointment->getStatus() === StatusEnum::CANCELLED) {
            throw new \InvalidArgumentException('Este turno ya fue cancelado');
        }
        
        if ($appointment->getStatus() === StatusEnum::CONFIRMED) {
            throw new \InvalidArgumentException('Este turno ya fue confirmado');
        }
        
        // Confirmar el turno
        $appointment->setStatus(StatusEnum::CONFIRMED);
        $this->entityManager->flush();
        
        // Log de auditoría
        $this->auditService->log(
            'appointment_confirmed_by_link',
            'Appointment',
            $appointment->getId(),
            ['method' => 'secure_link']
        );
        
        return $appointment;
    }

    /**
     * Validar token y cancelar appointment
     */
    public function cancelAppointmentByToken(int $appointmentId, string $token, Company $company): Appointment
    {
        $this->validateToken($appointmentId, $token);
        
        $appointment = $this->entityManager->getRepository(Appointment::class)->find($appointmentId);
        
        if (!$appointment) {
            throw new \InvalidArgumentException('Turno no encontrado');
        }
        
        if ($appointment->getProfessional()->getCompany() !== $company) {
            throw new \InvalidArgumentException('Turno no pertenece a esta empresa');
        }
        
        if ($appointment->getStatus() === StatusEnum::CANCELLED) {
            throw new \InvalidArgumentException('Este turno ya fue cancelado');
        }
        
        // Cancelar el turno
        $appointment->setStatus(StatusEnum::CANCELLED);
        $this->entityManager->flush();
        
        // Log de auditoría
        $this->auditService->log(
            'appointment_cancelled_by_link',
            'Appointment',
            $appointment->getId(),
            ['method' => 'secure_link']
        );
        
        return $appointment;
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
        
        // Verificar expiración
        if (time() > $data['expires']) {
            throw new \InvalidArgumentException('El enlace ha expirado');
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
}