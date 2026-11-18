<?php

namespace App\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Execute sql/marche.sql avec la connexion de l'application (celle de .env.local) : plus besoin
 * de psql ni de ses variables PG*. Le script copie marche_geocode_raw dans ogmarche.marche et
 * ne fait rien si marche contient deja des fiches.
 */
#[AsCommand(name: 'app:marche:importer', description: 'Alimente ogmarche.marche depuis marche_geocode_raw (sql/marche.sql)')]
final class MarcheImporterCommand extends Command
{
    public function __construct(
        private readonly Connection $connection,
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $fichier = $this->projectDir.'/sql/marche.sql';
        if (false === $sql = @file_get_contents($fichier)) {
            $io->error(\sprintf('Fichier introuvable : %s', $fichier));

            return Command::FAILURE;
        }

        if (!$this->connection->fetchOne("SELECT to_regclass('ogmarche.marche_geocode_raw')")) {
            $io->error("La table d'import ogmarche.marche_geocode_raw n'existe pas : lancez d'abord sql/schema.sql et chargez le CSV (README, étape 3).");

            return Command::FAILURE;
        }
        if (!$this->connection->fetchOne("SELECT to_regclass('ogmarche.marche')")) {
            $io->error("La table ogmarche.marche n'existe pas : lancez d'abord les migrations (doctrine:migrations:migrate).");

            return Command::FAILURE;
        }

        if (0 === (int) $this->connection->fetchOne('SELECT count(*) FROM ogmarche.marche_geocode_raw')) {
            $io->error("La table d'import ogmarche.marche_geocode_raw est vide : chargez d'abord data/data_geocoder_final.csv avec \\copy (README, étape 3).");

            return Command::FAILURE;
        }

        $avant = (int) $this->connection->fetchOne('SELECT count(*) FROM ogmarche.marche');
        if ($avant > 0) {
            $io->note(\sprintf('ogmarche.marche contient déjà %d fiche(s) : rien n\'est importé (le script ne remplit qu\'une table vide).', $avant));

            return Command::SUCCESS;
        }

        // le script a plusieurs instructions et une fonction temporaire : tout dans une transaction, sur une seule connexion
        $this->connection->transactional(function (Connection $c) use ($sql): void {
            $c->executeStatement($sql);
        });

        $apres = (int) $this->connection->fetchOne('SELECT count(*) FROM ogmarche.marche');
        $io->success(\sprintf('%d fiche(s) importée(s) dans ogmarche.marche.', $apres));
        $io->writeln('Étape suivante : <info>php bin/console app:horaire:importer</info> (créneaux par jour).');

        return Command::SUCCESS;
    }
}
