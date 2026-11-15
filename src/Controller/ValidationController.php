<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\MarcheRepository;
use App\Service\AdresseValidationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/** Pages HTML du parcours de validation : departement -> commune -> fiche. */
final class ValidationController extends AbstractController
{
    public function __construct(
        private readonly MarcheRepository $marches,
        private readonly AdresseValidationService $validation,
    ) {
    }

    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function home(): RedirectResponse
    {
        return $this->redirectToRoute('app_dep_choice');
    }

    /** Departements attribues a l'agent. */
    #[Route('/depChoice', name: 'app_dep_choice', methods: ['GET'])]
    public function depChoice(): Response
    {
        $stats = $this->marches->statsParDepartement($this->agent()->getId());

        return $this->render('validation/dep_choice.html.twig', ['items' => $stats]);
    }

    /** Communes d'un departement attribuees a l'agent. */
    #[Route('/dep/{dep}', name: 'app_communes', methods: ['GET'], requirements: ['dep' => '[0-9A-Za-z]{2,3}'])]
    public function communes(string $dep): Response
    {
        $items = $this->marches->statsParCommune($this->agent()->getId(), $dep);
        if ([] === $items) {
            throw $this->createAccessDeniedException("Ce département ne vous est pas attribué.");
        }

        return $this->render('validation/communes.html.twig', ['dep' => $dep, 'items' => $items]);
    }

    /** Prochaine fiche a valider dans une commune (equivalent de /pResto/specialDep). */
    #[Route('/validation/{dep}/{insee}', name: 'app_fiche', methods: ['GET'], requirements: ['dep' => '[0-9A-Za-z]{2,3}', 'insee' => '[0-9A-Za-z]{5}'])]
    public function fiche(string $dep, string $insee, Request $request): Response
    {
        // ?fiche=<id> garde l'agent sur la fiche qu'il vient de lier ou de delier
        $ficheId = $request->query->getInt('fiche') ?: null;
        $marche = $this->validation->prochaineFiche($dep, $insee, $this->agent(), $ficheId);
        if (null === $marche) {
            // plus rien a valider : retour a la liste des communes
            return $this->redirectToRoute('app_communes', ['dep' => $dep]);
        }

        return $this->render('validation/fiche.html.twig', [
            'marche' => $marche,
            'groupe' => $this->validation->groupe($marche),
            'dep' => $dep,
            'insee' => $insee,
        ]);
    }

    private function agent(): User
    {
        $user = $this->getUser();
        \assert($user instanceof User);

        return $user;
    }
}
