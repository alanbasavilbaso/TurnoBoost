<?php

namespace App\Command;

use App\Service\WhatsAppService;
use App\Entity\Appointment;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:test-whatsapp',
    description: 'Test WhatsApp service integration'
)]
class TestWhatsAppCommand extends Command
{
    private WhatsAppService $whatsappService;
    private EntityManagerInterface $entityManager;

    public function __construct(
        WhatsAppService $whatsappService,
        EntityManagerInterface $entityManager
    ) {
        $this->whatsappService = $whatsappService;
        $this->entityManager = $entityManager;
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Testing WhatsApp Service');

        // Test service health
        $io->section('Checking WhatsApp Service Health');
        $health = $this->whatsappService->checkServiceHealth();
        
        if ($health['status'] === 'ok' && $health['connected']) {
            $io->success('WhatsApp service is healthy and connected');
        } else {
            $io->error('WhatsApp service is not available or not connected');
            $io->table(['Status', 'Connected'], [[$health['status'], $health['connected'] ? 'Yes' : 'No']]);
            return Command::FAILURE;
        }

        // Test with a real appointment
        $io->section('Testing with Sample Appointment');
        
        $appointmentRepository = $this->entityManager->getRepository(Appointment::class);
        $appointment = $appointmentRepository->findOneBy([], ['id' => 'DESC']);
        
        if (!$appointment) {
            $io->warning('No appointments found in database. Create an appointment first.');
            return Command::SUCCESS;
        }

        $io->info(sprintf('Using appointment ID: %d', $appointment->getId()));
        $io->info(sprintf('Patient: %s', $appointment->getPatient()->getName()));
        $io->info(sprintf('Phone: %s', $appointment->getPatient()->getPhone()));
        
        // Send test message
        $success = $this->whatsappService->sendAppointmentNotification(
            $appointment,
            'CONFIRMATION'
        );

        if ($success) {
            $io->success('WhatsApp test message sent successfully!');
        } else {
            $io->error('Failed to send WhatsApp test message');
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}