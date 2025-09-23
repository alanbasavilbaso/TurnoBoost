<?php

namespace App\Repository;

use App\Entity\Appointment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Appointment>
 *
 * @method Appointment|null find($id, $lockMode = null, $lockVersion = null)
 * @method Appointment|null findOneBy(array $criteria, array $orderBy = null)
 * @method Appointment[]    findAll()
 * @method Appointment[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class AppointmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Appointment::class);
    }

    /**
     * Valida la disponibilidad de un slot en una sola consulta optimizada
     * 
     * @param \DateTime $dateTime Fecha y hora del slot
     * @param int $durationMinutes Duración en minutos
     * @param int $professionalId ID del profesional
     * @param int $serviceId ID del servicio
     * @param int|null $excludeAppointmentId ID de cita a excluir (para ediciones)
     * @return array Resultado con información de disponibilidad
     */
    public function validateSlotAvailability(
        \DateTime $dateTime,
        int $durationMinutes,
        int $professionalId,
        int $serviceId,
        ?int $excludeAppointmentId = null
    ): array {
        $endTime = clone $dateTime;
        $endTime->add(new \DateInterval('PT' . $durationMinutes . 'M'));
        
        $date = $dateTime->format('Y-m-d');
        $startTime = $dateTime->format('H:i:s');
        $endTimeFormatted = $endTime->format('H:i:s');
        $dayOfWeek = (int)$dateTime->format('w'); // 0=Domingo, 1=Lunes, ..., 6=Sábado
        
        $sql = "
            SELECT 
                -- Verificar citas existentes (conflictos)
                COUNT(DISTINCT a.id) as appointments,
                
                -- Verificar horarios especiales con servicios
                CASE 
                    WHEN ss.id IS NOT NULL AND sss.special_schedule_id IS NOT NULL THEN 1 
                    ELSE 0 
                END AS has_special_schedules,
                
                -- Verificar disponibilidad regular del profesional
                pa.id as professional_availability
                
            FROM 
                professionals p
                
            -- JOIN para servicios del profesional (verificar que el profesional ofrece el servicio)
            JOIN professional_services ps ON (
                p.id = ps.professional_id 
                AND ps.service_id = :serviceId
            )
            
            -- LEFT JOIN para disponibilidad regular del profesional
            LEFT JOIN professional_availability pa ON (
                p.id = pa.professional_id
                AND pa.weekday = :dayOfWeek
                AND pa.start_time <= CAST(:startTime AS TIME)
                AND pa.end_time >= CAST(:endTime AS TIME)
            )
            
            -- LEFT JOIN para citas existentes (detectar conflictos)
            LEFT JOIN appointments a ON (
                p.id = a.professional_id
                AND DATE(a.scheduled_at) = CAST(:date AS DATE)
                AND UPPER(a.status) != 'CANCELLED'
                AND (
                    -- Verificar solapamiento de horarios
                    -- a.scheduled_at::TIME BETWEEN CAST(:startTime AS TIME) AND CAST(:endTime AS TIME)
                    -- OR (a.scheduled_at + INTERVAL '1 minute' * a.duration_minutes)::TIME BETWEEN CAST(:startTime AS TIME) AND CAST(:endTime AS TIME)
                    -- OR (CAST(:startTime AS TIME) BETWEEN a.scheduled_at::TIME AND (a.scheduled_at + INTERVAL '1 minute' * a.duration_minutes)::TIME)

                    -- Verificar solapamiento de horarios (sin incluir extremos)
                    -- Hay solapamiento si:
                    -- 1. La cita existente empieza antes de que termine la nueva cita
                    -- 2. Y la cita existente termina después de que empiece la nueva cita
                    a.scheduled_at::TIME < CAST(:endTime AS TIME)
                    AND (a.scheduled_at + INTERVAL '1 minute' * a.duration_minutes)::TIME > CAST(:startTime AS TIME)
                )
                " . ($excludeAppointmentId ? "AND a.id != :excludeAppointmentId" : "") . "
            )
            
            -- LEFT JOIN para horarios especiales
            LEFT JOIN special_schedules ss ON (
                ss.professional_id = p.id
                AND ss.date = CAST(:date AS DATE)
                AND ss.start_time <= CAST(:startTime AS TIME)
                AND ss.end_time >= CAST(:endTime AS TIME)
            )
            
            -- LEFT JOIN para servicios en horarios especiales
            LEFT JOIN special_schedule_services sss ON (
                sss.special_schedule_id = ss.id
                AND sss.service_id = :serviceId
                AND ss.id IS NOT NULL
            )
            
            WHERE 
                p.id = :professionalId
                -- El profesional debe tener disponibilidad regular O horario especial con el servicio
                AND (pa.weekday = :dayOfWeek OR ss.id IS NOT NULL)
                
            GROUP BY 
                p.id, ss.id, sss.special_schedule_id, pa.id
        ";
        
        $params = [
            'professionalId' => $professionalId,
            'serviceId' => $serviceId, // Nuevo parámetro necesario
            'date' => $date,
            'startTime' => $startTime,
            'endTime' => $endTimeFormatted,
            'dayOfWeek' => $dayOfWeek
        ];
        
        if ($excludeAppointmentId) {
            $params['excludeAppointmentId'] = $excludeAppointmentId;
        }
        
        $stmt = $this->getEntityManager()->getConnection()->prepare($sql);
        // $result = $stmt->executeQuery($params)->fetchAssociative();
        $result = $stmt->executeQuery($params)->fetchAssociative();
        // var_dump($result === false)
        // $finalSql = $sql;
        // foreach ($params as $key => $value) {
        //     $finalSql = str_replace(":$key", "'$value'", $finalSql);
        // }
        // var_dump($finalSql);
        // exit;

        $hasInvalidResult = $result === false;
        return [
            'available' => $hasInvalidResult ? false : $this->isSlotAvailable($result),
            'hasConflicts' => $hasInvalidResult ? true : (int)$result['appointments'] > 0,
            'hasSpecialSchedule' => $hasInvalidResult ? false : (int)$result['has_special_schedules'] > 0,
            'hasRegularAvailability' => $hasInvalidResult ? false : (int)$result['professional_availability'] > 0,
            'details' => $hasInvalidResult ? [] : $result
        ];
    }
    
    /**
     * Determina si el slot está disponible basado en los resultados de la consulta
     */
    private function isSlotAvailable(array $queryResult): bool
    {
        // Si hay conflictos con citas existentes, no está disponible
        if ((int)$queryResult['appointments'] > 0) {
            return false;
        }
        
        // Si hay horarios especiales, tienen prioridad sobre los regulares
        if ((int)$queryResult['has_special_schedules'] > 0) {
            return true;
        }
        
        // Si no hay horarios especiales, verificar disponibilidad regular
        return (int)$queryResult['professional_availability'] > 0;
    }
}