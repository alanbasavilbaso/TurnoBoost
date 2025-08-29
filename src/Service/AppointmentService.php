<?php

namespace App\Service;

use App\Entity\Appointment;
use App\Entity\Clinic;
use App\Entity\Professional;
use App\Entity\Service;
use App\Entity\ProfessionalService;
use App\Entity\StatusEnum;
use App\Repository\ProfessionalRepository;
use App\Repository\ServiceRepository;
use Doctrine\ORM\EntityManagerInterface;

class AppointmentService
{
    private EntityManagerInterface $entityManager;
    private ProfessionalRepository $professionalRepository;
    private ServiceRepository $serviceRepository;
    private PatientService $patientService;

    public function __construct(
        EntityManagerInterface $entityManager,
        ProfessionalRepository $professionalRepository,
        ServiceRepository $serviceRepository,
        PatientService $patientService
    ) {
        $this->entityManager = $entityManager;
        $this->professionalRepository = $professionalRepository;
        $this->serviceRepository = $serviceRepository;
        $this->patientService = $patientService;
    }

    /**
     * Crea una nueva cita con todas las validaciones necesarias
     */
    public function createAppointment(array $data, Clinic $clinic): Appointment
    {
        // Validar y obtener datos básicos
        $appointmentData = $this->validateAndParseData($data);
        
        // Obtener entidades relacionadas
        $professional = $this->getProfessional($appointmentData['professionalId'], $clinic);
        $service = $this->getService($appointmentData['serviceId'], $clinic);
        
        // Calcular duración y hora de finalización
        $duration = $this->calculateDuration($professional, $service);
        $endTime = (clone $appointmentData['scheduledAt'])->add(new \DateInterval('PT' . $duration . 'M'));
        
        // Ejecutar todas las validaciones
        $this->validateAppointment(
            $appointmentData['scheduledAt'],
            $endTime,
            $professional,
            $clinic
        );
        
        // Crear o buscar paciente
        $patient = $this->patientService->findOrCreatePatient($appointmentData['patientData'], $clinic);
        
        // Crear la cita
        $appointment = new Appointment();
        $appointment->setClinic($clinic)
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
        
        // Construir la fecha y hora programada
        if (isset($data['scheduled_at'])) {
            $scheduledAt = new \DateTime($data['scheduled_at']);
        } elseif (isset($data['date']) && isset($data['time'])) {
            if (str_contains($data['time'], 'T')) {
                $scheduledAt = new \DateTime($data['time']);
            } else {
                $scheduledAt = new \DateTime($data['date'] . ' ' . $data['time']);
            }
        } else {
            throw new \InvalidArgumentException('Fecha y hora son requeridas');
        }
        
        if (!$professionalId || !$serviceId) {
            throw new \InvalidArgumentException('Profesional y servicio son requeridos');
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
        
        return [
            'professionalId' => $professionalId,
            'serviceId' => $serviceId,
            'scheduledAt' => $scheduledAt,
            'patientData' => $patientData,
            'notes' => $data['notes'] ?? null
        ];
    }

    /**
     * Obtiene y valida el profesional
     */
    private function getProfessional(int $professionalId, Clinic $clinic): Professional
    {
        $professional = $this->professionalRepository->find($professionalId);
        
        if (!$professional || $professional->getClinic() !== $clinic) {
            throw new \InvalidArgumentException('Profesional no encontrado');
        }
        
        return $professional;
    }

    /**
     * Obtiene y valida el servicio
     */
    private function getService(int $serviceId, Clinic $clinic): Service
    {
        $service = $this->serviceRepository->find($serviceId);
        
        if (!$service || $service->getClinic() !== $clinic) {
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
        \DateTime $endTime,
        Professional $professional,
        Clinic $clinic
    ): void {
        // Validar que no sea una fecha pasada
        if ($scheduledAt < new \DateTime()) {
            throw new \InvalidArgumentException(
                'No se pueden crear citas en fechas pasadas.'
            );
        }
        
        // Validar disponibilidad del profesional
        $this->validateProfessionalAvailability($scheduledAt, $endTime, $professional);
        
        // Validar conflictos de horarios
        $this->validateTimeConflicts($scheduledAt, $endTime, $professional, $clinic);
    }

    /**
     * Valida que el horario esté dentro de la disponibilidad del profesional
     */
    private function validateProfessionalAvailability(
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
     * Valida que no haya conflictos con otras citas
     */
    private function validateTimeConflicts(
        \DateTime $scheduledAt,
        \DateTime $endTime,
        Professional $professional,
        Clinic $clinic
    ): void {
        $conflictCount = $this->entityManager->createQueryBuilder()
            ->select('COUNT(a.id)')
            ->from(Appointment::class, 'a')
            ->where('a.professional = :professional')
            ->andWhere('a.clinic = :clinic')
            ->andWhere('a.status NOT IN (:cancelledStatus)')
            ->andWhere(
                '(a.scheduledAt < :endTime AND ' .
                'DATE_ADD(a.scheduledAt, a.durationMinutes, \'MINUTE\') > :startTime)'
            )
            ->setParameter('professional', $professional)
            ->setParameter('clinic', $clinic)
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
}