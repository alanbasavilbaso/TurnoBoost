<?php

namespace App\MessageHandler;

use App\Entity\Notification;
use App\Entity\NotificationStatusEnum;
use App\Message\SendEmailNotification;
use App\Service\EmailService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class SendEmailNotificationHandler
{
    public function __construct(
        private EmailService $emailService,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger
    ) {}

    public function __invoke(SendEmailNotification $message): void
    {
        $notification = $this->entityManager->getRepository(Notification::class)
            ->find($message->getNotificationId());

        if (!$notification) {
            $this->logger->warning('Notification not found', ['id' => $message->getNotificationId()]);
            return;
        }

        try {
            $this->emailService->sendAppointmentNotification(
                $notification->getAppointment(),
                $notification->getType()->value,
                $notification->getId()  // ← Agregar notification_id
            );
            
            $notification->setStatus(NotificationStatusEnum::SENT);
            $notification->setSentAt(new \DateTime());
            $notification->setErrorMessage(null);
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to send email notification', [
                'notification_id' => $notification->getId(),
                'error' => $e->getMessage()
            ]);
            
            $notification->setStatus(NotificationStatusEnum::FAILED);
            $notification->setErrorMessage($e->getMessage());
        }

        $this->entityManager->flush();
    }
}