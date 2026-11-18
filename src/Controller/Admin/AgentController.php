<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Form\AgentType;
use App\Repository\UserRepository;
use App\Service\AgentService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Gestion des comptes agents par un administrateur. */
#[Route('/admin/agents')]
#[IsGranted('ROLE_ADMIN')]
final class AgentController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly AgentService $agents,
    ) {
    }

    #[Route('', name: 'admin_agents', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/agents/index.html.twig', [
            'agents' => $this->users->findBy([], ['email' => 'ASC']),
        ]);
    }

    #[Route('/nouveau', name: 'admin_agent_nouveau', methods: ['GET', 'POST'])]
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

        return $this->render('admin/agents/form.html.twig', ['form' => $form, 'agent' => null]);
    }

    #[Route('/{id}/modifier', name: 'admin_agent_modifier', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function modifier(User $agent, Request $request): Response
    {
        $form = $this->createForm(AgentType::class, $agent, ['soi_meme' => $agent === $this->getUser()]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->agents->enregistrer($agent, $form->get('plainPassword')->getData());
            $this->addFlash('ok', \sprintf('Agent %s enregistré.', $agent->getEmail()));

            return $this->redirectToRoute('admin_agents');
        }

        return $this->render('admin/agents/form.html.twig', ['form' => $form, 'agent' => $agent]);
    }
}
