<?php

namespace App\Command;

use App\Entity\Appointment;
use App\Entity\Notification;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:debug-notification-date',
    description: 'Debug notification date vs appointment date'
)]
class DebugNotificationDateCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('appointment_id', InputArgument::OPTIONAL, 'Appointment ID to debug');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $appointmentId = $input->getArgument('appointment_id');

        if ($appointmentId) {
            $this->debugSpecificAppointment($io, $appointmentId);
        } else {
            $this->debugRecentAppointments($io);
        }

        return Command::SUCCESS;
    }

    private function debugSpecificAppointment(SymfonyStyle $io, int $appointmentId): void
    {
        $appointment = $this->entityManager->getRepository(Appointment::class)->find($appointmentId);
        
        if (!$appointment) {
            $io->error("Appointment with ID {$appointmentId} not found");
            return;
        }

        $io->title("Debugging Appointment ID: {$appointmentId}");
        $io->text("Appointment scheduled_at: " . $appointment->getScheduledAt()->format('Y-m-d H:i:s'));
        $io->text("Appointment scheduled_at data: " . $appointment->getScheduledAt()->format('Y-m-d'));
        
    }

    private function debugRecentAppointments(SymfonyStyle $io): void
    {
        $appointments = $this->entityManager->getRepository(Appointment::class)
            ->createQueryBuilder('a')
            ->orderBy('a.id', 'DESC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();

        $io->title("Recent 5 Appointments with Notifications");

        foreach ($appointments as $appointment) {
            $io->section("Appointment ID: " . $appointment->getId());
            $io->text("Scheduled At: " . $appointment->getScheduledAt()->format('Y-m-d H:i:s'));
            
            foreach ($appointment->getNotifications() as $notification) {
                $io->text([
                    "  → Notification {$notification->getId()} ({$notification->getType()->value})",
                    "    Scheduled: " . $notification->getScheduledAt()->format('Y-m-d H:i:s'),
                    "    Status: " . $notification->getStatus()->value
                ]);
            }
            $io->newLine();
        }
    }
}