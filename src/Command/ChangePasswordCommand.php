<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:change-password',
    description: 'Cambia la contraseña de un usuario existente',
)]
class ChangePasswordCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'Email del usuario')
            ->addArgument('new_password', InputArgument::REQUIRED, 'Nueva contraseña')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $email = $input->getArgument('email');
        $newPassword = $input->getArgument('new_password');

        // Buscar el usuario por email
        $userRepository = $this->entityManager->getRepository(User::class);
        $user = $userRepository->findOneBy(['email' => $email]);

        if (!$user) {
            $io->error(sprintf('No se encontró un usuario con el email: %s', $email));
            return Command::FAILURE;
        }

        // Hashear la nueva contraseña
        $hashedPassword = $this->passwordHasher->hashPassword($user, $newPassword);
        
        // Actualizar la contraseña
        $user->setPasswordHash($hashedPassword);
        $user->setUpdatedAt(new \DateTime());

        // Guardar en la base de datos
        $this->entityManager->flush();

        $io->success(sprintf('Contraseña actualizada exitosamente para el usuario: %s', $email));
        
        return Command::SUCCESS;
    }
}