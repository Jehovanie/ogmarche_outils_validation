<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Un creneau d'ouverture d'un marche : un jour de la semaine, avec une plage horaire et/ou un
 * moment de la journee. Un meme jour peut avoir plusieurs creneaux (8h-12h puis 15h-18h).
 * heure_fin vide = "a partir de" ; heures vides + moment (matin, après-midi, soir, journée entière) = "le samedi matin".
 */
#[ORM\Entity]
#[ORM\Table(name: 'marche_horaire', schema: 'ogmarche')]
#[ORM\Index(name: 'idx_marche_horaire_marche', columns: ['marche_id'])]
class MarcheHoraire
{
    /** Jours dans l'ordre de la semaine : jour 1 = lundi. */
    public const array JOURS = [1 => 'lundi', 2 => 'mardi', 3 => 'mercredi', 4 => 'jeudi', 5 => 'vendredi', 6 => 'samedi', 7 => 'dimanche'];
    public const array MOMENTS = ['matin', 'après-midi', 'soir', 'journée'];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Marche::class, inversedBy: 'horaires')]
    #[ORM\JoinColumn(name: 'marche_id', nullable: false, onDelete: 'CASCADE')]
    private Marche $marche;

    #[ORM\Column(type: Types::SMALLINT)]
    private int $jour;

    #[ORM\Column(length: 12, nullable: true)]
    private ?string $moment;

    #[ORM\Column(name: 'heure_debut', type: Types::TIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $heureDebut;

    #[ORM\Column(name: 'heure_fin', type: Types::TIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $heureFin;

    public function __construct(Marche $marche, int $jour, ?string $moment = null, ?\DateTimeImmutable $heureDebut = null, ?\DateTimeImmutable $heureFin = null)
    {
        if (!isset(self::JOURS[$jour])) {
            throw new \InvalidArgumentException(\sprintf('Jour inconnu : %d.', $jour));
        }
        if (null !== $moment && !\in_array($moment, self::MOMENTS, true)) {
            throw new \InvalidArgumentException(\sprintf('Moment inconnu : %s.', $moment));
        }
        $this->marche = $marche;
        $this->jour = $jour;
        $this->moment = $moment;
        $this->heureDebut = $heureDebut;
        $this->heureFin = $heureFin;
    }

    public function getJour(): int
    {
        return $this->jour;
    }

    public function getNomJour(): string
    {
        return self::JOURS[$this->jour];
    }

    public function getMoment(): ?string
    {
        return $this->moment;
    }

    public function getHeureDebut(): ?\DateTimeImmutable
    {
        return $this->heureDebut;
    }

    public function getHeureFin(): ?\DateTimeImmutable
    {
        return $this->heureFin;
    }

    /** Ni heure ni moment : le jour est connu mais pas son horaire. */
    public function isIncomplet(): bool
    {
        return null === $this->heureDebut && null === $this->moment;
    }
}
