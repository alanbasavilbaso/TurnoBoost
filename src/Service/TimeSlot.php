<?php

namespace App\Service;

use App\Entity\Professional;
use App\Entity\Service;
use App\Entity\Appointment;
use App\Entity\ProfessionalService;
use App\Repository\AppointmentRepository;
use Doctrine\ORM\EntityManagerInterface;

class TimeSlot
{
    private EntityManagerInterface $entityManager;
    private AppointmentRepository $appointmentRepository;
    private int $defaultSlotInterval = 30; // minutos
    
    public function __construct(EntityManagerInterface $entityManager, AppointmentRepository $appointmentRepository)
    {
        $this->entityManager = $entityManager;
        $this->appointmentRepository = $appointmentRepository;
    }
    
    /**
     * Genera slots disponibles para un profesional, servicio y fecha específicos
     */
    public function generateAvailableSlots(
        Professional $professional, 
        Service $service, 
        \DateTime $date,
        ?int $excludeAppointmentId = null
    ): array {
        $dayOfWeek = (int)$date->format('N'); // 0=Lunes, 6=Domingo
        
        // Obtener el ProfessionalService para usar la duración efectiva
        $professionalService = $this->entityManager->getRepository(ProfessionalService::class)
            ->findOneBy([
                'professional' => $professional,
                'service' => $service
            ]);
        if (!$professionalService) {
            return [];
        }
        // Verificar si el servicio está disponible para este profesional en este día
        if (!$professionalService->isAvailableOnDay($dayOfWeek)) {
            return [];
        }
        // Obtener disponibilidades del profesional para este día
        $availabilities = $this->getProfessionalAvailabilities($professional, $dayOfWeek);
        
        if (empty($availabilities)) {
            return [];
        }
        
        // Obtener citas existentes para optimizar consultas (excluyendo el turno actual si se está editando)
        $existingAppointments = $this->getExistingAppointments($professional, $date, $excludeAppointmentId);
        
        // Obtener bloques de horario activos
        // $scheduleBlocks = $this->getActiveScheduleBlocks($professional, $date);
       
        $slots = [];
        foreach ($availabilities as $availability) {
            $availabilitySlots = $this->generateSlotsForAvailability(
                $availability,
                $professionalService, // Pasar ProfessionalService en lugar de Service
                $date,
                $professionalService->getEffectiveDuration(), // Usar la duración del ProfessionalService
                $existingAppointments,
                $scheduleBlocks = []
            );
            
            $slots = array_merge($slots, $availabilitySlots);
        }
        
        // Ordenar slots por hora
        usort($slots, fn($a, $b) => strcmp($a['time'], $b['time']));
        
        return $slots;
    }
    
    /**
     * Obtiene las disponibilidades del profesional para un día específico
     */
    private function getProfessionalAvailabilities(Professional $professional, int $dayOfWeek): array
    {
        $availabilities = $professional->getAvailabilities()->filter(
            fn($availability) => $availability->getWeekday() === $dayOfWeek
        )->toArray();
        
        // DEBUG: Log availabilities
        error_log("=== PROFESSIONAL AVAILABILITIES ===");
        error_log("Professional ID: " . $professional->getId());
        error_log("Day of week: " . $dayOfWeek);
        error_log("Total availabilities found: " . count($availabilities));
        
        foreach ($availabilities as $availability) {
            error_log("Availability: " . $availability->getStartTime()->format('H:i') . " - " . $availability->getEndTime()->format('H:i'));
        }
        error_log("===================================");
        
        return $availabilities;
    }
    
    /**
     * Obtiene las citas existentes del profesional para una fecha específica
     */
    private function getExistingAppointments(Professional $professional, \DateTime $date, ?int $excludeAppointmentId = null): array
    {
        $startOfDay = clone $date;
        $startOfDay->setTime(0, 0, 0);
        
        $endOfDay = clone $date;
        $endOfDay->setTime(23, 59, 59);
        
        $queryBuilder = $this->entityManager->getRepository(Appointment::class)
            ->createQueryBuilder('a')
            ->where('a.professional = :professional')
            ->andWhere('a.scheduledAt BETWEEN :start AND :end')
            ->setParameter('professional', $professional)
            ->setParameter('start', $startOfDay)
            ->setParameter('end', $endOfDay);
            
        // Excluir el turno actual si se está editando
        if ($excludeAppointmentId) {
            $queryBuilder->andWhere('a.id != :excludeId')
                        ->setParameter('excludeId', $excludeAppointmentId);
        }
            
        return $queryBuilder->getQuery()->getResult();
    }
    
    /**
     * Obtiene los bloques de horario activos para una fecha específica
     */
    private function getActiveScheduleBlocks(Professional $professional, \DateTime $date): array
    {
        $startOfDay = clone $date;
        $startOfDay->setTime(0, 0, 0);
        
        $endOfDay = clone $date;
        $endOfDay->setTime(23, 59, 59);
        
        return $professional->getScheduleBlocks()->filter(
            fn($block) => $block->getStartDatetime() <= $endOfDay && 
                         $block->getEndDatetime() >= $startOfDay
        )->toArray();
    }
    
    /**
     * Genera slots para una disponibilidad específica
     */
    private function generateSlotsForAvailability(
        $availability,
        ProfessionalService $professionalService,
        \DateTime $date,
        int $interval,
        array $existingAppointments,
        array $scheduleBlocks
    ): array {
        $slots = [];
        
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
        
        // Ordenar citas existentes por hora de inicio
        usort($existingAppointments, function($a, $b) {
            return $a->getScheduledAt() <=> $b->getScheduledAt();
        });
        
        $current = clone $startTime;
        
        while ($current < $endTime) {
            $slotEnd = clone $current;
            $slotEnd->modify('+' . $professionalService->getEffectiveDuration() . ' minutes');
            
            // Verificar que el slot completo esté dentro del horario de disponibilidad
            if ($slotEnd <= $endTime) {
                $isAvailable = $this->isSlotAvailable(
                    $current,
                    $slotEnd,
                    $existingAppointments
                );
                
                // Solo agregar slots disponibles
                if ($isAvailable) {
                    $slots[] = [
                        'time' => $current->format('H:i'),
                        'datetime' => $current->format('c'),
                        'end_time' => $slotEnd->format('H:i'),
                        'end_datetime' => $slotEnd->format('c'),
                        'available' => true,
                        'duration' => $professionalService->getEffectiveDuration()
                    ];
                    
                    // Avanzar normalmente por la duración del servicio
                    $current->modify('+' . $professionalService->getEffectiveDuration() . ' minutes');
                } else {
                    // Si el slot no está disponible, encontrar la próxima hora libre
                    $nextAvailableTime = $this->findNextAvailableTime(
                        $current,
                        $endTime,
                        $existingAppointments,
                        $scheduleBlocks,
                        $professionalService->getEffectiveDuration()
                    );
                    // dump($current);
                    // dump($nextAvailableTime);
                    // exit;
                    if ($nextAvailableTime) {
                        $current = $nextAvailableTime;
                    } else {
                        // No hay más tiempo disponible en esta disponibilidad
                        break;
                    }
                }
            } else {
                break;
            }
        }
        
        return $slots;
    }
    
    /**
     * Encuentra el próximo tiempo disponible después de una cita ocupada
     */
    private function findNextAvailableTime(
        \DateTime $currentTime,
        \DateTime $endTime,
        array $existingAppointments,
        array $scheduleBlocks,
        int $professionalServiceDuration
    ): ?\DateTime {
        // Buscar la cita que está causando el conflicto
        foreach ($existingAppointments as $appointment) {
            $appointmentStart = $appointment->getScheduledAt();
            $appointmentEnd = clone $appointmentStart;
            $appointmentEnd->modify('+' . $appointment->getDurationMinutes() . ' minutes');
            // usado para ver si el tiempo antes del turno, me sirve como un bloque de tiempo
            $potencialEndTime = clone $currentTime;
            $potencialEndTime->modify('+' . $professionalServiceDuration . ' minutes');
            // Si la cita se solapa con el tiempo actual
            if ($currentTime < $appointmentEnd && 
                (
                    $currentTime >= $appointmentStart
                    || 
                    ($currentTime < $appointmentStart && $potencialEndTime > $appointmentStart)
                )) {
                // El próximo tiempo disponible es después del final de la cita
                $nextTime = clone $appointmentEnd;
                
                // Verificar que esté dentro del horario de disponibilidad
                if ($nextTime < $endTime) {
                    return $nextTime;
                }
            }
        }
        
        // Verificar bloques de horario
        foreach ($scheduleBlocks as $block) {
            if ($currentTime < $block->getEndDatetime() && $currentTime >= $block->getStartDatetime()) {
                $nextTime = clone $block->getEndDatetime();
                
                if ($nextTime < $endTime) {
                    return $nextTime;
                }
            }
        }
        
        return null;
    }
    
    /**
     * Verifica si un slot específico está disponible
     */
    private function isSlotAvailable(
        \DateTime $slotStart,
        \DateTime $slotEnd,
        array $existingAppointments
    ): bool {
        // Verificar conflictos con citas existentes
        foreach ($existingAppointments as $appointment) {
            $appointmentStart = $appointment->getScheduledAt();
            $appointmentEnd = clone $appointmentStart;
            $appointmentEnd->modify('+' . $appointment->getDurationMinutes() . ' minutes');
            
            // Verificar si hay solapamiento
            if ($slotStart < $appointmentEnd && $slotEnd > $appointmentStart) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * Verifica si un slot específico está disponible para una fecha y hora
     */
    public function isSlotAvailableForDateTime(
        Professional $professional,
        Service $service,
        \DateTime $dateTime,
        int $durationMinutes,
        ?int $excludeAppointmentId = null
    ): bool {
        $result = $this->appointmentRepository->validateSlotAvailability(
            $dateTime,
            $durationMinutes,
            $professional->getId(),
            $service->getId(),
            $excludeAppointmentId
        );
        
        return $result['available'];
    }
    
    /**
     * Obtiene el siguiente slot disponible para un profesional y servicio
     */
    public function getNextAvailableSlot(
        Professional $professional,
        Service $service,
        \DateTime $fromDate = null
    ): ?array {
        $searchDate = $fromDate ?? new \DateTime();
        $maxDaysToSearch = 30; // Buscar hasta 30 días en el futuro
        
        for ($i = 0; $i < $maxDaysToSearch; $i++) {
            $slots = $this->generateAvailableSlots($professional, $service, $searchDate);
            
            foreach ($slots as $slot) {
                if ($slot['available']) {
                    return $slot;
                }
            }
            
            $searchDate->modify('+1 day');
        }
        
        return null;
    }
    
    /**
     * Configura el intervalo por defecto para los slots
     */
    public function setDefaultSlotInterval(int $minutes): self
    {
        $this->defaultSlotInterval = $minutes;
        return $this;
    }
    
    /**
     * Obtiene estadísticas de disponibilidad para un rango de fechas
     */
    public function getAvailabilityStats(
        Professional $professional,
        Service $service,
        \DateTime $startDate,
        \DateTime $endDate
    ): array {
        $stats = [
            'total_slots' => 0,
            'available_slots' => 0,
            'occupied_slots' => 0,
            'blocked_slots' => 0,
            'availability_percentage' => 0
        ];
        
        $currentDate = clone $startDate;
        
        while ($currentDate <= $endDate) {
            $slots = $this->generateAvailableSlots($professional, $service, $currentDate);
            
            foreach ($slots as $slot) {
                $stats['total_slots']++;
                
                if ($slot['available']) {
                    $stats['available_slots']++;
                } else {
                    $stats['occupied_slots']++;
                }
            }
            
            $currentDate->modify('+1 day');
        }
        
        if ($stats['total_slots'] > 0) {
            $stats['availability_percentage'] = round(
                ($stats['available_slots'] / $stats['total_slots']) * 100,
                2
            );
        }
        
        return $stats;
    }
}