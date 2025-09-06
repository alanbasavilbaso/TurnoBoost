<?php

namespace App\Service;

use App\Entity\Appointment;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;

class EmailService
{
    public function __construct(
        private MailerInterface $mailer,
        private Environment $twig
    ) {}

    public function sendAppointmentConfirmation(Appointment $appointment): void
    {
        $patient = $appointment->getPatient();
        $professional = $appointment->getProfessional();
        $service = $appointment->getService();
        $location = $professional->getLocation();
        
        $htmlContent = $this->twig->render('emails/appointment_confirmation.html.twig', [
            'business_name' => $location->getName(),
            'service_name' => $service->getName(),
            'appointment_date' => $appointment->getScheduledAt()->format('d/m/Y'),
            'appointment_time' => $appointment->getScheduledAt()->format('H:i'),
            'professional_name' => $professional->getName(),
            'location_name' => $location->getName(),
            'location_address' => $location->getAddress(),
            'patient_name' => $patient->getName(),
            'phone_number' => $location->getPhone() ?? '+54 11 1234-5678',
            'reschedule_url' => '#', // TODO: Implementar URL de reprogramación
            'cancel_url' => '#', // TODO: Implementar URL de cancelación
        ]);
        
        // Usar variables de entorno para from y to
        $fromAddress = $_ENV['MAIL_FROM_ADDRESS'] ?? 'noreply@turnoboost.com';
        $toAddress = $_ENV['MAIL_TO_OVERRIDE'] ?? $patient->getEmail();
        
        $email = (new Email())
            ->from($fromAddress)
            ->to($toAddress)
            ->subject('Confirmación de tu cita - ' . $service->getName())
            ->html($htmlContent);
            
        $this->mailer->send($email);
    }

    public function sendAppointmentNotification(Appointment $appointment, string $type): void
    {
        // Mantener el método existente para compatibilidad
        if ($type === 'confirmation') {
            $this->sendAppointmentConfirmation($appointment);
            return;
        }
        
        // Resto del código existente para otros tipos de notificación
        $patient = $appointment->getPatient();
        $professional = $appointment->getProfessional();
        $service = $appointment->getService();
        
        $subject = $this->getSubjectForType($type);
        $template = $this->getTemplateForType($type);
        
        $htmlContent = $this->twig->render($template, [
            'appointment' => $appointment,
            'patient' => $patient,
            'professional' => $professional,
            'service' => $service,
            'type' => $type
        ]);
        
        // Usar variables de entorno para from y to
        $fromAddress = $_ENV['MAIL_FROM_ADDRESS'] ?? 'noreply@turnoboost.com';
        $toAddress = $_ENV['MAIL_TO_OVERRIDE'] ?? $patient->getEmail();
        
        $email = (new Email())
            ->from($fromAddress)
            ->to($toAddress)
            ->subject($subject)
            ->html($htmlContent);
            
        $this->mailer->send($email);
    }

    private function getSubjectForType(string $type): string
    {
        return match($type) {
            'confirmation' => 'Confirmación de tu cita',
            'reminder' => 'Recordatorio de tu cita',
            'urgent_reminder' => 'Recordatorio urgente de tu cita',
            'cancellation' => 'Cancelación de tu cita',
            default => 'Notificación de cita'
        };
    }
    
    private function getTemplateForType(string $type): string
    {
        return match($type) {
            'confirmation' => 'emails/appointment_confirmation.html.twig',
            'reminder' => 'emails/appointment_reminder.html.twig',
            'urgent_reminder' => 'emails/appointment_urgent_reminder.html.twig',
            'cancellation' => 'emails/appointment_cancellation.html.twig',
            default => 'emails/appointment_confirmation.html.twig'
        };
    }
}