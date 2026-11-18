<?php

namespace App\Entity;

use App\Enum\ActionType;
use App\Repository\MarcheRepository;
use App\Service\HoraireBrut;
use App\Service\ResumeHoraire;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Fiche de marche a valider. Alimentee depuis ogmarche.marche_geocode_raw (voir
 * sql/marche.sql) ; raw_row_id garde le lien vers la ligne brute d'origine.
 */
#[ORM\Entity(repositoryClass: MarcheRepository::class)]
#[ORM\Table(name: 'marche', schema: 'ogmarche')]
#[ORM\Index(name: 'idx_marche_dep_insee', columns: ['code_dep', 'code_insee'])]
#[ORM\Index(name: 'idx_marche_codens', columns: ['codens'])]
class Marche
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: 'raw_row_id', type: Types::BIGINT, nullable: true)]
    private ?string $rawRowId = null;

    #[ORM\Column(name: 'unique_id', length: 40, nullable: true)]
    private ?string $uniqueId = null;

    #[ORM\Column(name: 'id_marche', nullable: true)]
    private ?int $idMarche = null;

    #[ORM\Column(name: 'denomination_f', length: 100, nullable: true)]
    private ?string $denomination = null;

    #[ORM\Column(name: 'denomination_normalise', length: 100, nullable: true)]
    private ?string $denominationNormalise = null;

    #[ORM\Column(name: 'code_dep', length: 3)]
    private string $codeDep = '';

    // --- adresse (champs valides par l'agent) ---

    #[ORM\Column(name: 'num_voie', length: 10, nullable: true)]
    private ?string $numVoie = null;

    #[ORM\Column(name: 'type_voie', length: 20, nullable: true)]
    private ?string $typeVoie = null;

    #[ORM\Column(name: 'nom_voie', length: 100, nullable: true)]
    private ?string $nomVoie = null;

    #[ORM\Column(name: 'code_postal', length: 5, nullable: true)]
    private ?string $codePostal = null;

    #[ORM\Column(length: 100)]
    private string $commune = '';

    #[ORM\Column(name: 'code_insee', length: 5, nullable: true)]
    private ?string $codeInsee = null;

    // --- horaires : horaire_brut (texte d'origine, lecture seule) et ce qui en a ete extrait ---

    #[ORM\Column(name: 'horaire_brut', length: 255, nullable: true)]
    private ?string $horaireBrut = null;

    /** @var Collection<int, MarcheHoraire> creneaux d'ouverture, un ou plusieurs par jour */
    #[ORM\OneToMany(targetEntity: MarcheHoraire::class, mappedBy: 'marche', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['jour' => 'ASC', 'heureDebut' => 'ASC', 'id' => 'ASC'])]
    private Collection $horaires;

    #[ORM\Column(name: 'toute_annee', nullable: true)]
    private ?bool $touteAnnee = null;

    /** Mois en minuscules ("juillet"), seulement si le marche n'a pas lieu toute l'annee. */
    #[ORM\Column(name: 'mois_debut', length: 10, nullable: true)]
    private ?string $moisDebut = null;

    #[ORM\Column(name: 'mois_fin', length: 10, nullable: true)]
    private ?string $moisFin = null;

    // --- produits (sans mention de jour ni d'horaire) ---

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $produits = null;

    /** Libelle libre qui qualifie le marche ("Marché bio", "hebdomadaire"...), valide par l'agent. */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $specificite = null;

    // --- geocodage (lecture seule pour l'agent) ---

    #[ORM\Column(nullable: true)]
    private ?float $longitude = null;

    #[ORM\Column(nullable: true)]
    private ?float $latitude = null;

    #[ORM\Column(name: 'precision', length: 20, nullable: true)]
    private ?string $precision = null;

    #[ORM\Column(name: 'id_ban', length: 20, nullable: true)]
    private ?string $idBan = null;

    /**
     * Cle du groupe de doublons ("id_<dep>_<n>"), partagee par les fiches liees ;
     * null tant que la fiche n'est liee a aucune autre.
     */
    #[ORM\Column(length: 30, nullable: true)]
    private ?string $codens = null;

    // --- etat de validation ---

    /** Ce que l'agent doit valider : produits seuls, ou existence complete (voir ActionType). */
    #[ORM\Column(name: 'action_type', length: 30, enumType: ActionType::class, options: ['default' => 'EXISTANCE_AVALIDER'])]
    private ActionType $actionType = ActionType::Existance;

    #[ORM\Column(name: 'nom_ok', options: ['default' => false])]
    private bool $nomOk = false;

    #[ORM\Column(name: 'adresse_ok', options: ['default' => false])]
    private bool $adresseOk = false;

    #[ORM\Column(name: 'produits_ok', options: ['default' => false])]
    private bool $produitsOk = false;

    #[ORM\Column(name: 'specificite_ok', options: ['default' => false])]
    private bool $specificiteOk = false;

    #[ORM\Column(name: 'horaire_ok', options: ['default' => false])]
    private bool $horaireOk = false;

    /**
     * Decision de l'agent sur une fiche "existence a valider" : true = garder, false = ne pas garder,
     * null = pas encore decide. Sans objet pour une specification a valider.
     */
    #[ORM\Column(name: 'garde', nullable: true)]
    private ?bool $garde = null;

    /** Fiche entierement traitee par l'agent. */
    #[ORM\Column(options: ['default' => false])]
    private bool $traite = false;

    /** Fiche mise de cote pour y revenir plus tard. */
    #[ORM\Column(options: ['default' => false])]
    private bool $rappel = false;

    #[ORM\Column(name: 'validated_by', nullable: true)]
    private ?int $validatedBy = null;

    #[ORM\Column(name: 'date_de_validation', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $dateDeValidation = null;

    public function __construct()
    {
        $this->horaires = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRawRowId(): ?string
    {
        return $this->rawRowId;
    }

    public function getUniqueId(): ?string
    {
        return $this->uniqueId;
    }

    public function getIdMarche(): ?int
    {
        return $this->idMarche;
    }

    public function getDenomination(): ?string
    {
        return $this->denomination;
    }

    public function getDenominationNormalise(): ?string
    {
        return $this->denominationNormalise;
    }

    public function getCodeDep(): string
    {
        return $this->codeDep;
    }

    public function getNumVoie(): ?string
    {
        return $this->numVoie;
    }

    public function getTypeVoie(): ?string
    {
        return $this->typeVoie;
    }

    public function getNomVoie(): ?string
    {
        return $this->nomVoie;
    }

    public function getCodePostal(): ?string
    {
        return $this->codePostal;
    }

    public function getCommune(): string
    {
        return $this->commune;
    }

    public function getCodeInsee(): ?string
    {
        return $this->codeInsee;
    }

    public function getHoraireBrut(): ?string
    {
        return $this->horaireBrut;
    }

    /** @return list<MarcheHoraire> */
    public function getHoraires(): array
    {
        return $this->horaires->toArray();
    }

    /** @return list<string> jours ouverts, dans l'ordre de la semaine */
    public function getJours(): array
    {
        $jours = [];
        foreach ($this->horaires as $h) {
            $jours[$h->getJour()] = $h->getNomJour();
        }
        ksort($jours);

        return array_values($jours);
    }

    /** @return list<MarcheHoraire> les creneaux d'un jour (nom en minuscules) */
    public function getHorairesDuJour(string $jour): array
    {
        return array_values(array_filter($this->getHoraires(), static fn (MarcheHoraire $h): bool => $h->getNomJour() === $jour));
    }

    public function getResumeHoraire(): string
    {
        return ResumeHoraire::resumer($this->horaires);
    }

    /**
     * Points a verifier sur les horaires, sans jamais bloquer : jour ouvert sans heure ni moment,
     * fin avant le debut (a moins d'une nocturne), jour du texte d'origine absent du tableau.
     *
     * @return list<string>
     */
    public function getAvertissementsHoraire(): array
    {
        $avertissements = [];
        foreach ($this->horaires as $h) {
            if ($h->isIncomplet()) {
                $avertissements[] = \sprintf('%s : aucune heure ni moment de la journée.', ucfirst($h->getNomJour()));
            } elseif (null !== $h->getHeureDebut() && null !== $h->getHeureFin() && $h->getHeureFin() <= $h->getHeureDebut()) {
                $avertissements[] = \sprintf('%s : la fin (%s) n\'est pas après le début (%s).', ucfirst($h->getNomJour()), $h->getHeureFin()->format('H:i'), $h->getHeureDebut()->format('H:i'));
            }
        }

        $jours = $this->getJours();
        $jourBrut = array_unique(array_map(static fn (array $c): string => MarcheHoraire::JOURS[$c['jour']], HoraireBrut::analyser($this->horaireBrut)['creneaux']));
        foreach (array_diff($jourBrut, $jours) as $manquant) {
            $avertissements[] = \sprintf('%s figure dans l\'horaire d\'origine mais pas dans le tableau.', ucfirst($manquant));
        }

        return $avertissements;
    }

    public function isTouteAnnee(): ?bool
    {
        return $this->touteAnnee;
    }

    public function getMoisDebut(): ?string
    {
        return $this->moisDebut;
    }

    public function getMoisFin(): ?string
    {
        return $this->moisFin;
    }

    public function getProduits(): ?string
    {
        return $this->produits;
    }

    public function getSpecificite(): ?string
    {
        return $this->specificite;
    }

    public function getLongitude(): ?float
    {
        return $this->longitude;
    }

    public function getLatitude(): ?float
    {
        return $this->latitude;
    }

    public function getPrecision(): ?string
    {
        return $this->precision;
    }

    public function getIdBan(): ?string
    {
        return $this->idBan;
    }

    public function getCodens(): ?string
    {
        return $this->codens;
    }

    public function setCodens(?string $codens): static
    {
        $this->codens = $codens;

        return $this;
    }

    public function isNomOk(): bool
    {
        return $this->nomOk;
    }

    /** Corrige le nom ; un nom modifie n'est plus considere comme valide. */
    public function changeDenomination(string $denomination): static
    {
        $this->denomination = $denomination;
        $this->nomOk = false;

        return $this;
    }

    public function setNomOk(bool $nomOk): static
    {
        $this->nomOk = $nomOk;

        return $this;
    }

    public function isAdresseOk(): bool
    {
        return $this->adresseOk;
    }

    public function getActionType(): ActionType
    {
        return $this->actionType;
    }

    /** Pas d'horaire_brut : rien a comparer, donc rien a verifier. */
    public function exigeValidationHoraire(): bool
    {
        return '' !== trim((string) $this->horaireBrut);
    }

    public function isHoraireOk(): bool
    {
        return $this->horaireOk;
    }

    /**
     * Remplace les creneaux ; un creneau identique a un autre est ignore. Ne touche ni a la
     * periode ni a la validation (voir changeHoraire).
     *
     * @param list<array{jour: int, moment: ?string, debut: ?\DateTimeImmutable, fin: ?\DateTimeImmutable}> $creneaux
     */
    public function remplacerCreneaux(array $creneaux): static
    {
        $this->horaires->clear();
        $vus = [];
        foreach ($creneaux as $c) {
            $cle = $c['jour'].'|'.$c['moment'].'|'.$c['debut']?->format('H:i').'|'.$c['fin']?->format('H:i');
            if (isset($vus[$cle])) {
                continue;
            }
            $vus[$cle] = true;
            $this->horaires->add(new MarcheHoraire($this, $c['jour'], $c['moment'], $c['debut'], $c['fin']));
        }

        return $this;
    }

    /**
     * Corrige les horaires ; une fois modifies, ils ne sont plus consideres comme valides.
     * Toute l'annee : les mois n'ont pas de sens et sont effaces.
     *
     * @param list<array{jour: int, moment: ?string, debut: ?\DateTimeImmutable, fin: ?\DateTimeImmutable}> $creneaux
     */
    public function changeHoraire(array $creneaux, bool $touteAnnee, ?string $moisDebut, ?string $moisFin): static
    {
        $this->remplacerCreneaux($creneaux);
        $this->touteAnnee = $touteAnnee;
        $this->moisDebut = $touteAnnee ? null : $moisDebut;
        $this->moisFin = $touteAnnee ? null : $moisFin;
        $this->horaireOk = false;

        return $this;
    }

    public function setHoraireOk(bool $horaireOk): static
    {
        $this->horaireOk = $horaireOk;

        return $this;
    }

    /** Pas de specificite : rien a verifier, tant que l'agent n'en ajoute pas. */
    public function exigeValidationSpecificite(): bool
    {
        return '' !== trim((string) $this->specificite);
    }

    public function isSpecificiteOk(): bool
    {
        return $this->specificiteOk;
    }

    /** Corrige la specificite ; une fois modifiee, elle n'est plus consideree comme validee. */
    public function changeSpecificite(?string $specificite): static
    {
        $this->specificite = $specificite;
        $this->specificiteOk = false;

        return $this;
    }

    public function setSpecificiteOk(bool $specificiteOk): static
    {
        $this->specificiteOk = $specificiteOk;

        return $this;
    }

    /** Produits vides : rien a verifier, tant que l'agent n'en ajoute pas. */
    public function exigeValidationProduits(): bool
    {
        return '' !== trim((string) $this->produits);
    }

    public function isProduitsOk(): bool
    {
        return $this->produitsOk;
    }

    /** Corrige les produits ; une fois modifies, ils ne sont plus consideres comme valides. */
    public function changeProduits(?string $produits): static
    {
        $this->produits = $produits;
        $this->produitsOk = false;

        return $this;
    }

    public function setProduitsOk(bool $produitsOk): static
    {
        $this->produitsOk = $produitsOk;

        return $this;
    }

    public function isGarde(): ?bool
    {
        return $this->garde;
    }

    public function setGarde(?bool $garde): static
    {
        $this->garde = $garde;

        return $this;
    }

    public function isTraite(): bool
    {
        return $this->traite;
    }

    public function isRappel(): bool
    {
        return $this->rappel;
    }

    public function getValidatedBy(): ?int
    {
        return $this->validatedBy;
    }

    public function getDateDeValidation(): ?\DateTimeImmutable
    {
        return $this->dateDeValidation;
    }

    /** Corrige l'adresse ; une adresse modifiee n'est plus consideree comme validee. */
    public function changeAdresse(?string $numVoie, ?string $typeVoie, ?string $nomVoie, ?string $codePostal, string $commune): static
    {
        $this->numVoie = $numVoie;
        $this->typeVoie = $typeVoie;
        $this->nomVoie = $nomVoie;
        $this->codePostal = $codePostal;
        $this->commune = $commune;
        $this->adresseOk = false;

        return $this;
    }

    public function changePosition(float $latitude, float $longitude): static
    {
        $this->latitude = $latitude;
        $this->longitude = $longitude;

        return $this;
    }

    public function setAdresseOk(bool $adresseOk): static
    {
        $this->adresseOk = $adresseOk;

        return $this;
    }

    public function setRappel(bool $rappel): static
    {
        $this->rappel = $rappel;

        return $this;
    }

    public function marquerTraite(int $userId, \DateTimeImmutable $at): static
    {
        $this->traite = true;
        $this->rappel = false;
        $this->validatedBy = $userId;
        $this->dateDeValidation = $at;

        return $this;
    }
}
