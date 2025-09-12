<?php

namespace App\Repository;

use App\Entity\ProfessionalService;
use App\Entity\Location;
use App\Entity\Company;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Service>
 *
 * @method Service|null find($id, $lockMode = null, $lockVersion = null)
 * @method Service|null findOneBy(array $criteria, array $orderBy = null)
 * @method Service[]    findAll()
 * @method Service[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ProfessionalServiceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProfessionalService::class);
    }

    /**
     * Buscar servicios por nombre y local
     */
    public function findByProfesionalIdAndServiceId(int $professionalId, int $serviceId, int $dayOfWeek): ?ProfessionalService
    {
        $dayFields = [
            0 => 'availableSunday',
            1 => 'availableMonday', 
            2 => 'availableTuesday',
            3 => 'availableWednesday',
            4 => 'availableThursday',
            5 => 'availableFriday',
            6 => 'availableSaturday'
        ];

        if (!isset($dayFields[$dayOfWeek])) {
            throw new \InvalidArgumentException('dayOfWeek debe estar entre 0 (Domingo) y 6 (Sábado)');
        }

        return $this->createQueryBuilder('ps')
            ->andWhere('ps.professional = :professionalId')
            ->andWhere('ps.service = :serviceId')
            ->andWhere('ps.' . $dayFields[$dayOfWeek] . ' = :available')
            ->setParameter('professionalId', $professionalId)
            ->setParameter('serviceId', $serviceId)
            ->setParameter('available', true)
            ->getQuery()
            ->getOneOrNullResult(); // Cambio clave: usar getOneOrNullResult() en lugar de getResult()
    }

}