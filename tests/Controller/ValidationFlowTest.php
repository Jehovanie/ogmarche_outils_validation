<?php

namespace App\Tests\Controller;

use App\Entity\CommuneAttribution;
use App\Entity\Marche;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Parcours complet comme un agent : attribution, affichage de la fiche, correction
 * d'adresse, validation, traitement. Les ecritures sont annulees apres chaque test (DAMA).
 */
final class ValidationFlowTest extends WebTestCase
{
    private const DEP = '99';
    private const INSEE = '99001';

    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
    }

    public function testAnonymeRedirigeVersLogin(): void
    {
        $this->client->request('GET', '/depChoice');

        self::assertResponseRedirects('/login');
    }

    public function testAgentCorrigeValideEtTraiteUneFiche(): void
    {
        $agent = $this->creerAgent('agent@test.local');
        $id = $this->creerFiche();
        $this->em->persist(new CommuneAttribution(self::DEP, self::INSEE, (int) $agent->getId()));
        $this->em->flush();
        $this->client->loginUser($agent);

        // liste des departements puis fiche
        $this->client->request('GET', '/depChoice');
        self::assertSelectorTextContains('table', self::DEP);
        $crawler = $this->client->request('GET', '/validation/'.self::DEP.'/'.self::INSEE);
        self::assertResponseIsSuccessful();
        $csrf = $crawler->filter('#fiche')->attr('data-csrf');

        // traiter sans nom ni adresse valides : refuse
        $this->post("/api/marche/$id/traite", $csrf);
        self::assertResponseStatusCodeSame(422);

        // nom vide : refuse ; nom corrige puis valide : accepte
        $this->post("/api/marche/$id/nom", $csrf, ['denomination' => '  ']);
        self::assertResponseStatusCodeSame(422);
        $this->post("/api/marche/$id/nom", $csrf, ['denomination' => 'Marché de Testville']);
        self::assertResponseIsSuccessful();
        $this->post("/api/marche/$id/nom-ok", $csrf);
        self::assertResponseIsSuccessful();

        // nom valide mais adresse non validee : toujours refuse
        $this->post("/api/marche/$id/traite", $csrf);
        self::assertResponseStatusCodeSame(422);

        // adresse invalide : refusee par le validateur
        $this->post("/api/marche/$id/adresse", $csrf, ['codePostal' => '12', 'commune' => 'Testville']);
        self::assertResponseStatusCodeSame(422);

        // adresse valide mais code postal d'un autre departement : acceptee avec avertissement
        $this->post("/api/marche/$id/adresse", $csrf, ['numVoie' => '12', 'typeVoie' => 'RUE', 'nomVoie' => 'DU MARCHE', 'codePostal' => '69000', 'commune' => 'Testville']);
        self::assertResponseIsSuccessful();
        $reponse = json_decode($this->client->getResponse()->getContent(), true);
        self::assertCount(1, $reponse['avertissements']);

        $this->post("/api/marche/$id/adresse-ok", $csrf);
        self::assertResponseIsSuccessful();
        $this->post("/api/marche/$id/traite", $csrf);
        self::assertResponseIsSuccessful();

        $this->em->clear();
        $fiche = $this->em->find(Marche::class, $id);
        self::assertTrue($fiche->isTraite());
        self::assertTrue($fiche->isAdresseOk());
        self::assertTrue($fiche->isNomOk());
        self::assertSame('Marché de Testville', $fiche->getDenomination());
        self::assertSame('DU MARCHE', $fiche->getNomVoie());
        self::assertSame($agent->getId(), $fiche->getValidatedBy());
        self::assertNotNull($fiche->getDateDeValidation());

        // plus rien a valider : retour a la liste des communes
        $this->client->request('GET', '/validation/'.self::DEP.'/'.self::INSEE);
        self::assertResponseRedirects('/dep/'.self::DEP);
    }

    public function testModifierAdresseInvalideLeDrapeauAdresseOk(): void
    {
        $agent = $this->creerAgent('agent@test.local');
        $id = $this->creerFiche();
        $this->em->persist(new CommuneAttribution(self::DEP, self::INSEE, (int) $agent->getId()));
        $this->em->flush();
        $this->client->loginUser($agent);
        $csrf = $this->csrf();

        $this->post("/api/marche/$id/adresse-ok", $csrf);
        $this->post("/api/marche/$id/adresse", $csrf, ['commune' => 'Autre']);
        $this->em->clear();

        self::assertFalse($this->em->find(Marche::class, $id)->isAdresseOk());
    }

    public function testModifierLeNomInvalideLeDrapeauNomOk(): void
    {
        $agent = $this->creerAgent('agent@test.local');
        $id = $this->creerFiche();
        $this->em->persist(new CommuneAttribution(self::DEP, self::INSEE, (int) $agent->getId()));
        $this->em->flush();
        $this->client->loginUser($agent);
        $csrf = $this->csrf();

        $this->post("/api/marche/$id/nom-ok", $csrf);
        $this->post("/api/marche/$id/nom", $csrf, ['denomination' => 'Autre nom']);
        $this->em->clear();

        self::assertFalse($this->em->find(Marche::class, $id)->isNomOk());
    }

    public function testAgentChangeLaPosition(): void
    {
        $agent = $this->creerAgent('agent@test.local');
        $id = $this->creerFiche();
        $this->em->persist(new CommuneAttribution(self::DEP, self::INSEE, (int) $agent->getId()));
        $this->em->flush();
        $this->client->loginUser($agent);
        $csrf = $this->csrf();

        // hors limites ou manquante : refusee
        $this->post("/api/marche/$id/position", $csrf, ['latitude' => 95.0, 'longitude' => 5.2]);
        self::assertResponseStatusCodeSame(422);
        $this->post("/api/marche/$id/position", $csrf, ['latitude' => 46.2]);
        self::assertResponseStatusCodeSame(422);

        $this->post("/api/marche/$id/position", $csrf, ['latitude' => 46.207013, 'longitude' => 5.229934]);
        self::assertResponseIsSuccessful();
        $this->em->clear();
        $fiche = $this->em->find(Marche::class, $id);
        self::assertEqualsWithDelta(46.207013, $fiche->getLatitude(), 1e-9);
        self::assertEqualsWithDelta(5.229934, $fiche->getLongitude(), 1e-9);
    }

    public function testAgentNeToucheQueSonPerimetre(): void
    {
        $proprietaire = $this->creerAgent('proprietaire@test.local');
        $intrus = $this->creerAgent('intrus@test.local');
        $id = $this->creerFiche();
        $this->em->persist(new CommuneAttribution(self::DEP, self::INSEE, (int) $proprietaire->getId()));
        $this->em->flush();
        $this->client->loginUser($intrus);

        $this->client->request('GET', '/validation/'.self::DEP.'/'.self::INSEE);
        self::assertResponseStatusCodeSame(403);

        $csrf = $this->csrf();
        $this->post("/api/marche/$id/adresse-ok", $csrf);
        self::assertResponseStatusCodeSame(403);
        $this->post("/api/marche/$id/nom", $csrf, ['denomination' => 'Piraté']);
        self::assertResponseStatusCodeSame(403);
        $this->post("/api/marche/$id/position", $csrf, ['latitude' => 1.0, 'longitude' => 1.0]);
        self::assertResponseStatusCodeSame(403);
    }

    public function testJetonCsrfObligatoire(): void
    {
        $agent = $this->creerAgent('agent@test.local');
        $id = $this->creerFiche();
        $this->em->persist(new CommuneAttribution(self::DEP, self::INSEE, (int) $agent->getId()));
        $this->em->flush();
        $this->client->loginUser($agent);

        $this->post("/api/marche/$id/adresse-ok", 'mauvais-jeton');

        self::assertResponseStatusCodeSame(403);
    }

    private function creerAgent(string $email): User
    {
        $user = (new User())->setEmail($email)->setPassword('x');
        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    private function creerFiche(): int
    {
        $marche = new Marche();
        $rc = new \ReflectionClass(Marche::class);
        foreach (['codeDep' => self::DEP, 'codeInsee' => self::INSEE, 'commune' => 'Testville', 'denomination' => 'Marché de test'] as $prop => $valeur) {
            $rc->getProperty($prop)->setValue($marche, $valeur);
        }
        $this->em->persist($marche);
        $this->em->flush();

        return (int) $marche->getId();
    }

    /** Jeton CSRF de la session courante, lu dans l'en-tete des pages. */
    private function csrf(): string
    {
        $crawler = $this->client->request('GET', '/depChoice');
        self::assertResponseIsSuccessful();

        return (string) $crawler->filter('meta[name=csrf-token]')->attr('content');
    }

    /** @param array<string, mixed>|null $json */
    private function post(string $url, string $csrf, ?array $json = null): void
    {
        $this->client->request(Request::METHOD_POST, $url, [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_CSRF_TOKEN' => $csrf,
        ], null === $json ? null : json_encode($json));
    }
}
