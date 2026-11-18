<?php

namespace App\Controller\Admin;

use App\Form\RepartitionType;
use App\Service\AttributionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;

/**
 * Distribution des departements aux agents par repartition automatique (un departement = un agent).
 *
 * @IsGranted("ROLE_ADMIN")
 */
final class DepartementController extends AbstractController
{
    private AttributionService $attributions;

    public function __construct(AttributionService $attributions)
    {
        $this->attributions = $attributions;
    }

    /**
     * @Route("/admin", name="admin_home", methods={"GET"})
     */
    public function home(): RedirectResponse
    {
        return $this->redirectToRoute('admin_departements');
    }

    /**
     * @Route("/admin/departements", name="admin_departements", methods={"GET", "POST"})
     */
    public function index(Request $request): Response
    {
        $repartition = $this->createForm(RepartitionType::class, null, [
            'lignes' => $this->attributions->lignesParDepartement(),
        ]);
        $repartition->handleRequest($request);
        $plan = null;

        if ($repartition->isSubmitted() && $repartition->isValid()) {
            ['departements' => $deps, 'agents' => $agents] = $repartition->getData();
            $plan = $this->attributions->planifierRepartition(array_values($deps), array_values($agents instanceof \Traversable ? iterator_to_array($agents) : $agents));

            if ($repartition->get('appliquer')->isClicked()) {
                $this->attributions->appliquerRepartition($plan);
                $this->addFlash('ok', \sprintf('%d département(s) répartis entre %d agent(s) : %s.', \count($deps), \count($plan), implode(' ; ', array_map(
                    static fn (array $p): string => \sprintf('%s %d lignes', $p['agent']->getLibelle(), $p['lignes']),
                    $plan,
                ))));

                return $this->redirectToRoute('admin_departements');
            }
        }

        return $this->render('admin/departements/index.html.twig', [
            'repartition' => $repartition,
            'plan' => $plan,
        ], new Response(null, $repartition->isSubmitted() && !$repartition->isValid() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }
}
