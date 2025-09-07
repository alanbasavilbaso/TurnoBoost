<?php

namespace App\Command;

use App\Entity\User;
use App\Entity\Company;
use App\Entity\Settings;
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
    description: 'Crea un usuario de prueba en la base de datos con su company y settings',
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
            ->addArgument('company_name', InputArgument::OPTIONAL, 'Nombre de la empresa', null)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $name = $input->getArgument('name');
        $email = $input->getArgument('email');
        $password = $input->getArgument('password');
        $roleString = strtolower($input->getArgument('role'));
        $companyName = $input->getArgument('company_name');
    
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

        // Generar nombre de empresa si no se proporciona
        if (!$companyName) {
            $companyNames = [
                'Clínica Salud Total',
                'Centro Médico Bienestar',
                'Consultorio Vida Sana',
                'Clínica Esperanza',
                'Centro de Salud Integral',
                'Clínica San Rafael',
                'Consultorio Médico Aurora',
                'Centro Wellness',
                'Clínica Nueva Vida',
                'Consultorio Salud Plus'
            ];
            $companyName = $companyNames[array_rand($companyNames)];
        }


        // 1º - Crear la empresa con el usuario ya persistido
        $company = new Company();
        $company->setName($companyName);
        $company->setDescription('Empresa creada automáticamente para ' . $name);
        $company->setActive(true);

        $this->entityManager->persist($company);
        $this->entityManager->flush();


        // 2º - Crear el usuario
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

        $user->setCompany($company);
        $user->setIsOwner(true);
        // PRIMERO: Persistir y hacer flush del usuario para obtener su ID
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        
        
        // Crear configuraciones por defecto
        $settings = new Settings();
        $settings->setCompany($company);
        $settings->setMinimumBookingTime(60); // 1 hora mínimo
        $settings->setMaximumFutureTime(6);   // 6 meses máximo

        // Establecer la relación bidireccional
        $company->setSettings($settings);

        $this->entityManager->persist($settings);
        $this->entityManager->flush();

        $io->success('Usuario, empresa y configuraciones creados exitosamente:');
        $io->table(
            ['Campo', 'Valor'],
            [
                ['Usuario ID', $user->getId()],
                ['Nombre', $user->getName()],
                ['Email', $user->getEmail()],
                ['Rol', $user->getRole()->value],
                ['Empresa ID', $company->getId()],
                ['Empresa', $company->getName()],
                ['Settings ID', $settings->getId()],
                ['Tiempo mín. reserva', $settings->getMinimumBookingTime() . ' minutos'],
                ['Tiempo máx. futuro', $settings->getMaximumFutureTime() . ' meses'],
                ['Creado', $user->getCreatedAt()->format('Y-m-d H:i:s')],
            ]
        );
        
        $io->note(sprintf('Puedes iniciar sesión con: %s / %s', $email, $password));
        $io->note(sprintf('Empresa creada: %s', $companyName));

        return Command::SUCCESS;
    }
}