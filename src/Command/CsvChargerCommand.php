<?php

namespace App\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Charge data/data_geocoder_final.csv dans ogmarche.marche_geocode_raw avec la connexion de
 * l'application, sans psql ni liste de colonnes a recopier : chaque valeur est rangee dans la
 * colonne qui porte le nom de l'en-tete du CSV (l'ordre des colonnes de la table n'est pas celui
 * du CSV). Le CSV a deux colonnes "id" : la 1re est "id", la 2e est "id_ban" (voir sql/schema.sql).
 * Cree la table d'import si elle n'existe pas, et refuse de charger dans une table deja remplie.
 */
#[AsCommand(name: 'app:csv:charger', description: "Charge le CSV des marchés dans la table d'import ogmarche.marche_geocode_raw")]
final class CsvChargerCommand extends Command
{
    private const int LOT = 200;

    public function __construct(
        private readonly Connection $connection,
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('fichier', InputArgument::OPTIONAL, 'Chemin du CSV (relatif au projet ou absolu)', 'data/data_geocoder_final.csv');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $chemin = (string) $input->getArgument('fichier');
        if (!str_starts_with($chemin, '/')) {
            $chemin = $this->projectDir.'/'.$chemin;
        }
        if (!is_file($chemin) || false === $f = fopen($chemin, 'r')) {
            $io->error(\sprintf('Fichier introuvable : %s (le CSV se met dans data/, il n\'est pas versionné).', $chemin));

            return Command::FAILURE;
        }

        if (!$this->connection->fetchOne("SELECT to_regclass('ogmarche.marche_geocode_raw')")) {
            $this->connection->executeStatement((string) file_get_contents($this->projectDir.'/sql/schema.sql'));
            $io->writeln("Table d'import créée (sql/schema.sql).");
        }
        if (($existantes = (int) $this->connection->fetchOne('SELECT count(*) FROM ogmarche.marche_geocode_raw')) > 0) {
            $io->error(\sprintf("ogmarche.marche_geocode_raw contient déjà %d ligne(s) : le chargement les doublerait. Videz la table (TRUNCATE ogmarche.marche_geocode_raw) si c'est voulu.", $existantes));

            return Command::FAILURE;
        }

        // en-tete -> colonnes de la table ; escape vide : le guillemet double ("") est le seul echappement du CSV
        $entete = fgetcsv($f, 0, ';', '"', '');
        if (false === $entete || [] === $entete) {
            $io->error('CSV vide.');

            return Command::FAILURE;
        }
        $entete[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $entete[0]);
        $colonnes = [];
        $vus = [];
        foreach ($entete as $i => $nom) {
            $nom = trim((string) $nom);
            // 1re "id" -> id, 2e "id" -> id_ban
            $colonnes[$i] = 'id' === $nom && isset($vus['id']) ? 'id_ban' : $nom;
            $vus[$nom] = true;
        }
        $table = $this->connection->fetchFirstColumn("SELECT column_name FROM information_schema.columns WHERE table_schema = 'ogmarche' AND table_name = 'marche_geocode_raw'");
        if ([] !== $inconnues = array_diff($colonnes, $table)) {
            $io->error('Colonnes du CSV absentes de la table d\'import : '.implode(', ', $inconnues));

            return Command::FAILURE;
        }

        $nbColonnes = \count($colonnes);
        $liste = implode(', ', array_map(static fn (string $c): string => '"'.$c.'"', $colonnes));
        $total = 0;
        $lot = [];
        $envoyer = function () use (&$lot, $liste, $nbColonnes, &$total): void {
            if ([] === $lot) {
                return;
            }
            $ligne = '('.implode(',', array_fill(0, $nbColonnes, '?')).')';
            $valeurs = array_merge(...$lot);
            $this->connection->executeStatement(
                \sprintf('INSERT INTO ogmarche.marche_geocode_raw (%s) VALUES %s', $liste, implode(',', array_fill(0, \count($lot), $ligne))),
                $valeurs,
            );
            $total += \count($lot);
            $lot = [];
        };

        $this->connection->transactional(function () use ($f, $nbColonnes, &$lot, $envoyer, $io, $chemin): void {
            $numero = 1;
            while (false !== $row = fgetcsv($f, 0, ';', '"', '')) {
                ++$numero;
                if ([null] === $row) {
                    continue; // ligne vide
                }
                if (\count($row) !== $nbColonnes) {
                    throw new \RuntimeException(\sprintf('%s, ligne %d : %d colonne(s) au lieu de %d.', basename($chemin), $numero, \count($row), $nbColonnes));
                }
                // vide -> NULL, comme le \copy de psql
                $lot[] = array_map(static fn (?string $v): ?string => '' === $v ? null : $v, $row);
                if (\count($lot) >= self::LOT) {
                    $envoyer();
                }
            }
            $envoyer();
        });
        fclose($f);

        $io->success(\sprintf('%d ligne(s) chargée(s) dans ogmarche.marche_geocode_raw.', $total));
        $io->writeln('Étape suivante : <info>php bin/console app:marche:importer</info>.');

        return Command::SUCCESS;
    }
}
