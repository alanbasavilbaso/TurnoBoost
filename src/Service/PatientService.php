<?php

namespace App\Service;

use App\Entity\Patient;
use App\Entity\Company;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\AppointmentSourceEnum;

class PatientService
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function findOrCreatePatient(array $patientData, Company $company, AppointmentSourceEnum $source = AppointmentSourceEnum::USER): Patient
    {
        // Limpiar y validar el teléfono si está presente
        if (isset($patientData['phone']) && !empty($patientData['phone'])) {
            $cleanPhone = $this->cleanAndValidatePhone($patientData['phone']);
            if ($cleanPhone === null) {
                throw new \InvalidArgumentException('El teléfono no es válido. Debe contener entre 8 y 10 dígitos');
            }
            $patientData['phone'] = $cleanPhone;
        }

        if (isset($patientData['id']) && $patientData['id']) {
            $patient = $this->entityManager->getRepository(Patient::class)->find($patientData['id']);
            if ($patient && $patient->getCompany() === $company && !$patient->isDeleted()) {
                // Actualizar los datos del paciente con la información proporcionada
                if (isset($patientData['email']) && !empty($patientData['email'])) {
                    $patient->setEmail($patientData['email']);
                }
                if (isset($patientData['phone']) && !empty($patientData['phone'])) {
                    $patient->setPhone($patientData['phone']);
                }
                if (isset($patientData['birth_date']) && !empty($patientData['birth_date'])) {
                    $patient->setBirthDate($patientData['birth_date']);
                }
                
                return $patient;
            }
        }
        
        // Buscar paciente existente por email o teléfono (solo pacientes no eliminados)
        if ((isset($patientData['email']) && !empty($patientData['email'])) ||
             (isset($patientData['phone']) && !empty($patientData['phone'])) ) {
            $qb = $this->entityManager->getRepository(Patient::class)->createQueryBuilder('p')
                ->where('p.company = :company')
                ->andWhere('p.deletedAt IS NULL') // Solo pacientes no eliminados
                ->setParameter('company', $company);
            
            if (isset($patientData['email']) && !empty($patientData['email']) && 
                isset($patientData['phone']) && !empty($patientData['phone'])) {
                // Si tenemos ambos, buscar por ambos campos
                $qb->andWhere('p.email = :email AND p.phone = :phone')
                   ->setParameter('email', $patientData['email'])
                   ->setParameter('phone', $patientData['phone']);
            } elseif (isset($patientData['email']) && !empty($patientData['email'])) {
                // Solo email
                $qb->andWhere('p.email = :email')
                   ->setParameter('email', $patientData['email']);
            } elseif (isset($patientData['phone']) && !empty($patientData['phone'])) {
                // Solo teléfono
                $qb->andWhere('p.phone = :phone')
                   ->setParameter('phone', $patientData['phone']);
            }
            
            $existingPatient = $qb->getQuery()->getOneOrNullResult();
                    
            if ($existingPatient) {
                return $existingPatient;
            }
        }

        // Crear nuevo paciente solo si no existe
        $patient = new Patient();
        $patient->setCompany($company)
                ->setFirstName($patientData['first_name'] ?? null)
                ->setLastName($patientData['last_name'] ?? null)
                ->setEmail($patientData['email'] ?? null)
                ->setPhone($patientData['phone'] ?? null);
                
        $this->entityManager->persist($patient);
        
        return $patient;
    }

    /**
     * Limpia y valida un número de teléfono argentino
     * 
     * @param string $phone El número de teléfono a limpiar
     * @return string|null El teléfono limpio en formato +549XXXXXXXXX o null si es inválido
     */
    private function cleanAndValidatePhone(string $phone): ?string
    {
        // Limpiar el teléfono removiendo caracteres no numéricos excepto el +
        $cleanPhone = preg_replace('/[^\d+]/', '', $phone);
        
        // Asegurar que el número empiece con +549
        // Primero removemos cualquier prefijo existente
        $cleanPhone = preg_replace('/^\+?54?9?/', '', $cleanPhone);
        
        // Ahora agregamos el prefijo correcto
        $cleanPhone = '+549' . $cleanPhone;

        // Validar formato de teléfono argentino
        // Debe empezar con +549 y tener entre 8 y 10 dígitos después del código
        if (!preg_match('/^\+549[0-9]{8,10}$/', $cleanPhone)) {
            return null;
        }
        
        return $cleanPhone;
    }

    /**
     * Soft delete a patient
     */
    public function deletePatient(Patient $patient): void
    {
        $patient->delete();
        $this->entityManager->flush();
    }

    /**
     * Restore a soft deleted patient
     */
    public function restorePatient(Patient $patient): void
    {
        $patient->restore();
        $this->entityManager->flush();
    }

    /**
     * Get all active (non-deleted) patients for a company
     */
    public function getActivePatients(Company $company): array
    {
        return $this->entityManager->getRepository(Patient::class)
            ->createQueryBuilder('p')
            ->where('p.company = :company')
            ->andWhere('p.deletedAt IS NULL')
            ->setParameter('company', $company)
            ->orderBy('p.firstName', 'ASC')
            ->addOrderBy('p.lastName', 'ASC')
            ->getQuery()
            ->getResult();
    }
}