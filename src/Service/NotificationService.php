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
        if (($company->isReminderEmailEnabled() && $company->isEmailNotificationsEnabled()) || 
            ($company->isReminderWhatsappEnabled() && $company->isWhatsappNotificationsEnabled())) {
            $firstReminderTime = clone $scheduledAt;
            $firstReminderTime->modify('-' . $company->getFirstReminderHoursBeforeAppointment() . ' hours');
            
            // Solo programar recordatorio si es en el futuro
            if ($firstReminderTime > new \DateTime()) {
                // Primer recordatorio por email (si está habilitado Y las notificaciones email están habilitadas)
                if ($company->isReminderEmailEnabled() && $company->isEmailNotificationsEnabled()) {
                    $this->createAndDispatchEmailNotification(
                        $appointment, 
                        NotificationTypeEnum::REMINDER->value, 
                        $firstReminderTime
                    );
                }
                
                // Primer recordatorio por WhatsApp (si está habilitado Y las notificaciones WhatsApp están habilitadas)
                if ($company->isReminderWhatsappEnabled() && $company->isWhatsappNotificationsEnabled()) {
                    $this->createAndDispatchWhatsAppNotification(
                        $appointment, 
                        NotificationTypeEnum::REMINDER->value,  
                        $firstReminderTime
                    );
                }
            }
        }
        
        // Segundo recordatorio
        if ($company->isSecondReminderEnabled()) {
            $secondReminderTime = clone $scheduledAt;
            $secondReminderTime->modify('-' . $company->getSecondReminderHoursBeforeAppointment() . ' hours');
            
            // Solo programar si es en el futuro
            if ($secondReminderTime > new \DateTime()) {
                // Segundo recordatorio por email (si está habilitado)
                if ($company->isReminderEmailEnabled() && $company->isEmailNotificationsEnabled()) {
                    $this->createAndDispatchEmailNotification(
                        $appointment, 
                        NotificationTypeEnum::URGENT_REMINDER->value, 
                        $secondReminderTime
                    );
                }
                
                // Segundo recordatorio por WhatsApp (si está habilitado)
                if ($company->isReminderWhatsappEnabled() && $company->isWhatsappNotificationsEnabled()) {
                    $this->createAndDispatchWhatsAppNotification(
                        $appointment, 
                        NotificationTypeEnum::URGENT_REMINDER->value, 
                        $secondReminderTime
                    );
                }
            }
        }
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
        
        // Luego, obtener solo notificaciones de turnos activos, excluyendo notificaciones de empresa o de usuario
        $notifications = $this->entityManager->getRepository(Notification::class)
            ->createQueryBuilder('n')
            ->leftJoin('n.appointment', 'a')
            ->where('n.status = :status')
            ->andWhere('n.scheduledAt <= :now')
            ->andWhere('(n.appointment IS NULL OR a.status != :cancelledStatus)')
            ->andWhere('n.type NOT IN (:companyTypes)')
            ->setParameter('status', NotificationStatusEnum::PENDING)
            ->setParameter('now', $now)
            ->setParameter('cancelledStatus', StatusEnum::CANCELLED)
            ->setParameter('companyTypes', [
                NotificationTypeEnum::COMPANY_NEW_BOOKING->value,
                NotificationTypeEnum::COMPANY_CANCELLATION->value
            ])
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
        
        // Solo despachar inmediatamente si es una confirmación o si la fecha ya llegó
        if ($type === NotificationTypeEnum::CONFIRMATION->value || $scheduledAt <= new \DateTime()) {
            $message = new SendWhatsAppNotification($notification->getId(), $type);
            $this->messageBus->dispatch($message);
        }
        // Para reminders futuros, solo se guarda en BD y se procesará con el comando cron
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

    /**
     * Envía notificación asíncrona a la empresa sobre nuevos turnos
     */
    public function sendCompanyNotification(Appointment $appointment, string $type): void
    {
        $company = $appointment->getCompany();
        
        // Verificar si la empresa quiere recibir notificaciones por email
        if (!$company->getReceiveEmailNotifications()) {
            return;
        }

        // Crear y despachar notificación de empresa de forma asíncrona
        $this->createAndDispatchEmailNotification(
            $appointment,
            $type,
            new \DateTime()
        );
    }
}