<?php

namespace App\Command;

use App\Service\NotificationService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:process-notifications',
    description: 'Process scheduled notifications'
)]
class ProcessScheduledNotificationsCommand extends Command
{
    public function __construct(
        private NotificationService $notificationService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->notificationService->sendScheduledNotifications();
        $output->writeln('Scheduled notifications processed');
        
        return Command::SUCCESS;
    }
}