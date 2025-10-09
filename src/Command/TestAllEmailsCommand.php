<?php

namespace App\Command;

use App\Service\EmailService;
use App\Service\BrevoEmailService;
use App\Entity\Appointment;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:test-all-emails',
    description: 'Test all email types with Brevo integration'
)]
class TestAllEmailsCommand extends Command
{
    private const EMAIL_TYPES = [
        'confirmation' => 'Confirmación de cita',
        'reminder' => 'Recordatorio de cita',
        'urgent_reminder' => 'Recordatorio urgente',
        'cancellation' => 'Cancelación de cita',
        'modification' => 'Modificación de cita'
    ];

    public function __construct(
        private EmailService $emailService,
        private BrevoEmailService $brevoEmailService,
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('appointment-id', InputArgument::REQUIRED, 'Appointment ID to test')
            ->addOption('type', 't', InputOption::VALUE_OPTIONAL, 'Specific email type to test (confirmation, reminder, urgent_reminder, cancellation, modification)')
            ->addOption('all', 'a', InputOption::VALUE_NONE, 'Test all email types')
            ->addOption('custom', 'c', InputOption::VALUE_NONE, 'Test custom email functionality using BrevoEmailService.sendEmail()')
            ->addOption('delay', 'd', InputOption::VALUE_OPTIONAL, 'Delay between emails in seconds (when testing all)', 2);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $appointmentId = $input->getArgument('appointment-id');
        $specificType = $input->getOption('type');
        $testAll = $input->getOption('all');
        $testCustom = $input->getOption('custom');
        $delay = (int) $input->getOption('delay');

        // Validar appointment
        $appointment = $this->entityManager->getRepository(Appointment::class)->find($appointmentId);
        
        if (!$appointment) {
            $io->error("Appointment with ID {$appointmentId} not found");
            return Command::FAILURE;
        }

        // Mostrar información del appointment
        $this->displayAppointmentInfo($io, $appointment);

        // Determinar qué emails enviar
        if ($testCustom) {
            return $this->testCustomEmail($io, $appointment);
        } elseif ($specificType) {
            if (!array_key_exists($specificType, self::EMAIL_TYPES)) {
                $io->error("Invalid email type. Available types: " . implode(', ', array_keys(self::EMAIL_TYPES)));
                return Command::FAILURE;
            }
            return $this->testSingleEmail($io, $appointment, $specificType);
        } elseif ($testAll) {
            return $this->testAllEmails($io, $appointment, $delay);
        } else {
            // Por defecto, probar solo confirmation
            return $this->testSingleEmail($io, $appointment, 'confirmation');
        }
    }

    private function displayAppointmentInfo(SymfonyStyle $io, Appointment $appointment): void
    {
        $patient = $appointment->getPatient();
        $professional = $appointment->getProfessional();
        $service = $appointment->getService();
        $company = $appointment->getCompany();

        $io->section('Información del Appointment');
        $io->table(
            ['Campo', 'Valor'],
            [
                ['ID', $appointment->getId()],
                ['Paciente', $patient->getFirstName() . ' ' . $patient->getLastName()],
                ['Email', $patient->getEmail()],
                ['Teléfono', $patient->getPhone()],
                ['Profesional', $professional->getName()],
                ['Servicio', $service->getName()],
                ['Empresa', $company->getName()],
                ['Fecha/Hora', $appointment->getScheduledAt()->format('d/m/Y H:i')],
                ['Estado', $appointment->getStatus()->value],
            ]
        );
    }

    private function testSingleEmail(SymfonyStyle $io, Appointment $appointment, string $type): int
    {
        $io->section("Probando email: " . self::EMAIL_TYPES[$type]);
        
        try {
            $io->info("Enviando email de tipo '{$type}' usando EmailService...");
            
            if ($type === 'confirmation') {
                $this->emailService->sendAppointmentConfirmation($appointment);
            } else {
                $this->emailService->sendAppointmentNotification($appointment, $type);
            }
            
            $io->success("✅ Email '{$type}' enviado exitosamente!");
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $io->error("❌ Error enviando email '{$type}': " . $e->getMessage());
            $io->note("Stack trace: " . $e->getTraceAsString());
            return Command::FAILURE;
        }
    }

    private function testAllEmails(SymfonyStyle $io, Appointment $appointment, int $delay): int
    {
        $io->section("Probando todos los tipos de email");
        $io->note("Delay entre emails: {$delay} segundos");
        
        $results = [];
        $totalSuccess = 0;
        $totalFailed = 0;

        foreach (self::EMAIL_TYPES as $type => $description) {
            $io->info("Enviando: {$description} ({$type})");
            
            try {
                if ($type === 'confirmation') {
                    $this->emailService->sendAppointmentConfirmation($appointment);
                } else {
                    $this->emailService->sendAppointmentNotification($appointment, $type);
                }
                
                $results[] = ['✅', $type, $description, 'SUCCESS'];
                $totalSuccess++;
                $io->success("✅ {$description} enviado");
                
            } catch (\Exception $e) {
                $results[] = ['❌', $type, $description, 'FAILED: ' . $e->getMessage()];
                $totalFailed++;
                $io->error("❌ Error en {$description}: " . $e->getMessage());
            }

            // Delay entre emails (excepto el último)
            if ($delay > 0 && $type !== array_key_last(self::EMAIL_TYPES)) {
                $io->note("Esperando {$delay} segundos...");
                sleep($delay);
            }
        }

        // Mostrar resumen
        $io->section('Resumen de Resultados');
        $io->table(
            ['Estado', 'Tipo', 'Descripción', 'Resultado'],
            $results
        );

        $io->info("Total exitosos: {$totalSuccess}");
        $io->info("Total fallidos: {$totalFailed}");

        return $totalFailed === 0 ? Command::SUCCESS : Command::FAILURE;
    }

    private function testCustomEmail(SymfonyStyle $io, Appointment $appointment): int
    {
        $io->section("Probando funcionalidad de email personalizado");
        $io->note("Usando BrevoEmailService.sendEmail() directamente");
        
        $patient = $appointment->getPatient();
        $toEmail = $patient->getEmail();
        $subject = "Email de prueba personalizado - " . date('Y-m-d H:i:s');
        $htmlContent = "
            <h1>Email de Prueba Personalizado</h1>
            <p>Hola {$patient->getFirstName()},</p>
            <p>Este es un email de prueba enviado usando el método <code>sendEmail()</code> de BrevoEmailService.</p>
            <p><strong>Detalles del test:</strong></p>
            <ul>
                <li>Appointment ID: {$appointment->getId()}</li>
                <li>Fecha de envío: " . date('Y-m-d H:i:s') . "</li>
                <li>Método usado: BrevoEmailService::sendEmail()</li>
            </ul>
            <hr>
            <small>Este email fue enviado desde el comando de test: <code>app:test-all-emails --custom</code></small>
        ";

        try {
            $io->info("Enviando email personalizado a: {$toEmail}");
            
            $this->brevoEmailService->sendEmail($toEmail, $subject, $htmlContent);
            
            $io->success("✅ Email personalizado enviado exitosamente!");
            $io->table(
                ['Campo', 'Valor'],
                [
                    ['Para', $toEmail],
                    ['Asunto', $subject],
                    ['Método usado', 'BrevoEmailService::sendEmail()'],
                    ['Tipo', 'Email personalizado (HTML directo)']
                ]
            );
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $io->error("❌ Error enviando email personalizado: " . $e->getMessage());
            $io->note("Stack trace: " . $e->getTraceAsString());
            return Command::FAILURE;
        }
    }
}