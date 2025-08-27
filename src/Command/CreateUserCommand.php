<?php

namespace App\Command;

use App\Entity\User;
use App\Entity\RoleEnum;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-user',
    description: 'Crea un usuario de prueba en la base de datos',
)]
class CreateUserCommand extends Command
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
            ->addArgument('name', InputArgument::OPTIONAL, 'Nombre del usuario', 'Admin Test')
            ->addArgument('email', InputArgument::OPTIONAL, 'Email del usuario', 'admin@turnoboost.com')
            ->addArgument('password', InputArgument::OPTIONAL, 'Contraseña del usuario', 'admin123')
            ->addArgument('role', InputArgument::OPTIONAL, 'Rol del usuario (admin, profesional, recepcionista, paciente)', 'admin')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $name = $input->getArgument('name');
        $email = $input->getArgument('email');
        $password = $input->getArgument('password');
        $roleString = strtolower($input->getArgument('role')); // Cambiar a minúsculas
    
        // Verificar si el usuario ya existe
        $existingUser = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
        if ($existingUser) {
            $io->error(sprintf('Ya existe un usuario con el email: %s', $email));
            return Command::FAILURE;
        }
    
        // Validar el rol
        try {
            $role = RoleEnum::from($roleString);
        } catch (\ValueError $e) {
            $io->error(sprintf('Rol inválido: %s. Roles válidos: admin, profesional, recepcionista, paciente', $roleString));
            return Command::FAILURE;
        }

        // Crear el usuario
        $user = new User();
        $user->setName($name);
        $user->setEmail($email);
        $user->setRole($role);
        
        // Hash de la contraseña
        $hashedPassword = $this->passwordHasher->hashPassword($user, $password);
        $user->setPasswordHash($hashedPassword);
        
        // Establecer fechas
        $now = new \DateTimeImmutable();
        $user->setCreatedAt($now);
        $user->setUpdatedAt($now);

        // Guardar en la base de datos
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $io->success(sprintf('Usuario creado exitosamente:'));
        $io->table(
            ['Campo', 'Valor'],
            [
                ['ID', $user->getId()],
                ['Nombre', $user->getName()],
                ['Email', $user->getEmail()],
                ['Rol', $user->getRole()->value],
                ['Creado', $user->getCreatedAt()->format('Y-m-d H:i:s')],
            ]
        );
        
        $io->note(sprintf('Puedes iniciar sesión con: %s / %s', $email, $password));

        return Command::SUCCESS;
    }
}