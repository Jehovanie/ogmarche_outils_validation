<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Form\AgentType;
use App\Repository\UserRepository;
use App\Service\AgentService;
use App\Service\AttributionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;

/**
 * Gestion des comptes agents par un administrateur.
 *
 * @Route("/admin/agents")
 * @IsGranted("ROLE_ADMIN")
 */
final class AgentController extends AbstractController
{
    private UserRepository $users;
    private AgentService $agents;
    private AttributionService $attributions;

    public function __construct(UserRepository $users, AgentService $agents, AttributionService $attributions)
    {
        $this->users = $users;
        $this->agents = $agents;
        $this->attributions = $attributions;
    }

    /**
     * @Route("", name="admin_agents", methods={"GET"})
     */
    public function index(): Response
    {
        $agents = $this->users->findBy([], ['email' => 'ASC']);

        return $this->render('admin/agents/index.html.twig', [
            'agents' => array_map(fn (User $u): array => ['user' => $u, 'departements' => $this->attributions->departementsDe($u)], $agents),
        ]);
    }

    /**
     * @Route("/nouveau", name="admin_agent_nouveau", methods={"GET", "POST"})
     */
    public function nouveau(Request $request): Response
    {
        $agent = new User();
        $form = $this->createForm(AgentType::class, $agent, ['creation' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->agents->enregistrer($agent, $form->get('plainPassword')->getData());
            $this->addFlash('ok', \sprintf('Agent %s créé.', $agent->getEmail()));

            return $this->redirectToRoute('admin_agents');
        }

        return $this->render('admin/agents/form.html.twig', ['form' => $form, 'agent' => null], $this->reponseFormulaire($form));
    }

    /**
     * @Route("/{id}/modifier", name="admin_agent_modifier", methods={"GET", "POST"}, requirements={"id": "\d+"})
     */
    public function modifier(User $agent, Request $request): Response
    {
        $form = $this->createForm(AgentType::class, $agent, ['soi_meme' => $agent === $this->getUser()]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->agents->enregistrer($agent, $form->get('plainPassword')->getData());
            $this->addFlash('ok', \sprintf('Agent %s enregistré.', $agent->getEmail()));

            return $this->redirectToRoute('admin_agents');
        }

        return $this->render('admin/agents/form.html.twig', ['form' => $form, 'agent' => $agent], $this->reponseFormulaire($form));
    }

    /** Formulaire soumis mais non valide : 422, comme le fait Symfony 6.2+. */
    private function reponseFormulaire(FormInterface $form): Response
    {
        return new Response(null, $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK);
    }
}
