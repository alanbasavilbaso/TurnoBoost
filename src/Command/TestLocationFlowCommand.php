<?php

namespace App\Command;

use App\Entity\Company;
use App\Entity\Location;
use App\Entity\User;
use App\Entity\RoleEnum;
use App\Service\DomainRoutingService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:test-location-flow',
    description: 'Prueba el flujo completo de ubicaciones para empresas con una y múltiples ubicaciones'
)]
class TestLocationFlowCommand extends Command
{
    private EntityManagerInterface $entityManager;
    private DomainRoutingService $domainRoutingService;
    private UserPasswordHasherInterface $passwordHasher;

    public function __construct(
        EntityManagerInterface $entityManager,
        DomainRoutingService $domainRoutingService,
        UserPasswordHasherInterface $passwordHasher
    ) {
        $this->entityManager = $entityManager;
        $this->domainRoutingService = $domainRoutingService;
        $this->passwordHasher = $passwordHasher;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('domain', 'd', InputOption::VALUE_OPTIONAL, 'Probar un dominio específico')
            ->addOption('create-test-data', null, InputOption::VALUE_NONE, 'Crear datos de prueba si no existen')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $io->title('Test de Flujo de Ubicaciones');

        if ($input->getOption('create-test-data')) {
            $this->createTestData($io);
        }

        $domain = $input->getOption('domain');
        
        if ($domain) {
            $this->testSpecificDomain($io, $domain);
        } else {
            $this->testAllCompanies($io);
        }

        return Command::SUCCESS;
    }

    private function createTestData(SymfonyStyle $io): void
    {
        $io->section('Creando datos de prueba...');

        // Obtener o crear un usuario para asignar como createdBy
        $testUser = $this->getOrCreateTestUser($io);

        // Crear empresa con una sola ubicación
        $company1 = new Company();
        $company1->setName('Empresa Una Ubicación');
        $company1->setDomain('empresa-una');
        $company1->setActive(true);

        $location1 = new Location();
        $location1->setName('Sede Principal');
        $location1->setAddress('Calle Principal 123');
        $location1->setPhone('+1234567890');
        $location1->setCompany($company1);
        $location1->setCreatedBy($testUser);
        $location1->setActive(true);

        // Crear empresa con múltiples ubicaciones
        $company2 = new Company();
        $company2->setName('Empresa Múltiples Ubicaciones');
        $company2->setDomain('empresa-multiples');
        $company2->setActive(true);

        $location2a = new Location();
        $location2a->setName('Sede Norte');
        $location2a->setAddress('Av. Norte 456');
        $location2a->setPhone('+1234567891');
        $location2a->setCompany($company2);
        $location2a->setCreatedBy($testUser);
        $location2a->setActive(true);

        $location2b = new Location();
        $location2b->setName('Sede Sur');
        $location2b->setAddress('Av. Sur 789');
        $location2b->setPhone('+1234567892');
        $location2b->setCompany($company2);
        $location2b->setCreatedBy($testUser);
        $location2b->setActive(true);

        $location2c = new Location();
        $location2c->setName('Sede Centro');
        $location2c->setAddress('Plaza Central 101');
        $location2c->setPhone('+1234567893');
        $location2c->setCompany($company2);
        $location2c->setCreatedBy($testUser);
        $location2c->setActive(true);

        $this->entityManager->persist($company1);
        $this->entityManager->persist($location1);
        $this->entityManager->persist($company2);
        $this->entityManager->persist($location2a);
        $this->entityManager->persist($location2b);
        $this->entityManager->persist($location2c);

        $this->entityManager->flush();

        $io->success('Datos de prueba creados exitosamente');
    }

    private function getOrCreateTestUser(SymfonyStyle $io): User
    {
        // Buscar un usuario existente
        $existingUser = $this->entityManager->getRepository(User::class)->findOneBy([]);
        
        if ($existingUser) {
            $io->note("Usando usuario existente: {$existingUser->getName()} ({$existingUser->getEmail()})");
            return $existingUser;
        }

        // Si no hay usuarios, crear uno de prueba
        $io->note('No se encontraron usuarios existentes. Creando usuario de prueba...');

        // Crear empresa para el usuario
        $testCompany = new Company();
        $testCompany->setName('Empresa de Prueba');
        $testCompany->setDomain('test-company');
        $testCompany->setActive(true);

        // Crear usuario de prueba
        $testUser = new User();
        $testUser->setName('Usuario de Prueba');
        $testUser->setEmail('test@turnoboost.com');
        $testUser->setRole(RoleEnum::ADMIN);
        $testUser->setCompany($testCompany);
        $testUser->setIsOwner(true);

        // Hash de la contraseña
        $hashedPassword = $this->passwordHasher->hashPassword($testUser, 'test123');
        $testUser->setPasswordHash($hashedPassword);

        $this->entityManager->persist($testCompany);
        $this->entityManager->persist($testUser);
        $this->entityManager->flush();

        $io->success("Usuario de prueba creado: {$testUser->getName()} ({$testUser->getEmail()})");
        
        return $testUser;
    }

    private function testSpecificDomain(SymfonyStyle $io, string $domain): void
    {
        $io->section("Probando dominio: $domain");

        // Verificar que el dominio es válido
        if (!$this->domainRoutingService->isValidDomainRoute($domain)) {
            $io->error("El dominio '$domain' no es válido o está en la lista de exclusión");
            return;
        }

        // Obtener la empresa
        $company = $this->domainRoutingService->getCompanyByDomain($domain);
        if (!$company) {
            $io->error("No se encontró una empresa con el dominio '$domain'");
            return;
        }

        $this->analyzeCompanyLocations($io, $company);
    }

    private function testAllCompanies(SymfonyStyle $io): void
    {
        $io->section('Analizando todas las empresas...');

        $companies = $this->entityManager->getRepository(Company::class)
            ->findBy(['active' => true]);

        if (empty($companies)) {
            $io->warning('No se encontraron empresas activas');
            return;
        }

        $singleLocationCompanies = [];
        $multipleLocationCompanies = [];
        $noLocationCompanies = [];

        foreach ($companies as $company) {
            $locations = $this->entityManager->getRepository(Location::class)
                ->findBy(['company' => $company, 'active' => true]);

            $locationCount = count($locations);

            if ($locationCount === 0) {
                $noLocationCompanies[] = $company;
            } elseif ($locationCount === 1) {
                $singleLocationCompanies[] = $company;
            } else {
                $multipleLocationCompanies[] = $company;
            }
        }

        // Mostrar estadísticas
        $io->table(
            ['Tipo', 'Cantidad', 'Empresas'],
            [
                ['Una ubicación', count($singleLocationCompanies), $this->getCompanyNames($singleLocationCompanies)],
                ['Múltiples ubicaciones', count($multipleLocationCompanies), $this->getCompanyNames($multipleLocationCompanies)],
                ['Sin ubicaciones', count($noLocationCompanies), $this->getCompanyNames($noLocationCompanies)]
            ]
        );

        // Analizar empresas con una ubicación
        if (!empty($singleLocationCompanies)) {
            $io->section('Empresas con una ubicación:');
            foreach ($singleLocationCompanies as $company) {
                $this->analyzeCompanyLocations($io, $company);
            }
        }

        // Analizar empresas con múltiples ubicaciones
        if (!empty($multipleLocationCompanies)) {
            $io->section('Empresas con múltiples ubicaciones:');
            foreach ($multipleLocationCompanies as $company) {
                $this->analyzeCompanyLocations($io, $company);
            }
        }

        // Mostrar empresas problemáticas
        if (!empty($noLocationCompanies)) {
            $io->section('⚠️  Empresas sin ubicaciones activas:');
            foreach ($noLocationCompanies as $company) {
                $io->warning("- {$company->getName()} (dominio: {$company->getDomain()})");
            }
        }
    }

    private function analyzeCompanyLocations(SymfonyStyle $io, Company $company): void
    {
        $locations = $this->entityManager->getRepository(Location::class)
            ->findBy(['company' => $company, 'active' => true]);

        $locationCount = count($locations);
        $domain = $company->getDomain();

        $io->writeln("📍 <info>{$company->getName()}</info> (dominio: <comment>$domain</comment>)");

        if ($locationCount === 0) {
            $io->writeln("   ❌ Sin ubicaciones activas");
            return;
        }

        if ($locationCount === 1) {
            $location = $locations[0];
            $io->writeln("   ✅ Una ubicación: {$location->getName()}");
            $io->writeln("      📍 Dirección: {$location->getAddress()}");
            $io->writeln("      📞 Teléfono: {$location->getPhone()}");
            $io->writeln("      🔗 URL directa: <comment>turnoboost.com/$domain</comment>");
            $io->writeln("      💡 Comportamiento: Se usa automáticamente esta ubicación");
        } else {
            $io->writeln("   ✅ $locationCount ubicaciones:");
            foreach ($locations as $location) {
                $io->writeln("      - {$location->getName()} ({$location->getAddress()})");
            }
            $io->writeln("      🔗 URL directa: <comment>turnoboost.com/$domain</comment>");
            $io->writeln("      🔗 URL con ubicación: <comment>turnoboost.com/$domain?location=ID</comment>");
            $io->writeln("      💡 Comportamiento: Se muestra selector de ubicaciones");
        }

        // Verificar URLs de prueba
        $this->testUrls($io, $domain, $locations);
        
        $io->writeln('');
    }

    private function testUrls(SymfonyStyle $io, string $domain, array $locations): void
    {
        $io->writeln("   🧪 URLs de prueba:");
        
        // URL principal
        $io->writeln("      - <comment>/$domain</comment> (página principal)");
        
        // URLs de API
        $io->writeln("      - <comment>/$domain/api/locations</comment> (obtener ubicaciones)");
        
        if (count($locations) === 1) {
            $location = $locations[0];
            $io->writeln("      - <comment>/$domain/api/services</comment> (servicios de ubicación única)");
        } else {
            foreach ($locations as $location) {
                $io->writeln("      - <comment>/$domain/api/services?location_id={$location->getId()}</comment> (servicios de {$location->getName()})");
            }
        }
    }

    private function getCompanyNames(array $companies): string
    {
        if (empty($companies)) {
            return '-';
        }

        $names = array_map(function(Company $company) {
            return $company->getName() . ' (' . $company->getDomain() . ')';
        }, $companies);

        return implode(', ', $names);
    }
}