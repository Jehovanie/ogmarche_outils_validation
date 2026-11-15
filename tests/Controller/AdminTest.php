<?php

namespace App\Tests\Controller;

use App\Entity\CommuneAttribution;
use App\Entity\Marche;
use App\Entity\User;
use App\Repository\CommuneAttributionRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Form;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Administration : creation des agents et distribution des departements, comme un admin
 * dans son navigateur. Les ecritures sont annulees apres chaque test (DAMA).
 */
final class AdminTest extends WebTestCase
{
    private const DEP = '99';

    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
    }

    public function testUnAgentNAccedePasALAdministration(): void
    {
        $this->client->loginUser($this->creerUser('agent@test.local'));

        $this->client->request('GET', '/depChoice');
        self::assertSelectorNotExists('nav a[href="/admin"]');
        self::assertSelectorExists('nav a[href="/logout"]');

        foreach (['/admin', '/admin/departements', '/admin/agents', '/admin/agents/nouveau'] as $url) {
            $this->client->request('GET', $url);
            self::assertResponseStatusCodeSame(403, $url);
        }
    }

    public function testLAdminCreeUnAgentQuiPeutSeConnecter(): void
    {
        $this->client->loginUser($this->creerUser('admin@test.local', admin: true));

        $this->client->request('GET', '/admin/agents/nouveau');
        $this->client->submitForm('Enregistrer', [
            'agent[email]' => 'nouvel.agent@test.local',
            'agent[name]' => 'Nouvel Agent',
            'agent[plainPassword]' => 'motdepasse-solide',
        ]);
        self::assertResponseRedirects('/admin/agents');
        $this->client->followRedirect();
        self::assertSelectorTextContains('.flash.ok', 'nouvel.agent@test.local');
        self::assertSelectorTextContains('table', 'Nouvel Agent');

        $agent = $this->users()->findOneBy(['email' => 'nouvel.agent@test.local']);
        self::assertFalse($agent->isAdmin());
        self::assertNotSame('motdepasse-solide', $agent->getPassword());

        // le nouvel agent se connecte par le formulaire de connexion
        $this->client->request('GET', '/logout');
        $this->client->request('GET', '/login');
        $this->client->submitForm('Se connecter', ['_username' => 'nouvel.agent@test.local', '_password' => 'motdepasse-solide']);
        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertSelectorTextContains('nav', 'Nouvel Agent');
    }

    public function testCreationRefuseeEmailDejaPrisOuMotDePasseTropCourt(): void
    {
        $this->creerUser('pris@test.local');
        $this->client->loginUser($this->creerUser('admin@test.local', admin: true));

        $this->client->request('GET', '/admin/agents/nouveau');
        $this->client->submitForm('Enregistrer', ['agent[email]' => 'pris@test.local', 'agent[plainPassword]' => 'court']);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('form', 'Un compte existe déjà avec cet email.');
        self::assertSelectorTextContains('form', 'au moins 8 caractères');
    }

    public function testModifierSansMotDePasseGardeLAncienEtUnAdminNeSeRetirePasSesDroits(): void
    {
        $agent = $this->creerUser('agent@test.local');
        $ancienHash = $agent->getPassword();
        $admin = $this->creerUser('admin@test.local', admin: true);
        $this->client->loginUser($admin);

        $this->client->request('GET', "/admin/agents/{$agent->getId()}/modifier");
        $this->client->submitForm('Enregistrer', ['agent[name]' => 'Renommé', 'agent[admin]' => true]);
        self::assertResponseRedirects('/admin/agents');
        $this->em->clear();
        $agent = $this->users()->find($agent->getId());
        self::assertSame('Renommé', $agent->getName());
        self::assertSame($ancienHash, $agent->getPassword());
        self::assertTrue($agent->isAdmin());

        // sur sa propre fiche, la case administrateur est desactivee
        $crawler = $this->client->request('GET', "/admin/agents/{$admin->getId()}/modifier");
        self::assertNotNull($crawler->filter('input[name="agent[admin]"]')->attr('disabled'));
    }

    public function testRepartirUnDepartementLeRetireALAgentPrecedent(): void
    {
        $this->creerFiche('99001');
        $this->creerFiche('99001');
        $this->creerFiche('99002');
        $alice = $this->creerUser('alice@test.local');
        $bob = $this->creerUser('bob@test.local');
        // une commune deja a Alice
        $this->em->persist(new CommuneAttribution(self::DEP, '99001', (int) $alice->getId()));
        $this->em->flush();
        $this->client->loginUser($this->creerUser('admin@test.local', admin: true));

        // un seul departement et un seul agent coches : le departement va entier a Bob
        $crawler = $this->client->request('GET', '/admin/departements');
        $form = $crawler->selectButton('Répartition automatique')->form();
        $this->cocher($form, 'repartition[departements]', [self::DEP]);
        $this->cocher($form, 'repartition[agents]', [(string) $bob->getId()]);
        $this->client->submit($form);
        self::assertResponseRedirects('/admin/departements');
        $this->client->followRedirect();
        self::assertSelectorTextContains('.flash.ok', 'bob@test.local 3 lignes');

        self::assertSame([], $this->attributions()->departementsDe((int) $alice->getId()));
        self::assertTrue($this->attributions()->isAttributed((int) $bob->getId(), self::DEP, '99001'));
        self::assertTrue($this->attributions()->isAttributed((int) $bob->getId(), self::DEP, '99002'));

        // la page Agents montre le departement de Bob
        $this->client->request('GET', '/admin/agents');
        self::assertSelectorTextContains('table', self::DEP);

        // Bob voit le departement dans son parcours
        $this->client->loginUser($bob);
        $this->client->request('GET', '/depChoice');
        self::assertSelectorTextContains('table', self::DEP);
    }

    public function testLaPageDepartementsNeContientQueLaRepartition(): void
    {
        $this->client->loginUser($this->creerUser('admin@test.local', admin: true));

        $crawler = $this->client->request('GET', '/admin/departements');

        self::assertCount(1, $crawler->filter('main section'));
        self::assertSelectorTextContains('main section h2', 'Répartition automatique');
    }

    public function testRepartitionAutomatiqueApercuPuisApplication(): void
    {
        // trois departements de test : X1 = 3 lignes, X2 = 2, X3 = 1
        foreach (['X1' => 3, 'X2' => 2, 'X3' => 1] as $dep => $n) {
            for ($i = 0; $i < $n; ++$i) {
                $this->creerFiche($dep.'001', $dep);
            }
        }
        $alice = $this->creerUser('alice@test.local');
        $bob = $this->creerUser('bob@test.local');
        $this->client->loginUser($this->creerUser('admin@test.local', admin: true));

        $crawler = $this->client->request('GET', '/admin/departements');
        self::assertSelectorExists('[data-tout-selectionner="#repartition-departements"]');
        self::assertSelectorExists('[data-tout-selectionner="#repartition-agents"]');
        $form = $crawler->selectButton('Aperçu de la répartition')->form();
        $this->cocher($form, 'repartition[departements]', ['X1', 'X2', 'X3']);
        $this->cocher($form, 'repartition[agents]', [(string) $alice->getId(), (string) $bob->getId()]);

        // apercu : la repartition s'affiche, rien n'est enregistre
        $crawler = $this->client->submit($form);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.apercu', "rien n'est encore enregistré");
        $lignes = $crawler->filter('.apercu tbody tr')->each(static fn ($tr) => $tr->filter('td')->each(static fn ($td) => $td->text()));
        self::assertSame([['alice@test.local', '3', 'X1'], ['bob@test.local', '3', 'X2, X3']], $lignes);
        self::assertSame([], $this->attributions()->departementsDe((int) $alice->getId()));

        // "Reinitialiser" ramene la page vierge : plus d'apercu ni de case cochee
        $vierge = $this->client->click($crawler->selectLink('Réinitialiser')->link());
        self::assertCount(0, $vierge->filter('.apercu'));
        self::assertCount(0, $vierge->filter('#repartition-departements input[checked]'));
        self::assertCount(0, $vierge->filter('a:contains("Réinitialiser")'));

        // les cases restent cochees : "Repartition automatique" applique le meme plan
        $this->client->submit($crawler->selectButton('Répartition automatique')->form());
        self::assertResponseRedirects('/admin/departements');
        $this->client->followRedirect();
        self::assertSelectorTextContains('.flash.ok', '3 département(s) répartis entre 2 agent(s)');

        self::assertSame(['X1'], $this->attributions()->departementsDe((int) $alice->getId()));
        self::assertSame(['X2', 'X3'], $this->attributions()->departementsDe((int) $bob->getId()));
    }

    public function testRepartitionExigeDesDepartementsEtDesAgents(): void
    {
        $this->client->loginUser($this->creerUser('admin@test.local', admin: true));

        $this->client->request('GET', '/admin/departements');
        $this->client->submitForm('Aperçu de la répartition');

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('#repartition-departements', 'Sélectionnez au moins un département.');
        self::assertSelectorTextContains('#repartition-agents', 'Sélectionnez au moins un agent.');
    }

    /** @param list<string> $valeurs */
    private function cocher(Form $form, string $nom, array $valeurs): void
    {
        foreach ($form[$nom] as $case) {
            if (\in_array($case->availableOptionValues()[0], $valeurs, true)) {
                $case->tick();
            }
        }
    }

    private function creerUser(string $email, bool $admin = false): User
    {
        $user = (new User())->setEmail($email)->setAdmin($admin);
        $user->setPassword(static::getContainer()->get(UserPasswordHasherInterface::class)->hashPassword($user, 'motdepasse'));
        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    private function creerFiche(string $insee, string $dep = self::DEP): void
    {
        $marche = new Marche();
        foreach (['codeDep' => $dep, 'codeInsee' => $insee, 'commune' => 'Testville'] as $prop => $valeur) {
            new \ReflectionProperty(Marche::class, $prop)->setValue($marche, $valeur);
        }
        $this->em->persist($marche);
        $this->em->flush();
    }

    private function users(): UserRepository
    {
        return static::getContainer()->get(UserRepository::class);
    }

    private function attributions(): CommuneAttributionRepository
    {
        return static::getContainer()->get(CommuneAttributionRepository::class);
    }
}
