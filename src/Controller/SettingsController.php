<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\SettingsType;
use App\Service\SettingsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/settings')]
#[IsGranted('ROLE_ADMIN')]
class SettingsController extends AbstractController
{
    private SettingsService $settingsService;

    public function __construct(SettingsService $settingsService)
    {
        $this->settingsService = $settingsService;
    }

    #[Route('/', name: 'settings_index')]
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $settings = $this->settingsService->getUserSettings($user);
        
        $form = $this->createForm(SettingsType::class, $settings);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->settingsService->saveSettings($settings);
            
            $this->addFlash('success', 'Configuración guardada exitosamente.');
            return $this->redirectToRoute('settings_index');
        }

        return $this->render('settings/index.html.twig', [
            'form' => $form->createView(),
            'settings' => $settings
        ]);
    }
}