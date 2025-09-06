<?php

namespace App\Service;

use App\Entity\Appointment;
use App\Entity\Notification;
use App\Entity\NotificationTypeEnum;
use App\Entity\NotificationStatusEnum;
use App\Message\SendEmailNotification;
use App\Message\SendWhatsAppNotification;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Psr\Log\LoggerInterface;

class NotificationService
{
    private EntityManagerInterface $entityManager;
    private MessageBusInterface $messageBus;
    private LoggerInterface $logger;

    public function __construct(
        EntityManagerInterface $entityManager,
        MessageBusInterface $messageBus,
        LoggerInterface $logger
    ) {
        $this->entityManager = $entityManager;
        $this->messageBus = $messageBus;
        $this->logger = $logger;
    }

    public function scheduleAppointmentNotifications(Appointment $appointment): void
    {
        // Programar notificaciones por email
        $this->scheduleEmailNotifications($appointment);
        
        // Programar notificaciones por WhatsApp
        $this->scheduleWhatsAppNotifications($appointment);
        
        $this->logger->info('Notifications scheduled for appointment', [
            'appointment_id' => $appointment->getId()
        ]);
    }

    private function scheduleEmailNotifications(Appointment $appointment): void
    {
        $scheduledAt = $appointment->getScheduledAt();
        
        // Confirmación inmediata por email
        $this->createAndDispatchEmailNotification(
            $appointment, 
            NotificationTypeEnum::CONFIRMATION->value, 
            new \DateTime()
        );
        
        // Recordatorio 24 horas antes por email
        $reminderTime = (clone $scheduledAt)->modify('-1 day');
        if ($reminderTime > new \DateTime()) {
            $this->createAndDispatchEmailNotification(
                $appointment, 
                NotificationTypeEnum::REMINDER->value,
                $reminderTime
            );
        }
    }

    private function scheduleWhatsAppNotifications(Appointment $appointment): void
    {
        $scheduledAt = $appointment->getScheduledAt();
        
        // Confirmación inmediata por WhatsApp
        $this->createAndDispatchWhatsAppNotification(
            $appointment, 
            NotificationTypeEnum::CONFIRMATION->value, 
            new \DateTime()
        );
        
        // Recordatorio 24 horas antes por WhatsApp
        $reminderTime = (clone $scheduledAt)->modify('-1 day');
        if ($reminderTime > new \DateTime()) {
            $this->createAndDispatchWhatsAppNotification(
                $appointment, 
                NotificationTypeEnum::REMINDER->value,  
                $reminderTime
            );
        }
        
        // Recordatorio urgente 2 horas antes por WhatsApp
        $urgentReminderTime = (clone $scheduledAt)->modify('-2 hours');
        if ($urgentReminderTime > new \DateTime()) {
            $this->createAndDispatchWhatsAppNotification(
                $appointment, 
                NotificationTypeEnum::URGENT_REMINDER->value, 
                $urgentReminderTime
            );
        }
    }

    private function createAndDispatchEmailNotification(
        Appointment $appointment, 
        string $type, 
        \DateTime $scheduledAt
    ): void {
        // Crear notificación en BD
        $notification = new Notification();
        $notification->setAppointment($appointment);
        $notification->setType(NotificationTypeEnum::from($type));
        $notification->setScheduledAt($scheduledAt);
        $notification->setStatus(NotificationStatusEnum::PENDING);
        $notification->setTemplateUsed('email_' . strtolower($type));
        
        $this->entityManager->persist($notification);
        $this->entityManager->flush();
        
        // Despachar mensaje a la cola
        $message = new SendEmailNotification($notification->getId(), $type);
        $this->messageBus->dispatch($message);
    }

    private function createAndDispatchWhatsAppNotification(
        Appointment $appointment, 
        string $type, 
        \DateTime $scheduledAt
    ): void {
        // Crear notificación en BD
        $notification = new Notification();
        $notification->setAppointment($appointment);
        $notification->setType(NotificationTypeEnum::from($type));
        $notification->setScheduledAt($scheduledAt);
        $notification->setStatus(NotificationStatusEnum::PENDING);
        $notification->setTemplateUsed('whatsapp_' . strtolower($type));
        
        $this->entityManager->persist($notification);
        $this->entityManager->flush();
        
        // Despachar mensaje a la cola
        $message = new SendWhatsAppNotification($notification->getId(), $type);
        $this->messageBus->dispatch($message);
    }

    public function cancelAppointmentNotifications(Appointment $appointment): void
    {
        // Enviar notificación de cancelación por email
        $this->createAndDispatchEmailNotification(
            $appointment,
            NotificationTypeEnum::CANCELLATION->value,
            new \DateTime()
        );

        // Enviar notificación de cancelación por WhatsApp
        $this->createAndDispatchWhatsAppNotification(
            $appointment,
            NotificationTypeEnum::CANCELLATION->value,
            new \DateTime()
        );
    }
}