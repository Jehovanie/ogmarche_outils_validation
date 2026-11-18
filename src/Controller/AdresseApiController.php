<?php

namespace App\Controller;

use App\Dto\AdresseDto;
use App\Dto\HoraireDto;
use App\Dto\LierDto;
use App\Dto\NomDto;
use App\Dto\PositionDto;
use App\Dto\ProduitsDto;
use App\Dto\SpecificiteDto;
use App\Entity\Marche;
use App\Entity\User;
use App\Service\AdresseValidationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

/** Actions XHR de la page de validation. Toutes exigent le jeton CSRF "marche" (en-tete X-CSRF-Token). */
#[Route('/api/marche/{id}', requirements: ['id' => '\d+'])]
final class AdresseApiController extends AbstractController
{
    public function __construct(private readonly AdresseValidationService $validation)
    {
    }

    #[Route('/position', name: 'api_marche_position', methods: ['POST'])]
    public function changerPosition(int $id, Request $request, #[MapRequestPayload] PositionDto $position): JsonResponse
    {
        $this->checkCsrf($request);
        $this->validation->changerPosition($id, $position, $this->agent());

        return $this->json(['ok' => true]);
    }

    #[Route('/nom', name: 'api_marche_nom', methods: ['POST'])]
    public function changerNom(int $id, Request $request, #[MapRequestPayload] NomDto $nom): JsonResponse
    {
        $this->checkCsrf($request);
        $this->validation->changerNom($id, $nom, $this->agent());

        return $this->json(['ok' => true]);
    }

    #[Route('/nom-ok', name: 'api_marche_nom_ok', methods: ['POST'])]
    public function nomOk(int $id, Request $request): JsonResponse
    {
        $this->checkCsrf($request);
        $this->validation->definirNomOk($id, $request->query->getBoolean('valeur', true), $this->agent());

        return $this->json(['ok' => true]);
    }

    /** Decision garder / ne pas garder pour la fiche et son groupe : ?valeur=1 (garder), 0 (ne pas garder), vide (pas decide). */
    #[Route('/garde', name: 'api_marche_garde', methods: ['POST'])]
    public function garde(int $id, Request $request): JsonResponse
    {
        $this->checkCsrf($request);
        $garde = match ($request->query->getString('valeur')) {
            '1' => true,
            '0' => false,
            '' => null,
            default => throw new BadRequestHttpException('Valeur attendue : 1, 0 ou vide.'),
        };
        try {
            $this->validation->definirGarde($id, $garde, $this->agent());
        } catch (\DomainException $e) {
            return $this->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        return $this->json(['ok' => true]);
    }

    #[Route('/adresse', name: 'api_marche_adresse', methods: ['POST'])]
    public function changerAdresse(int $id, Request $request, #[MapRequestPayload] AdresseDto $adresse): JsonResponse
    {
        $this->checkCsrf($request);
        $avertissements = $this->validation->changerAdresse($id, $adresse, $this->agent());

        return $this->json(['ok' => true, 'avertissements' => $avertissements]);
    }

    #[Route('/adresse-ok', name: 'api_marche_adresse_ok', methods: ['POST'])]
    public function adresseOk(int $id, Request $request): JsonResponse
    {
        $this->checkCsrf($request);
        $this->validation->definirAdresseOk($id, $request->query->getBoolean('valeur', true), $this->agent());

        return $this->json(['ok' => true]);
    }

    #[Route('/horaire', name: 'api_marche_horaire', methods: ['POST'])]
    public function changerHoraire(int $id, Request $request, #[MapRequestPayload] HoraireDto $horaire): JsonResponse
    {
        $this->checkCsrf($request);
        $resultat = $this->validation->changerHoraire($id, $horaire, $this->agent());

        return $this->json(['ok' => true] + $resultat);
    }

    #[Route('/horaire-ok', name: 'api_marche_horaire_ok', methods: ['POST'])]
    public function horaireOk(int $id, Request $request): JsonResponse
    {
        $this->checkCsrf($request);
        $this->validation->definirHoraireOk($id, $request->query->getBoolean('valeur', true), $this->agent());

        return $this->json(['ok' => true]);
    }

    #[Route('/produits', name: 'api_marche_produits', methods: ['POST'])]
    public function changerProduits(int $id, Request $request, #[MapRequestPayload] ProduitsDto $produits): JsonResponse
    {
        $this->checkCsrf($request);
        $avertissements = $this->validation->changerProduits($id, $produits, $this->agent());

        return $this->json(['ok' => true, 'avertissements' => $avertissements]);
    }

    #[Route('/specificite', name: 'api_marche_specificite', methods: ['POST'])]
    public function changerSpecificite(int $id, Request $request, #[MapRequestPayload] SpecificiteDto $specificite): JsonResponse
    {
        $this->checkCsrf($request);
        $this->validation->changerSpecificite($id, $specificite, $this->agent());

        return $this->json(['ok' => true]);
    }

    #[Route('/specificite-ok', name: 'api_marche_specificite_ok', methods: ['POST'])]
    public function specificiteOk(int $id, Request $request): JsonResponse
    {
        $this->checkCsrf($request);
        $this->validation->definirSpecificiteOk($id, $request->query->getBoolean('valeur', true), $this->agent());

        return $this->json(['ok' => true]);
    }

    #[Route('/produits-ok', name: 'api_marche_produits_ok', methods: ['POST'])]
    public function produitsOk(int $id, Request $request): JsonResponse
    {
        $this->checkCsrf($request);
        $this->validation->definirProduitsOk($id, $request->query->getBoolean('valeur', true), $this->agent());

        return $this->json(['ok' => true]);
    }

    #[Route('/traite', name: 'api_marche_traite', methods: ['POST'])]
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

    #[Route('/lier', name: 'api_marche_lier', methods: ['POST'])]
    public function lier(int $id, Request $request, #[MapRequestPayload] LierDto $lier): JsonResponse
    {
        $this->checkCsrf($request);
        try {
            $codens = $this->validation->lier($id, $lier->ids, $this->agent());
        } catch (\DomainException $e) {
            return $this->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        return $this->json(['ok' => true, 'codens' => $codens]);
    }

    #[Route('/delier', name: 'api_marche_delier', methods: ['POST'])]
    public function delier(int $id, Request $request): JsonResponse
    {
        $this->checkCsrf($request);
        $this->validation->delier($id, $this->agent());

        return $this->json(['ok' => true]);
    }

    /** Doublons possibles : ?champ=cp (meme code postal), commune (meme commune) ou denomination (meme nom). */
    #[Route('/candidats', name: 'api_marche_candidats', methods: ['GET'])]
    public function candidats(int $id, Request $request): JsonResponse
    {
        try {
            $fiches = $this->validation->candidats($id, $request->query->getString('champ'), $this->agent());
        } catch (\InvalidArgumentException $e) {
            return $this->json(['message' => $e->getMessage()], 400);
        }

        return $this->json(array_map(static fn (Marche $m): array => [
            'id' => $m->getId(),
            'denominationNormalise' => $m->getDenominationNormalise() ?? $m->getDenomination(),
            'numVoie' => $m->getNumVoie(),
            'typeVoie' => $m->getTypeVoie(),
            'nomVoie' => $m->getNomVoie(),
            'codePostal' => $m->getCodePostal(),
            'commune' => $m->getCommune(),
            'codens' => $m->getCodens(),
        ], $fiches));
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
