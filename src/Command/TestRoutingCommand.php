<?php

namespace App\Command;

use App\Service\DomainRoutingService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Routing\RouterInterface;

#[AsCommand(
    name: 'app:test-routing',
    description: 'Prueba el sistema de routing para verificar que no hay conflictos entre dominios y rutas específicas'
)]
class TestRoutingCommand extends Command
{
    private RouterInterface $router;
    private DomainRoutingService $domainRoutingService;

    public function __construct(RouterInterface $router, DomainRoutingService $domainRoutingService)
    {
        $this->router = $router;
        $this->domainRoutingService = $domainRoutingService;
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Prueba del Sistema de Routing de Dominios');

        // Obtener todas las rutas
        $routes = $this->router->getRouteCollection();
        $specificRoutes = [];
        $domainRoutes = [];

        foreach ($routes as $name => $route) {
            $path = $route->getPath();
            
            // Filtrar rutas que empiezan con /{domain}
            if (preg_match('/^\/\{domain\}/', $path)) {
                $domainRoutes[] = [
                    'name' => $name,
                    'path' => $path,
                    'priority' => $route->getOption('priority') ?? 0
                ];
            } else if (!str_starts_with($path, '/_')) {
                // Rutas específicas (excluyendo rutas internas de Symfony)
                $specificRoutes[] = [
                    'name' => $name,
                    'path' => $path,
                    'priority' => $route->getOption('priority') ?? 0
                ];
            }
        }

        // Mostrar rutas específicas
        $io->section('Rutas Específicas (tienen prioridad)');
        $specificTable = [];
        foreach ($specificRoutes as $route) {
            $specificTable[] = [
                $route['name'],
                $route['path'],
                $route['priority']
            ];
        }
        $io->table(['Nombre', 'Path', 'Prioridad'], $specificTable);

        // Mostrar rutas de dominio
        $io->section('Rutas de Dominio (prioridad baja)');
        $domainTable = [];
        foreach ($domainRoutes as $route) {
            $domainTable[] = [
                $route['name'],
                $route['path'],
                $route['priority']
            ];
        }
        $io->table(['Nombre', 'Path', 'Prioridad'], $domainTable);

        // Mostrar palabras excluidas
        $io->section('Palabras Excluidas para Dominios');
        $excludedWords = $this->domainRoutingService->getExcludedWords();
        $chunks = array_chunk($excludedWords, 5);
        foreach ($chunks as $chunk) {
            $io->text(implode(', ', $chunk));
        }

        // Probar algunos casos
        $io->section('Pruebas de Validación');
        
        $testCases = [
            'configuracion' => false, // Debe fallar - palabra excluida
            'servicios' => false,     // Debe fallar - palabra excluida
            'agenda' => false,        // Debe fallar - palabra excluida
            'mi-clinica' => true,     // Debe pasar - dominio válido
            'beati' => true,          // Debe pasar - dominio válido
            'admin' => false,         // Debe fallar - palabra excluida
            'api' => false,           // Debe fallar - palabra excluida
        ];

        foreach ($testCases as $domain => $shouldPass) {
            $isAvailable = $this->domainRoutingService->isDomainAvailable($domain);
            $status = $isAvailable ? '✅ DISPONIBLE' : '❌ NO DISPONIBLE';
            $expected = $shouldPass ? '✅' : '❌';
            $result = ($isAvailable === $shouldPass) ? '✅ CORRECTO' : '❌ ERROR';
            
            $io->text(sprintf(
                'Dominio: %-15s | Estado: %-15s | Esperado: %s | Resultado: %s',
                $domain,
                $status,
                $expected,
                $result
            ));
        }

        $io->success('Prueba del sistema de routing completada');

        return Command::SUCCESS;
    }
}