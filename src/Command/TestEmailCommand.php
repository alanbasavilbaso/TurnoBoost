<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:test-email',
    description: 'Test email sending with SendGrid',
)]
class TestEmailCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'Email address to send test email to');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $emailAddress = $input->getArgument('email');

        try {
            $email = new \SendGrid\Mail\Mail();
            $email->setFrom("turnoboost@gmail.com", "TurnoBoost Test");
            $email->setSubject("Prueba de SendGrid desde TurnoBoost");
            $email->addTo($emailAddress, "Usuario de Prueba");
            $email->addContent("text/plain", "Este es un email de prueba desde TurnoBoost usando SendGrid.");
            $email->addContent(
                "text/html", 
                "<h1>Prueba de SendGrid</h1><p>Este es un <strong>email de prueba</strong> desde TurnoBoost usando SendGrid.</p>"
            );

            $sendgrid = new \SendGrid($_ENV['SENDGRID_API_KEY']);
            
            $response = $sendgrid->send($email);
            
            $io->success(sprintf(
                'Email enviado exitosamente!\nStatus Code: %d\nResponse Body: %s',
                $response->statusCode(),
                $response->body()
            ));
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $io->error('Error al enviar email: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}