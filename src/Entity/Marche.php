<?php

namespace App\Entity;

use App\Enum\ActionType;
use App\Repository\MarcheRepository;
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

    // --- produits (sans mention de jour ni d'horaire) ---

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $produits = null;

    /** Repris de l'import, ni affiche ni valide : il peut contenir des jours et horaires. */
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
