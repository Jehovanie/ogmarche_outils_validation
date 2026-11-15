<?php

namespace App\Controller;

use App\Dto\AdresseDto;
use App\Dto\NomDto;
use App\Dto\PositionDto;
use App\Entity\User;
use App\Service\AdresseValidationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

/** Actions XHR de la page de validation. Toutes exigent le jeton CSRF "marche" (en-tete X-CSRF-Token). */
#[Route('/api/marche/{id}', requirements: ['id' => '\d+'], methods: ['POST'])]
final class AdresseApiController extends AbstractController
{
    public function __construct(private readonly AdresseValidationService $validation)
    {
    }

    #[Route('/position', name: 'api_marche_position')]
    public function changerPosition(int $id, Request $request, #[MapRequestPayload] PositionDto $position): JsonResponse
    {
        $this->checkCsrf($request);
        $this->validation->changerPosition($id, $position, $this->agent());

        return $this->json(['ok' => true]);
    }

    #[Route('/nom', name: 'api_marche_nom')]
    public function changerNom(int $id, Request $request, #[MapRequestPayload] NomDto $nom): JsonResponse
    {
        $this->checkCsrf($request);
        $this->validation->changerNom($id, $nom, $this->agent());

        return $this->json(['ok' => true]);
    }

    #[Route('/nom-ok', name: 'api_marche_nom_ok')]
    public function nomOk(int $id, Request $request): JsonResponse
    {
        $this->checkCsrf($request);
        $this->validation->definirNomOk($id, $request->query->getBoolean('valeur', true), $this->agent());

        return $this->json(['ok' => true]);
    }

    #[Route('/adresse', name: 'api_marche_adresse')]
    public function changerAdresse(int $id, Request $request, #[MapRequestPayload] AdresseDto $adresse): JsonResponse
    {
        $this->checkCsrf($request);
        $avertissements = $this->validation->changerAdresse($id, $adresse, $this->agent());

        return $this->json(['ok' => true, 'avertissements' => $avertissements]);
    }

    #[Route('/adresse-ok', name: 'api_marche_adresse_ok')]
    public function adresseOk(int $id, Request $request): JsonResponse
    {
        $this->checkCsrf($request);
        $this->validation->definirAdresseOk($id, $request->query->getBoolean('valeur', true), $this->agent());

        return $this->json(['ok' => true]);
    }

    #[Route('/traite', name: 'api_marche_traite')]
    public function traite(int $id, Request $request): JsonResponse
    {
        $this->checkCsrf($request);
        try {
            $this->validation->marquerTraite($id, $this->agent());
        } catch (\DomainException $e) {
            return $this->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        return $this->json(['ok' => true]);
    }

    #[Route('/rappel', name: 'api_marche_rappel')]
    public function rappel(int $id, Request $request): JsonResponse
    {
        $this->checkCsrf($request);
        $this->validation->definirRappel($id, $request->query->getBoolean('valeur', true), $this->agent());

        return $this->json(['ok' => true]);
    }

    private function checkCsrf(Request $request): void
    {
        if (!$this->isCsrfTokenValid('marche', (string) $request->headers->get('X-CSRF-Token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }
    }

    private function agent(): User
    {
        $user = $this->getUser();
        \assert($user instanceof User);

        return $user;
    }
}
