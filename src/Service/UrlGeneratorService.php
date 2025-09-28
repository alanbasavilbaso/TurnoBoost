<?php

namespace App\Service;

use App\Entity\Appointment;

class UrlGeneratorService
{
    /**
     * Generar token seguro para una cita
     */
    public function generateAppointmentToken(Appointment $appointment): string
    {
        $data = $appointment->getId() . 
                $appointment->getPatient()->getEmail() . 
                $appointment->getScheduledAt()->format('Y-m-d H:i:s') .
                $appointment->getCompany()->getId();
        
        return hash('sha256', $data . ($_ENV['APP_SECRET'] ?? 'default_secret'));
    }

    /**
     * Generar URL de confirmación
     */
    public function generateConfirmUrl(Appointment $appointment): string
    {
        $domain = $appointment->getCompany()->getDomain();
        $token = $this->generateAppointmentToken($appointment);
        $baseUrl = $_ENV['APP_URL'] ?? 'https://turnoboost.com';
        return $baseUrl . "/{$domain}/confirm/{$appointment->getId()}/{$token}";
    }

    /**
     * Generar URL de cancelación
     */
    public function generateCancelUrl(Appointment $appointment): string
    {
        $domain = $appointment->getCompany()->getDomain();
        $token = $this->generateAppointmentToken($appointment);
        $baseUrl = $_ENV['APP_URL'] ?? 'https://turnoboost.com';
        return $baseUrl . "/{$domain}/cancel/{$appointment->getId()}/{$token}";
    }

}