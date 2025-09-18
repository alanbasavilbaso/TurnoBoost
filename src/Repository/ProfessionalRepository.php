<?php

namespace App\Repository;

use App\Entity\Professional;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Professional>
 *
 * @method Professional|null find($id, $lockMode = null, $lockVersion = null)
 * @method Professional|null findOneBy(array $criteria, array $orderBy = null)
 * @method Professional[]    findAll()
 * @method Professional[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ProfessionalRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Professional::class);
    }

    /**
     * Encuentra profesionales por locales
     */
    public function findByLocation($location, array $orderBy = []): array
    {
        $qb = $this->createQueryBuilder('p')
            ->andWhere('p.company = :company')
            ->setParameter('company', $location->getCompany());

        foreach ($orderBy as $field => $direction) {
            $qb->addOrderBy('p.' . $field, $direction);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Encuentra profesionales activos por locales
     */
    public function findActiveByLocation($location): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.company = :company')
            ->andWhere('p.active = :active')
            ->setParameter('company', $location->getCompany())
            ->setParameter('active', true)
            ->orderBy('p.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Busca profesionales por nombre o especialidad
     */
    public function searchByNameOrSpecialty($location, string $searchTerm): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.company = :company')
            ->andWhere('p.name LIKE :searchTerm OR p.specialty LIKE :searchTerm')
            ->setParameter('company', $location->getCompany())
            ->setParameter('searchTerm', '%' . $searchTerm . '%')
            ->orderBy('p.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Cuenta profesionales por locales
     */
    public function countByLocation($location): int
    {
        return $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.company = :company')
            ->setParameter('company', $location->getCompany())
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Encuentra profesionales con citas en un rango de fechas
     */
    public function findWithAppointmentsInDateRange($location, \DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.appointments', 'a')
            ->andWhere('p.company = :company')
            ->andWhere('a.appointmentDate BETWEEN :startDate AND :endDate')
            ->setParameter('company', $location->getCompany())
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->groupBy('p.id')
            ->orderBy('p.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function getProfessionalSchedulesForDay(Professional $professional, int $weekday, \DateTime $date = null): array
    {
        $sql = "
            SELECT 
                pa.start_time AS start_time, 
                pa.end_time AS end_time, 
                'Disponibilidad Regular' AS tipo 
            FROM professional_availability pa 
            WHERE pa.professional_id = :professionalId 
                AND pa.weekday = :weekday 
            
            UNION ALL 
            
            SELECT 
                ss.start_time AS start_time, 
                ss.end_time AS end_time, 
                'Horario Especial' AS tipo 
            FROM special_schedules ss 
            WHERE ss.professional_id = :professionalId 
                AND EXTRACT(DOW FROM ss.date) = :weekday 
                " . ($date ? "AND ss.date = :specificDate" : "") . "
            ORDER BY start_time
        ";
        
        $params = [
            'professionalId' => $professional->getId(),
            'weekday' => $weekday
        ];
        
        if ($date) {
            $params['specificDate'] = $date->format('Y-m-d');
        }
        
        $stmt = $this->getEntityManager()->getConnection()->prepare($sql);
        return $stmt->executeQuery($params)->fetchAllAssociative();
    }
}