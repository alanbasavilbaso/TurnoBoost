<?php

namespace App\Service;

use App\Entity\Settings;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class SettingsService
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * Obtiene la configuración del usuario, creando una por defecto si no existe
     */
    public function getUserSettings(User $user): Settings
    {
        $settings = $this->entityManager->getRepository(Settings::class)
            ->findOneBy(['user' => $user]);

        if (!$settings) {
            $settings = new Settings();
            $settings->setUser($user);
            $this->entityManager->persist($settings);
            $this->entityManager->flush();
        }

        return $settings;
    }

    /**
     * Guarda la configuración del usuario
     */
    public function saveSettings(Settings $settings): void
    {
        $this->entityManager->persist($settings);
        $this->entityManager->flush();
    }

    /**
     * Valida si una fecha de cita es válida según la configuración del usuario
     */
    public function validateAppointmentDate(User $user, \DateTimeInterface $appointmentDate): array
    {
        $settings = $this->getUserSettings($user);
        $errors = [];

        if (!$settings->isWithinMinimumTime($appointmentDate)) {
            $errors[] = sprintf(
                'La cita debe ser programada con al menos %d minutos de anticipación.',
                $settings->getMinimumBookingTime()
            );
        }

        if (!$settings->isWithinMaximumTime($appointmentDate)) {
            $errors[] = sprintf(
                'La cita no puede ser programada para más de %d meses en el futuro.',
                $settings->getMaximumFutureTime()
            );
        }

        return $errors;
    }
}