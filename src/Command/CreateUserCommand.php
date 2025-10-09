<?php

namespace App\Command;

use App\Entity\User;
use App\Entity\Company;
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
    description: 'Crea un usuario de prueba en la base de datos con su company',
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

        // Validar rol
        $role = match($roleString) {
            'super' => RoleEnum::SUPER,
            'admin' => RoleEnum::ADMIN,
            'profesional' => RoleEnum::PROFESIONAL,
            'recepcionista' => RoleEnum::RECEPCIONISTA,
            'paciente' => RoleEnum::PACIENTE,
            default => throw new \InvalidArgumentException('Rol inválido. Usa: super, admin, profesional, recepcionista, paciente')
        };

        // Verificar si el usuario ya existe
        $existingUser = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
        if ($existingUser) {
            $io->error('Ya existe un usuario con ese email.');
            return Command::FAILURE;
        }

        // Crear empresa si no se proporciona nombre
        if (!$companyName) {
            $companyName = 'Empresa de ' . $name;
        }

        $company = new Company();
        $company->setName($companyName);
        $company->setDescription('Empresa creada automáticamente para ' . $name);
        $company->setActive(true);
        $company->setDomain($company->getRandomDomain());
        $company->setMinimumBookingTime(60); // 1 hora mínimo
        $company->setMaximumFutureTime(90);  // 90 días máximo

        // Crear usuario
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
        
        // Persistir entidades
        $this->entityManager->persist($company);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $io->success('Usuario y empresa creados exitosamente:');
        $io->table(
            ['Campo', 'Valor'],
            [
                ['Usuario ID', $user->getId()],
                ['Nombre', $user->getName()],
                ['Email', $user->getEmail()],
                ['Rol', $user->getRole()->value],
                ['Empresa ID', $company->getId()],
                ['Empresa', $company->getName()],
                ['Dominio', $company->getDomain()],
                ['Tiempo mín. reserva', $company->getMinimumBookingTime() . ' minutos'],
                ['Tiempo máx. futuro', $company->getMaximumFutureTime() . ' días'],
                ['Creado', $user->getCreatedAt()->format('Y-m-d H:i:s')],
            ]
        );
        
        $io->note(sprintf('Puedes iniciar sesión con: %s / %s', $email, $password));
        $io->note(sprintf('Empresa creada: %s', $companyName));
        $io->note(sprintf('URL de reservas: %s', $company->getBookingUrl()));

        return Command::SUCCESS;
    }
}