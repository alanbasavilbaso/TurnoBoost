<?php

namespace App\Command;

use App\Entity\Appointment;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:test-reminder-creation',
    description: 'Test reminder creation logic'
)]
class TestReminderCreationCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private NotificationService $notificationService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('appointment_id', InputArgument::REQUIRED, 'Appointment ID to test');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $appointmentId = $input->getArgument('appointment_id');

        $appointment = $this->entityManager->getRepository(Appointment::class)->find($appointmentId);
        
        if (!$appointment) {
            $io->error("Appointment with ID {$appointmentId} not found");
            return Command::FAILURE;
        }

        $company = $appointment->getCompany();
        $scheduledAt = $appointment->getScheduledAt();
        
        $io->title("Testing Reminder Creation for Appointment {$appointmentId}");
        
        $io->section("Company Configuration:");
        $io->text([
            "Domain: " . $company->getDomain(),
            "WhatsApp Notifications Enabled: " . ($company->isWhatsappNotificationsEnabled() ? 'YES' : 'NO'),
            "Reminder WhatsApp Enabled: " . ($company->isReminderWhatsappEnabled() ? 'YES' : 'NO'),
            "First Reminder Hours: " . $company->getFirstReminderHoursBeforeAppointment(),
            "Second Reminder Enabled: " . ($company->isSecondReminderEnabled() ? 'YES' : 'NO'),
            "Second Reminder Hours: " . $company->getSecondReminderHoursBeforeAppointment(),
        ]);
        
        $io->section("Appointment Details:");
        $io->text([
            "Scheduled At: " . $scheduledAt->format('Y-m-d H:i:s'),
            "Current Time: " . (new \DateTime())->format('Y-m-d H:i:s'),
        ]);
        
        // Calcular tiempos de recordatorio
        $firstReminderTime = clone $scheduledAt;
        $firstReminderTime->modify('-' . $company->getFirstReminderHoursBeforeAppointment() . ' hours');
        
        $secondReminderTime = clone $scheduledAt;
        $secondReminderTime->modify('-' . $company->getSecondReminderHoursBeforeAppointment() . ' hours');
        
        $io->section("Calculated Reminder Times:");
        $io->text([
            "First Reminder Time: " . $firstReminderTime->format('Y-m-d H:i:s'),
            "Is First Reminder in Future: " . ($firstReminderTime > new \DateTime() ? 'YES' : 'NO'),
            "Second Reminder Time: " . $secondReminderTime->format('Y-m-d H:i:s'),
            "Is Second Reminder in Future: " . ($secondReminderTime > new \DateTime() ? 'YES' : 'NO'),
        ]);
        
        // Verificar condiciones
        $io->section("Reminder Creation Conditions:");
        
        $shouldCreateFirstReminder = ($company->isReminderEmailEnabled() || $company->isReminderWhatsappEnabled()) 
            && $firstReminderTime > new \DateTime() 
            && $company->isReminderWhatsappEnabled();
            
        $shouldCreateSecondReminder = $company->isSecondReminderEnabled() 
            && $company->isReminderWhatsappEnabled() 
            && $secondReminderTime > new \DateTime();
        
        $io->text([
            "Should Create First WhatsApp Reminder: " . ($shouldCreateFirstReminder ? 'YES' : 'NO'),
            "Should Create Second WhatsApp Reminder: " . ($shouldCreateSecondReminder ? 'YES' : 'NO'),
        ]);
        
        return Command::SUCCESS;
    }
}