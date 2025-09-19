<?php

namespace App\Service;

use App\Entity\AuditLog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

class AuditService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Security $security,
        private RequestStack $requestStack
    ) {}

    public function logChange(string $entityType, int $entityId, string $action, ?array $oldValues = null, ?array $newValues = null): void
    {
        $auditLog = new AuditLog();
        $auditLog->setEntityType($entityType);
        $auditLog->setEntityId($entityId);
        $auditLog->setAction($action);
        $auditLog->setOldValues($oldValues);
        $auditLog->setNewValues($newValues);
        
        // Solo establecer el usuario si está autenticado y existe
        $user = $this->security->getUser();
        if ($user && $user->getId()) {
            $auditLog->setUser($user);
        }
        // Si no hay usuario autenticado, dejar user como null (permitido por el constraint nullable: true)
        
        $auditLog->setCreatedAt(new \DateTimeImmutable());
        
        $request = $this->requestStack->getCurrentRequest();
        if ($request) {
            $auditLog->setIpAddress($request->getClientIp());
            $auditLog->setUserAgent($request->headers->get('User-Agent'));
        }

        $this->entityManager->persist($auditLog);
        $this->entityManager->flush();
    }
}