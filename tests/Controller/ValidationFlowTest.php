<?php

namespace App\Tests\Controller;

use App\Entity\CommuneAttribution;
use App\Entity\Marche;
use App\Entity\User;
use App\Enum\ActionType;
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
        $this->em->find(Marche::class, $id)->changeProduits('Fruits et légumes');
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

        // nom et adresse valides mais produits non valides : toujours refuse
        $this->post("/api/marche/$id/traite", $csrf);
        self::assertResponseStatusCodeSame(422);

        // produits qui contiennent encore un horaire : acceptes avec avertissement
        $this->post("/api/marche/$id/produits", $csrf, ['produits' => 'Fruits et légumes, le samedi de 8h à 12h']);
        self::assertResponseIsSuccessful();
        $reponse = json_decode($this->client->getResponse()->getContent(), true);
        self::assertCount(1, $reponse['avertissements']);
        self::assertStringContainsString('samedi, 8h, 12h', $reponse['avertissements'][0]);

        // horaire retire : plus d'avertissement
        $this->post("/api/marche/$id/produits", $csrf, ['produits' => 'Fruits et légumes']);
        $reponse = json_decode($this->client->getResponse()->getContent(), true);
        self::assertSame([], $reponse['avertissements']);

        $this->post("/api/marche/$id/produits-ok", $csrf);
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
        self::assertTrue($fiche->isProduitsOk());
        self::assertSame('Fruits et légumes', $fiche->getProduits());
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

    public function testSpecificationAValiderNExigeQueLesProduits(): void
    {
        $this->agentAvecPerimetre();
        $id = $this->creerFiche(actionType: ActionType::Specification);
        $this->em->find(Marche::class, $id)->changeProduits('Fruits et légumes');
        $this->em->flush();
        $csrf = $this->csrf();

        $crawler = $this->client->request('GET', '/validation/'.self::DEP.'/'.self::INSEE);
        self::assertSelectorTextContains('.badge', 'Spécification à valider');
        self::assertSame(['(déjà validé)', '(déjà validée)'], $crawler->filter('.facultatif')->each(static fn ($n) => $n->text()));

        // produits non valides : refuse
        $this->post("/api/marche/$id/traite", $csrf);
        self::assertResponseStatusCodeSame(422);

        // produits valides, sans nom ni adresse : accepte
        $this->post("/api/marche/$id/produits-ok", $csrf);
        $this->post("/api/marche/$id/traite", $csrf);
        self::assertResponseIsSuccessful();
        $this->em->clear();
        self::assertTrue($this->em->find(Marche::class, $id)->isTraite());
    }

    public function testExistenceAValiderExigeNomAdresseEtProduits(): void
    {
        $this->agentAvecPerimetre();
        $id = $this->creerFiche(actionType: ActionType::Existance);
        $csrf = $this->csrf();

        $crawler = $this->client->request('GET', '/validation/'.self::DEP.'/'.self::INSEE);
        self::assertSelectorTextContains('.badge', 'Existence à valider');
        self::assertCount(0, $crawler->filter('.facultatif'));

        // produits seuls : refuse
        $this->post("/api/marche/$id/produits-ok", $csrf);
        $this->post("/api/marche/$id/traite", $csrf);
        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('nom', json_decode($this->client->getResponse()->getContent(), true)['message']);

        $this->post("/api/marche/$id/nom-ok", $csrf);
        $this->post("/api/marche/$id/adresse-ok", $csrf);
        $this->post("/api/marche/$id/traite", $csrf);
        self::assertResponseIsSuccessful();
    }

    public function testUnGroupeMixteAppliqueLaRegleDeChaqueFiche(): void
    {
        $this->agentAvecPerimetre();
        $spec = $this->creerFiche('Spec', actionType: ActionType::Specification);
        $exist = $this->creerFiche('Exist', actionType: ActionType::Existance);
        $csrf = $this->csrf();
        $this->post("/api/marche/$spec/lier", $csrf, ['ids' => [$exist]]);

        foreach ([$spec, $exist] as $id) {
            $this->post("/api/marche/$id/produits-ok", $csrf);
        }
        // la fiche EXISTANCE n'a ni nom ni adresse valides : tout le groupe est refuse
        $this->post("/api/marche/$spec/traite", $csrf);
        self::assertResponseStatusCodeSame(422);

        $this->post("/api/marche/$exist/nom-ok", $csrf);
        $this->post("/api/marche/$exist/adresse-ok", $csrf);
        $this->post("/api/marche/$spec/traite", $csrf);
        self::assertResponseIsSuccessful();
    }

    public function testProduitsVidesNeBloquentPasLeTraitement(): void
    {
        $this->agentAvecPerimetre();
        $vide = $this->creerFiche('Vide', actionType: ActionType::Specification);
        $rempli = $this->creerFiche('Rempli', actionType: ActionType::Specification);
        $this->em->find(Marche::class, $rempli)->changeProduits('Fromages');
        $this->em->flush();
        $csrf = $this->csrf();

        // fiche vide : rien a verifier, bouton de validation masque
        $crawler = $this->client->request('GET', '/validation/'.self::DEP.'/'.self::INSEE.'?fiche='.$vide);
        self::assertNull($crawler->filter('.produits-vides')->attr('hidden'));
        self::assertNotNull($crawler->filter('.btn-produits-ok')->attr('hidden'));

        // traitee sans aucune validation
        $this->post("/api/marche/$vide/traite", $csrf);
        self::assertResponseIsSuccessful();

        // fiche avec des produits : la validation reste exigee
        $this->post("/api/marche/$rempli/traite", $csrf);
        self::assertResponseStatusCodeSame(422);
    }

    public function testAjouterDesProduitsAUneFicheVideExigeLeurValidation(): void
    {
        $this->agentAvecPerimetre();
        $id = $this->creerFiche(actionType: ActionType::Specification);
        $csrf = $this->csrf();

        $this->post("/api/marche/$id/produits", $csrf, ['produits' => 'Miel']);
        $this->post("/api/marche/$id/traite", $csrf);
        self::assertResponseStatusCodeSame(422);

        $this->post("/api/marche/$id/produits-ok", $csrf);
        $this->post("/api/marche/$id/traite", $csrf);
        self::assertResponseIsSuccessful();
    }

    public function testModifierLesProduitsInvalideLeDrapeauProduitsOk(): void
    {
        $this->agentAvecPerimetre();
        $id = $this->creerFiche();
        $csrf = $this->csrf();

        $this->post("/api/marche/$id/produits-ok", $csrf);
        $this->post("/api/marche/$id/produits", $csrf, ['produits' => 'Fromages']);
        $this->em->clear();

        self::assertFalse($this->em->find(Marche::class, $id)->isProduitsOk());
    }

    public function testLaFicheAfficheLesProduitsSansLaSpecificite(): void
    {
        $this->agentAvecPerimetre();
        $id = $this->creerFiche();
        $marche = $this->em->find(Marche::class, $id);
        $marche->changeProduits('Volailles et œufs');
        new \ReflectionProperty(Marche::class, 'specificite')->setValue($marche, 'Le samedi matin');
        $this->em->flush();

        $crawler = $this->client->request('GET', '/validation/'.self::DEP.'/'.self::INSEE);

        self::assertSame('Volailles et œufs', $crawler->filter("#produits-$id")->text());
        self::assertCount(0, $crawler->filter("#specificite-$id"));
        self::assertCount(1, $crawler->filter('.btn-produits-ok'));

        // enregistrer les produits ne touche pas a la specificite, qui peut garder ses horaires
        $this->post("/api/marche/$id/produits", $this->csrf(), ['produits' => 'Volailles']);
        self::assertSame([], json_decode($this->client->getResponse()->getContent(), true)['avertissements']);
        $this->em->clear();
        self::assertSame('Le samedi matin', $this->em->find(Marche::class, $id)->getSpecificite());
    }

    public function testUneSpecificiteSeuleNExigePasDeValidation(): void
    {
        $this->agentAvecPerimetre();
        $id = $this->creerFiche(actionType: ActionType::Specification);
        new \ReflectionProperty(Marche::class, 'specificite')->setValue($this->em->find(Marche::class, $id), 'Marché bio');
        $this->em->flush();

        $this->post("/api/marche/$id/traite", $this->csrf());
        self::assertResponseIsSuccessful();
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

    private function agentAvecPerimetre(): User
    {
        $agent = $this->creerAgent('agent@test.local');
        $this->em->persist(new CommuneAttribution(self::DEP, self::INSEE, (int) $agent->getId()));
        $this->em->flush();
        $this->client->loginUser($agent);

        return $agent;
    }

    private function codens(int $id): ?string
    {
        $this->em->clear();

        return $this->em->find(Marche::class, $id)->getCodens();
    }

    public function testLierCreeUnGroupeAvecUnCodensParDepartement(): void
    {
        $this->agentAvecPerimetre();
        [$a, $b, $c] = [$this->creerFiche('A'), $this->creerFiche('B'), $this->creerFiche('C')];
        $csrf = $this->csrf();

        $this->post("/api/marche/$a/lier", $csrf, ['ids' => [$b]]);
        self::assertResponseIsSuccessful();
        $premier = json_decode($this->client->getResponse()->getContent(), true)['codens'];
        self::assertMatchesRegularExpression('/^id_99_\d+$/', $premier);
        self::assertSame($premier, $this->codens($a));
        self::assertSame($premier, $this->codens($b));
        self::assertNull($this->codens($c));

        // un second groupe recoit un numero different
        $this->post("/api/marche/$c/lier", $csrf, ['ids' => [$this->creerFiche('D')]]);
        $second = json_decode($this->client->getResponse()->getContent(), true)['codens'];
        self::assertNotSame($premier, $second);
    }

    public function testLierUneFicheAUnGroupeExistantRejointCeGroupe(): void
    {
        $this->agentAvecPerimetre();
        [$a, $b, $c] = [$this->creerFiche('A'), $this->creerFiche('B'), $this->creerFiche('C')];
        $csrf = $this->csrf();

        $this->post("/api/marche/$a/lier", $csrf, ['ids' => [$b]]);
        $groupe = $this->codens($a);
        $this->post("/api/marche/$c/lier", $csrf, ['ids' => [$a]]);

        self::assertSame($groupe, $this->codens($c));
    }

    public function testLierFusionneDeuxGroupes(): void
    {
        $this->agentAvecPerimetre();
        [$a, $b, $c, $d] = [$this->creerFiche('A'), $this->creerFiche('B'), $this->creerFiche('C'), $this->creerFiche('D')];
        $csrf = $this->csrf();

        $this->post("/api/marche/$a/lier", $csrf, ['ids' => [$b]]);
        $this->post("/api/marche/$c/lier", $csrf, ['ids' => [$d]]);
        $this->post("/api/marche/$a/lier", $csrf, ['ids' => [$c]]);

        $codens = $this->codens($a);
        foreach ([$b, $c, $d] as $id) {
            self::assertSame($codens, $this->codens($id));
        }
    }

    public function testLierExigePlusieursFichesDuMemeDepartement(): void
    {
        $this->agentAvecPerimetre();
        $a = $this->creerFiche('A');
        $csrf = $this->csrf();

        $this->post("/api/marche/$a/lier", $csrf, ['ids' => [$a]]);
        self::assertResponseStatusCodeSame(422);
        $this->post("/api/marche/$a/lier", $csrf, ['ids' => []]);
        self::assertResponseStatusCodeSame(422);
    }

    public function testDelierUnDoublonDissoutUnGroupeDeDeux(): void
    {
        $this->agentAvecPerimetre();
        [$a, $b, $c] = [$this->creerFiche('A'), $this->creerFiche('B'), $this->creerFiche('C')];
        $csrf = $this->csrf();
        $this->post("/api/marche/$a/lier", $csrf, ['ids' => [$b, $c]]);
        $groupe = $this->codens($a);

        $this->post("/api/marche/$c/delier", $csrf);
        self::assertNull($this->codens($c));
        self::assertSame($groupe, $this->codens($a));

        // il ne reste que deux fiches : en delier une dissout le groupe
        $this->post("/api/marche/$b/delier", $csrf);
        self::assertNull($this->codens($a));
        self::assertNull($this->codens($b));
    }

    public function testTraiterTraiteToutLeGroupeEtExigeChaqueFicheValidee(): void
    {
        $this->agentAvecPerimetre();
        [$a, $b] = [$this->creerFiche('A'), $this->creerFiche('B')];
        $csrf = $this->csrf();
        $this->post("/api/marche/$a/lier", $csrf, ['ids' => [$b]]);

        foreach ([$a] as $id) {
            $this->post("/api/marche/$id/nom-ok", $csrf);
            $this->post("/api/marche/$id/adresse-ok", $csrf);
            $this->post("/api/marche/$id/produits-ok", $csrf);
        }
        // B n'est pas validee : tout le groupe reste a traiter
        $this->post("/api/marche/$a/traite", $csrf);
        self::assertResponseStatusCodeSame(422);
        $this->em->clear();
        self::assertFalse($this->em->find(Marche::class, $a)->isTraite());

        $this->post("/api/marche/$b/nom-ok", $csrf);
        $this->post("/api/marche/$b/adresse-ok", $csrf);
        $this->post("/api/marche/$b/produits-ok", $csrf);
        $this->post("/api/marche/$a/traite", $csrf);
        self::assertResponseIsSuccessful();
        $this->em->clear();
        self::assertTrue($this->em->find(Marche::class, $a)->isTraite());
        self::assertTrue($this->em->find(Marche::class, $b)->isTraite());
    }

    public function testLaFicheAffichePresenteTousLesDoublonsDuGroupe(): void
    {
        $this->agentAvecPerimetre();
        [$a, $b] = [$this->creerFiche('Alpha'), $this->creerFiche('Beta')];
        $this->post("/api/marche/$a/lier", $this->csrf(), ['ids' => [$b]]);

        $crawler = $this->client->request('GET', '/validation/'.self::DEP.'/'.self::INSEE);

        self::assertCount(2, $crawler->filter('.fiche-card'));
        // ?fiche= garde l'agent sur la fiche demandee
        $crawler = $this->client->request('GET', '/validation/'.self::DEP.'/'.self::INSEE."?fiche=$b");
        self::assertCount(2, $crawler->filter('.fiche-card'));
    }

    public function testCandidatsParCodePostalOuParCommuneDansLePerimetre(): void
    {
        $agent = $this->agentAvecPerimetre();
        $this->em->persist(new CommuneAttribution(self::DEP, '99003', (int) $agent->getId()));
        $a = $this->creerFiche('A', self::DEP, self::INSEE, '99100', 'Testville');
        $memeCp = $this->creerFiche('Meme CP', self::DEP, '99003', '99100', 'Voisinville');
        $memeCommune = $this->creerFiche('Meme commune', self::DEP, self::INSEE, '99200', 'Testville');
        $this->creerFiche('Autre', self::DEP, self::INSEE, '99300', 'Ailleurs');
        // meme code postal mais commune non attribuee a l'agent : jamais proposee
        $this->creerFiche('Hors perimetre', self::DEP, '99002', '99100', 'Testville');

        $this->client->request('GET', "/api/marche/$a/candidats?champ=cp");
        self::assertResponseIsSuccessful();
        self::assertSame([$memeCp], array_column(json_decode($this->client->getResponse()->getContent(), true), 'id'));

        $this->client->request('GET', "/api/marche/$a/candidats?champ=commune");
        $lignes = json_decode($this->client->getResponse()->getContent(), true);
        self::assertSame([$memeCommune], array_column($lignes, 'id'));
        // colonnes attendues par le tableau
        self::assertSame(['id', 'denominationNormalise', 'numVoie', 'typeVoie', 'nomVoie', 'codePostal', 'commune', 'codens'], array_keys($lignes[0]));

        $this->client->request('GET', "/api/marche/$a/candidats?champ=autre");
        self::assertResponseStatusCodeSame(400);
    }

    public function testCandidatsParDenomination(): void
    {
        $agent = $this->agentAvecPerimetre();
        $this->em->persist(new CommuneAttribution(self::DEP, '99003', (int) $agent->getId()));
        $a = $this->creerFiche('Marché du centre');
        $memeNom = $this->creerFiche('Marché du centre', self::DEP, '99003');
        $this->creerFiche('Marché du port');
        // meme nom mais commune non attribuee a l'agent : jamais propose
        $this->creerFiche('Marché du centre', self::DEP, '99002');

        $this->client->request('GET', "/api/marche/$a/candidats?champ=denomination");

        self::assertResponseIsSuccessful();
        self::assertSame([$memeNom], array_column(json_decode($this->client->getResponse()->getContent(), true), 'id'));
    }

    public function testCandidatsExcluentLeGroupeEtLesFichesTraitees(): void
    {
        $this->agentAvecPerimetre();
        [$a, $b, $c] = [$this->creerFiche('A'), $this->creerFiche('B'), $this->creerFiche('C')];
        $this->post("/api/marche/$a/lier", $this->csrf(), ['ids' => [$b]]);

        $this->client->request('GET', "/api/marche/$a/candidats?champ=commune");
        self::assertSame([$c], array_column(json_decode($this->client->getResponse()->getContent(), true), 'id'));
    }

    public function testLaPageAfficheLesBoutonsDeRecherchePreremplis(): void
    {
        $this->agentAvecPerimetre();
        $this->creerFiche('A', self::DEP, self::INSEE, '99100', 'Testville');

        $crawler = $this->client->request('GET', '/validation/'.self::DEP.'/'.self::INSEE);

        self::assertStringContainsString('99100', $crawler->filter('#btn-search-cp')->text());
        self::assertStringContainsString('Testville', $crawler->filter('#btn-search-commune')->text());
        self::assertStringContainsString('(A)', $crawler->filter('#btn-search-denomination')->text());
        self::assertCount(1, $crawler->filter('#doublons-table'));
        // outils de verification de la fiche : recherches Google uniquement
        foreach (['.btn-google', '.btn-maps', '.btn-images'] as $bouton) {
            self::assertCount(1, $crawler->filter($bouton), $bouton);
        }
        foreach (['.btn-mairie', '.btn-streetview', '.btn-osm', '.btn-ban'] as $bouton) {
            self::assertCount(0, $crawler->filter($bouton), $bouton);
        }
    }

    public function testAgentNeLiePasLesFichesDUneCommuneNonAttribuee(): void
    {
        $this->agentAvecPerimetre();
        $a = $this->creerFiche('A');
        $etranger = $this->creerFiche('Z', self::DEP, '99002');

        $this->post("/api/marche/$a/lier", $this->csrf(), ['ids' => [$etranger]]);

        self::assertResponseStatusCodeSame(403);
        self::assertNull($this->codens($a));
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

    private function creerFiche(string $denomination = 'Marché de test', string $dep = self::DEP, string $insee = self::INSEE, ?string $codePostal = null, string $commune = 'Testville', ActionType $actionType = ActionType::Existance): int
    {
        $marche = new Marche();
        $rc = new \ReflectionClass(Marche::class);
        foreach (['codeDep' => $dep, 'codeInsee' => $insee, 'commune' => $commune, 'codePostal' => $codePostal, 'denomination' => $denomination, 'actionType' => $actionType] as $prop => $valeur) {
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
