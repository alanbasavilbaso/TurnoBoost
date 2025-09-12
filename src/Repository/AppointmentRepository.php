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
     * @param int|null $excludeAppointmentId ID de cita a excluir (para ediciones)
     * @return array Resultado con información de disponibilidad
     */
    public function validateSlotAvailability(
        \DateTime $dateTime,
        int $durationMinutes,
        int $professionalId,
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
                
                -- Verificar horarios especiales
                COUNT(DISTINCT ss.id) as specialSchedules,
                MIN(ss.start_time) as specialStartTime,
                MAX(ss.end_time) as specialEndTime,
                
                -- Verificar disponibilidad regular del profesional
                COUNT(DISTINCT pa.id) as professionalAvailability,
                MIN(pa.start_time) as regularStartTime,
                MAX(pa.end_time) as regularEndTime
                
            FROM 
                (SELECT CAST(:professionalId AS INTEGER) as professional_id) prof
                
            -- LEFT JOIN para citas existentes (detectar conflictos)
            LEFT JOIN appointments a ON (
                a.professional_id = prof.professional_id
                AND DATE(a.scheduled_at) = CAST(:date AS DATE)
                AND a.status != 'CANCELLED'
                AND (
                    -- Verificar solapamiento de horarios: cita existente se solapa con el nuevo slot
                    (a.scheduled_at::TIME < CAST(:endTime AS TIME) AND 
                     (a.scheduled_at + INTERVAL '1 minute' * a.duration_minutes)::TIME > CAST(:startTime AS TIME))
                )
                " . ($excludeAppointmentId ? "AND a.id != :excludeAppointmentId" : "") . "
            )
            
            -- LEFT JOIN para horarios especiales
            LEFT JOIN special_schedules ss ON (
                ss.professional_id = prof.professional_id
                AND ss.date = CAST(:date AS DATE)
                AND ss.start_time <= CAST(:startTime AS TIME)
                AND ss.end_time >= CAST(:endTime AS TIME)
            )
            
            -- LEFT JOIN para disponibilidad regular del profesional
            LEFT JOIN professional_availability pa ON (
                pa.professional_id = prof.professional_id
                AND pa.weekday = :dayOfWeek
                AND pa.start_time <= CAST(:startTime AS TIME)
                AND pa.end_time >= CAST(:endTime AS TIME)
            )
        ";
        
        $params = [
            'professionalId' => $professionalId,
            'date' => $date,
            'startTime' => $startTime,
            'endTime' => $endTimeFormatted,
            'dayOfWeek' => $dayOfWeek
        ];
        
        if ($excludeAppointmentId) {
            $params['excludeAppointmentId'] = $excludeAppointmentId;
        }
        
        $stmt = $this->getEntityManager()->getConnection()->prepare($sql);
        $result = $stmt->executeQuery($params)->fetchAssociative();
        
        return [
            'available' => $this->isSlotAvailable($result),
            'hasConflicts' => (int)$result['appointments'] > 0,
            'hasSpecialSchedule' => (int)$result['specialschedules'] > 0,
            'hasRegularAvailability' => (int)$result['professionalavailability'] > 0,
            'details' => $result
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
        if ((int)$queryResult['specialschedules'] > 0) {
            return true;
        }
        
        // Si no hay horarios especiales, verificar disponibilidad regular
        return (int)$queryResult['professionalavailability'] > 0;
    }
    
    /**
     * Obtiene todas las citas de un profesional en un día específico
     */
    public function findByProfessionalAndDate(int $professionalId, \DateTime $date): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.professional = :professionalId')
            ->andWhere('DATE(a.scheduledAt) = :date')
            ->andWhere('a.status != :cancelledStatus')
            ->setParameter('professionalId', $professionalId)
            ->setParameter('date', $date->format('Y-m-d'))
            ->setParameter('cancelledStatus', 'CANCELLED')
            ->orderBy('a.scheduledAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}