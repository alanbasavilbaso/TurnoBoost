<?php

namespace App\Service;

use App\Entity\Appointment;
use App\Entity\Notification;
use App\Entity\NotificationTypeEnum;
use App\Entity\NotificationStatusEnum;
use App\Entity\StatusEnum;
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
        $company = $appointment->getCompany();
        $scheduledAt = $appointment->getScheduledAt();
        
        // Notificación de confirmación inmediata por email (si está habilitada)
        if ($company->isEmailNotificationsEnabled()) {
            $this->createAndDispatchEmailNotification(
                $appointment, 
                NotificationTypeEnum::CONFIRMATION->value, 
                new \DateTime()
            );
        }
        
        // Notificación de confirmación inmediata por WhatsApp (si está habilitada)
        if ($company->isWhatsappNotificationsEnabled()) {
            $this->createAndDispatchWhatsAppNotification(
                $appointment, 
                NotificationTypeEnum::CONFIRMATION->value, 
                new \DateTime()
            );
        }
        
        // Primer recordatorio (si al menos uno de los canales está habilitado)
        if ($company->isReminderEmailEnabled() || $company->isReminderWhatsappEnabled()) {
            $firstReminderTime = clone $scheduledAt;
            $firstReminderTime->modify('-' . $company->getFirstReminderHoursBeforeAppointment() . ' hours');
            
            // Solo programar recordatorio si es en el futuro
            if ($firstReminderTime > new \DateTime()) {
                // Primer recordatorio por email (si está habilitado)
                if ($company->isReminderEmailEnabled()) {
                    $this->createAndDispatchEmailNotification(
                        $appointment, 
                        NotificationTypeEnum::REMINDER->value, 
                        $firstReminderTime
                    );
                }
                
                // Primer recordatorio por WhatsApp (si está habilitado)
                if ($company->isReminderWhatsappEnabled()) {
                    $this->createAndDispatchWhatsAppNotification(
                        $appointment, 
                        NotificationTypeEnum::REMINDER->value,  
                        $firstReminderTime
                    );
                }
            }
        }
        
        // Segundo recordatorio (solo si está habilitado y WhatsApp está activo)
        if ($company->isSecondReminderEnabled() && $company->isReminderWhatsappEnabled()) {
            $secondReminderTime = clone $scheduledAt;
            $secondReminderTime->modify('-' . $company->getSecondReminderHoursBeforeAppointment() . ' hours');
            
            // Solo programar si es en el futuro
            if ($secondReminderTime > new \DateTime()) {
                $this->createAndDispatchWhatsAppNotification(
                    $appointment, 
                    NotificationTypeEnum::URGENT_REMINDER->value, 
                    $secondReminderTime
                );
            }
        }
    }

    /**
     * Envía notificaciones de confirmación para un nuevo turno
     */
    public function sendAppointmentConfirmationNotification(Appointment $appointment): array
    {
        $company = $appointment->getCompany();
        $results = ['email' => ['sent' => false], 'whatsapp' => ['sent' => false]];
        
        try {
            // Notificación por email (si está habilitada)
            if ($company->isEmailNotificationsEnabled()) {
                $this->createAndDispatchEmailNotification(
                    $appointment, 
                    NotificationTypeEnum::CONFIRMATION->value, 
                    new \DateTime()
                );
                $results['email']['sent'] = true;
            }
            
            // Notificación por WhatsApp (si está habilitada)
            if ($company->isWhatsappNotificationsEnabled()) {
                $this->createAndDispatchWhatsAppNotification(
                    $appointment, 
                    NotificationTypeEnum::CONFIRMATION->value, 
                    new \DateTime()
                );
                $results['whatsapp']['sent'] = true;
            }
            
        } catch (\Exception $e) {
            $this->logger->error('Error sending appointment confirmation notifications', [
                'appointment_id' => $appointment->getId(),
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
        
        return $results;
    }

    /**
     * Envía notificaciones de modificación para un turno actualizado
     */
    public function sendAppointmentModificationNotification(Appointment $appointment): array
    {
        $company = $appointment->getCompany();
        $results = ['email' => ['sent' => false], 'whatsapp' => ['sent' => false]];
        
        try {
            // Notificación por email (si está habilitada)
            if ($company->isEmailNotificationsEnabled()) {
                $this->createAndDispatchEmailNotification(
                    $appointment, 
                    NotificationTypeEnum::MODIFICATION->value, 
                    new \DateTime()
                );
                $results['email']['sent'] = true;
            }
            
            // Notificación por WhatsApp (si está habilitada)
            if ($company->isWhatsappNotificationsEnabled()) {
                $this->createAndDispatchWhatsAppNotification(
                    $appointment, 
                    NotificationTypeEnum::MODIFICATION->value, 
                    new \DateTime()
                );
                $results['whatsapp']['sent'] = true;
            }
            
        } catch (\Exception $e) {
            $this->logger->error('Error sending appointment modification notifications', [
                'appointment_id' => $appointment->getId(),
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
        
        return $results;
    }

    /**
     * Envía notificaciones de cancelación para un turno cancelado
     */
    public function sendAppointmentCancellationNotification(Appointment $appointment): array
    {
        $company = $appointment->getCompany();
        $results = ['email' => ['sent' => false], 'whatsapp' => ['sent' => false]];
        
        try {
            // Notificación por email (si está habilitada)
            if ($company->isEmailNotificationsEnabled()) {
                $this->createAndDispatchEmailNotification(
                    $appointment, 
                    NotificationTypeEnum::CANCELLATION->value, 
                    new \DateTime()
                );
                $results['email']['sent'] = true;
            }
            
            // Notificación por WhatsApp (si está habilitada)
            if ($company->isWhatsappNotificationsEnabled()) {
                $this->createAndDispatchWhatsAppNotification(
                    $appointment, 
                    NotificationTypeEnum::CANCELLATION->value, 
                    new \DateTime()
                );
                $results['whatsapp']['sent'] = true;
            }
            
        } catch (\Exception $e) {
            $this->logger->error('Error sending appointment cancellation notifications', [
                'appointment_id' => $appointment->getId(),
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
        
        return $results;
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
        
        // Solo despachar inmediatamente si es una confirmación o si la fecha ya llegó
        if ($type === NotificationTypeEnum::CONFIRMATION->value || $scheduledAt <= new \DateTime()) {
            $message = new SendEmailNotification($notification->getId(), $type);
            $this->messageBus->dispatch($message);
        }
        // Para reminders futuros, solo se guarda en BD y se procesará con el comando cron
    }

    /**
     * Procesa notificaciones programadas que ya deben enviarse
     */
    public function sendScheduledNotifications(): void
    {
        $now = new \DateTime();
        
        // Buscar notificaciones pendientes cuya fecha de envío ya llegó
        // EXCLUYENDO turnos cancelados y marcando como CANCELLED las de turnos cancelados
        
        // Primero, marcar como CANCELLED las notificaciones de turnos cancelados
        $this->entityManager->createQueryBuilder()
            ->update(Notification::class, 'n')
            ->set('n.status', ':cancelledStatus')
            ->where('n.status = :pendingStatus')
            ->andWhere('n.appointment IS NOT NULL')
            ->andWhere('EXISTS (
                SELECT 1 FROM App\Entity\Appointment a 
                WHERE a.id = n.appointment 
                AND a.status = :appointmentCancelledStatus
            )')
            ->setParameter('cancelledStatus', NotificationStatusEnum::CANCELLED)
            ->setParameter('pendingStatus', NotificationStatusEnum::PENDING)
            ->setParameter('appointmentCancelledStatus', StatusEnum::CANCELLED)
            ->getQuery()
            ->execute();
        
        // Luego, obtener solo notificaciones de turnos activos
        $notifications = $this->entityManager->getRepository(Notification::class)
            ->createQueryBuilder('n')
            ->leftJoin('n.appointment', 'a')
            ->where('n.status = :status')
            ->andWhere('n.scheduledAt <= :now')
            ->andWhere('(n.appointment IS NULL OR a.status != :cancelledStatus)')
            ->setParameter('status', NotificationStatusEnum::PENDING)
            ->setParameter('now', $now)
            ->setParameter('cancelledStatus', StatusEnum::CANCELLED)
            ->getQuery()
            ->getResult();

        foreach ($notifications as $notification) {
            try {
                // Despachar el mensaje para envío inmediato
                if (str_contains($notification->getTemplateUsed(), 'email_')) {
                    $message = new SendEmailNotification(
                        $notification->getId(), 
                        $notification->getType()->value
                    );
                    $this->messageBus->dispatch($message);
                } elseif (str_contains($notification->getTemplateUsed(), 'whatsapp_')) {
                    $message = new SendWhatsAppNotification(
                        $notification->getId(), 
                        $notification->getType()->value
                    );
                    $this->messageBus->dispatch($message);
                }
                
                $this->logger->info('Scheduled notification dispatched', [
                    'notification_id' => $notification->getId(),
                    'type' => $notification->getType()->value,
                    'scheduled_at' => $notification->getScheduledAt()->format('Y-m-d H:i:s')
                ]);
                
            } catch (\Exception $e) {
                $this->logger->error('Failed to dispatch scheduled notification', [
                    'notification_id' => $notification->getId(),
                    'error' => $e->getMessage()
                ]);
                
                $notification->setStatus(NotificationStatusEnum::FAILED);
                $notification->setErrorMessage($e->getMessage());
                $this->entityManager->flush();
            }
        }
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
        $message = new SendWhatsAppNotification($notification->getId());
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

    public function modifyAppointmentNotifications(Appointment $appointment): void
    {
        $company = $appointment->getCompany();
        
        // Enviar notificación de modificación por email (si está habilitada)
        if ($company->isEmailNotificationsEnabled()) {
            $this->createAndDispatchEmailNotification(
                $appointment,
                NotificationTypeEnum::MODIFICATION->value,
                new \DateTime()
            );
        }
    
        // Enviar notificación de modificación por WhatsApp (si está habilitada)
        if ($company->isWhatsappNotificationsEnabled()) {
            $this->createAndDispatchWhatsAppNotification(
                $appointment,
                NotificationTypeEnum::MODIFICATION->value,
                new \DateTime()
            );
        }
    }
}