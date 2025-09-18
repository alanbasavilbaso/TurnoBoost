<?php

namespace App\Service;

use App\Entity\Company;
use Doctrine\ORM\EntityManagerInterface;

class DomainRoutingService
{
    private EntityManagerInterface $entityManager;
    
    /**
     * Palabras excluidas que no pueden ser usadas como dominios
     * Estas corresponden a rutas existentes en los controllers
     */
    private const EXCLUDED_WORDS = [
        // Rutas principales de controllers
        'configuracion',
        'servicios', 
        'reservas',
        'agenda',
        'profesionales',
        'clients',
        'location',
        'test',
        'login',
        'logout',
        
        // Rutas comunes que podrían causar conflictos
        'api',
        'admin',
        'app',
        'new',
        'edit',
        'delete',
        'show',
        'form',
        'details',
        'reactivate',
        'create',
        'update',
        'cancel',
        'confirm',
        
        // Rutas técnicas
        '_profiler',
        '_wdt',
        'assets',
        'bundles',
        'css',
        'js',
        'images',
        'fonts',
        'favicon',
        'robots',
        'sitemap',
        
        // Palabras reservadas adicionales
        'www',
        'mail',
        'email',
        'ftp',
        'blog',
        'shop',
        'store',
        'help',
        'support',
        'contact',
        'about',
        'terms',
        'privacy',
        'legal',
        'security',
        'status',
        'health',
        'ping',
        'webhook',
        'callback'
    ];

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * Verifica si un dominio está disponible (no está en la lista de excluidos)
     */
    public function isDomainAvailable(string $domain): bool
    {
        $domain = strtolower(trim($domain));
        
        // Verificar si está en la lista de palabras excluidas
        if (in_array($domain, self::EXCLUDED_WORDS, true)) {
            return false;
        }
        
        return true;
    }

    /**
     * Verifica si un dominio existe en la base de datos
     */
    public function domainExists(string $domain): bool
    {
        $company = $this->entityManager->getRepository(Company::class)
            ->findOneBy(['domain' => $domain]);
            
        return $company !== null;
    }

    /**
     * Obtiene una empresa por su dominio
     */
    public function getCompanyByDomain(string $domain): ?Company
    {
        return $this->entityManager->getRepository(Company::class)
            ->findOneBy(['domain' => $domain]);
    }

    /**
     * Valida un dominio para uso (disponible y no existe)
     */
    public function validateDomainForUse(string $domain): array
    {
        $errors = [];
        
        if (!$this->isDomainAvailable($domain)) {
            $errors[] = 'Este dominio no está disponible porque está reservado por el sistema.';
        }
        
        if ($this->domainExists($domain)) {
            $errors[] = 'Este dominio ya está siendo utilizado por otra empresa.';
        }
        
        return $errors;
    }

    /**
     * Obtiene la lista de palabras excluidas
     */
    public function getExcludedWords(): array
    {
        return self::EXCLUDED_WORDS;
    }

    /**
     * Verifica si una ruta corresponde a un dominio válido
     * Esta función será usada en el controller para determinar si debe procesar la ruta
     */
    public function isValidDomainRoute(string $path): bool
    {
        // Remover barras iniciales y finales
        $path = trim($path, '/');
        
        // Si está vacío, no es un dominio válido
        if (empty($path)) {
            return false;
        }
        
        // Si contiene barras, no es un dominio simple
        if (strpos($path, '/') !== false) {
            return false;
        }
        
        // Verificar que no esté en palabras excluidas
        if (!$this->isDomainAvailable($path)) {
            return false;
        }
        
        // Verificar que exista en la base de datos
        return $this->domainExists($path);
    }
}