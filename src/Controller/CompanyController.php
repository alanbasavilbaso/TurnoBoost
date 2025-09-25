<?php

namespace App\Controller;

use App\Entity\Company;
use App\Entity\User;
use App\Form\CompanyType;
use App\Service\ImageUploadService;
use App\Service\WhatsAppService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/configuracion')]
#[IsGranted('ROLE_ADMIN')]
class CompanyController extends AbstractController
{
    public function __construct(
        private ImageUploadService $imageUploadService,
        private WhatsAppService $whatsappService
    ) {}

    #[Route('/', name: 'app_company_config', methods: ['GET', 'POST'])]
    public function config(Request $request, EntityManagerInterface $entityManager): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $company = $user->getCompany();
        
        if (!$company) {
            $this->addFlash('error', 'Debe estar asociado a una empresa para acceder a la configuración.');
            return $this->redirectToRoute('app_index');
        }

        // Verificar estado de WhatsApp si hay teléfono configurado
        $whatsappStatus = null;
        if ($company->getPhone()) {
            try {
                $whatsappStatus = $this->whatsappService->getQRStatus($company->getPhone());
                
                // Actualizar estado de conexión
                if (isset($whatsappStatus['connected'])) {
                    $status = $whatsappStatus['connected'] ? 'connected' : 'disconnected';
                    $company->setWhatsappConnectionStatus($status);
                    $company->setWhatsappLastChecked(new \DateTime());
                    $entityManager->flush();
                }
            } catch (\Exception $e) {
                // Log error but don't break the page
                $whatsappStatus = ['error' => $e->getMessage()];
            }
        }

        $form = $this->createForm(CompanyType::class, $company);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Manejar conversión del teléfono
            $phoneValue = $form->get('phone')->getData();
            if ($phoneValue) {
                // Limpiar el valor (remover espacios y caracteres no numéricos)
                $cleanPhone = preg_replace('/\D/', '', $phoneValue);
                
                if (!empty($cleanPhone)) {
                    // Agregar el prefijo +54 si no lo tiene
                    if (!str_starts_with($cleanPhone, '54')) {
                        $company->setPhone('+54' . $cleanPhone);
                    } else {
                        $company->setPhone('+' . $cleanPhone);
                    }
                } else {
                    $company->setPhone(null);
                }
            } else {
                $company->setPhone(null);
            }

            // Manejar subida de logo
            $logoFile = $form->get('logoFile')->getData();
            if ($logoFile) {
                try {
                    // Eliminar logo anterior si existe
                    if ($company->getLogoUrl()) {
                        $this->imageUploadService->deleteImage($company->getLogoUrl());
                    }
                    
                    $logoUrl = $this->imageUploadService->uploadCompanyLogo($logoFile, $company->getId());
                    $company->setLogoUrl($logoUrl);
                } catch (\Exception $e) {
                    $this->addFlash('error', 'Error al subir el logo: ' . $e->getMessage());
                }
            }

            // Manejar subida de portada
            $coverFile = $form->get('coverFile')->getData();
            if ($coverFile) {
                try {
                    // Eliminar portada anterior si existe
                    if ($company->getCoverUrl()) {
                        $this->imageUploadService->deleteImage($company->getCoverUrl());
                    }
                    
                    $coverUrl = $this->imageUploadService->uploadCompanyCover($coverFile, $company->getId());
                    $company->setCoverUrl($coverUrl);
                } catch (\Exception $e) {
                    $this->addFlash('error', 'Error al subir la portada: ' . $e->getMessage());
                }
            }

            $company->setUpdatedAt(new \DateTime());
            $entityManager->flush();

            $this->addFlash('success', 'La configuración de la empresa se ha actualizado correctamente.');
            return $this->redirectToRoute('app_company_config');
        }

        return $this->render('company/config.html.twig', [
            'company' => $company,
            'form' => $form->createView(),
            'whatsappStatus' => $whatsappStatus,
        ]);
    }

    /**
     * Obtiene el estado del QR de WhatsApp vía AJAX
     */
    #[Route('/whatsapp/qr-status', name: 'app_company_whatsapp_qr_status', methods: ['POST'])]
    public function getWhatsAppQRStatus(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $company = $user->getCompany();
        
        if (!$company) {
            return new JsonResponse(['error' => 'Empresa no encontrada'], 404);
        }

        // Obtener el teléfono del request o de la empresa
        $data = json_decode($request->getContent(), true);
        $phone = $data['phone'] ?? null;
        
        if (!$phone && !$company->getPhone()) {
            return new JsonResponse([
                'success' => false,
                'message' => 'No hay teléfono configurado para WhatsApp',
                'needsPhone' => true
            ]);
        }

        // Si se proporciona un teléfono nuevo, actualizarlo
        if ($phone && $phone !== $company->getPhone()) {
            // Validar formato argentino
            if (!preg_match('/^\+54[0-9]{10,12}$/', $phone)) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'El teléfono debe tener formato argentino (+54 + código de área + número)'
                ]);
            }
            
            $company->setPhone($phone);
            $entityManager->flush();
        }

        $phoneToCheck = $phone ?: $company->getPhone();

        try {
            $qrStatus = $this->whatsappService->getQRStatus($phoneToCheck);
            
            // Actualizar estado de conexión en la empresa
            if (isset($qrStatus['state'])) {
                $status = $qrStatus['state'];
                $company->setWhatsappConnectionStatus($status);
                $company->setWhatsappLastChecked(new \DateTime());
                $entityManager->flush();
            }

            return new JsonResponse([
                'success' => true,
                'phone' => $company->getFormattedPhone(),
                'connectionStatus' => $company->getWhatsappConnectionStatus(),
                'lastChecked' => $company->getWhatsappLastChecked()?->format('Y-m-d H:i:s'),
                'qrData' => $qrStatus
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    
}