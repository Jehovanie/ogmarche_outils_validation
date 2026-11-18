<?php

namespace App\Command;

use App\Entity\Marche;
use App\Entity\MarcheHoraire;
use App\Service\HoraireBrut;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:horaire:importer', description: 'Remplit les créneaux par jour (marche_horaire) à partir de horaire_brut')]
final class HoraireImporterCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('force', null, InputOption::VALUE_NONE, 'Relit aussi les fiches qui ont déjà des créneaux, sauf celles dont les horaires sont déjà vérifiés par un agent');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $force = (bool) $input->getOption('force');
        $qb = $this->em->createQueryBuilder()->select('m.id')->from(Marche::class, 'm')
            ->where("m.horaireBrut IS NOT NULL AND m.horaireBrut <> ''")
            ->orderBy('m.id');
        if ($force) {
            $qb->andWhere('m.horaireOk = false');
        } else {
            $qb->andWhere('NOT EXISTS (SELECT 1 FROM '.MarcheHoraire::class.' h WHERE h.marche = m)');
        }
        $ids = array_column($qb->getQuery()->getScalarResult(), 'id');

        $heure = static fn (?string $h): ?\DateTimeImmutable => null === $h ? null : new \DateTimeImmutable('1970-01-01 '.$h);
        $numero = 0;
        $crees = 0;
        $nonReconnus = [];
        $this->em->wrapInTransaction(function () use ($ids, $heure, &$numero, &$crees, &$nonReconnus): void {
            foreach (array_chunk($ids, 500) as $lot) {
                foreach ($lot as $id) {
                    $marche = $this->em->find(Marche::class, $id);
                    $analyse = HoraireBrut::analyser($marche->getHoraireBrut());
                    foreach ($analyse['nonReconnus'] as $segment) {
                        $nonReconnus[$segment][] = $id;
                    }
                    if ([] === $analyse['creneaux']) {
                        continue; // rien de reconnu : on ne touche pas a l'existant
                    }
                    $creneaux = array_map(static fn (array $c): array => ['jour' => $c['jour'], 'moment' => $c['moment'], 'debut' => $heure($c['debut']), 'fin' => $heure($c['fin'])], $analyse['creneaux']);
                    $marche->remplacerCreneaux($creneaux);
                    ++$numero;
                    $crees += \count($creneaux);
                }
                $this->em->flush();
                $this->em->clear();
            }
        });

        $io->success(\sprintf('%d fiche(s) sur %d lue(s), %d créneau(x) créés.', $numero, \count($ids), $crees));
        if ([] !== $nonReconnus) {
            $io->warning(\sprintf('%d texte(s) non reconnu(s), laissés à l\'agent (tableau vide ou incomplet) :', \count($nonReconnus)));
            foreach (\array_slice($nonReconnus, 0, 20, true) as $segment => $fiches) {
                $io->writeln(\sprintf(' - « %s » (%d fiche(s), ex. %d)', $segment, \count($fiches), $fiches[0]));
            }
        }

        return Command::SUCCESS;
    }
}
