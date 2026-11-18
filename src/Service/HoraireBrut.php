<?php

namespace App\Service;

use App\Entity\MarcheHoraire;

/**
 * Lit le texte d'origine d'un horaire (horaire_brut), qui suit une grammaire reguliere :
 *   Ce marché a lieu [toute l'année] [de Avril à Octobre] <segment>[; <segment>...]
 * ou un segment est "le mardi de 8h à 12h", "les mardi et samedi à partir de 7h30",
 * "les jours suivants : lundi, jeudi de 9h à 13h", "le samedi le matin" ou "le lundi".
 * Chaque segment peut avoir ses propres heures : c'est ce qui donne un horaire par jour.
 * Un segment non reconnu n'est jamais devine : il est rendu dans `nonReconnus`.
 */
final class HoraireBrut
{
    private const string JOUR = 'lundi|mardi|mercredi|jeudi|vendredi|samedi|dimanche';
    private const string HEURE = '(\d{1,2})\s?h\s?(\d{2})?';

    /**
     * @return array{creneaux: list<array{jour: int, moment: ?string, debut: ?string, fin: ?string}>, nonReconnus: list<string>}
     */
    public static function analyser(?string $brut): array
    {
        $texte = trim((string) $brut);
        $texte = preg_replace("~^ce march[ée] a lieu\s*~iu", '', $texte);
        $texte = preg_replace("~^toute l'ann[ée]e\s*~iu", '', $texte);
        $texte = preg_replace('~^de\s+\pL+\s+à\s+\pL+\s+(?=les?\b)~iu', '', $texte);

        $creneaux = [];
        $nonReconnus = [];
        foreach ('' === $texte ? [] : explode(';', $texte) as $segment) {
            $segment = trim($segment);
            if (null === $trouves = self::segment($segment)) {
                $nonReconnus[] = $segment;
                continue;
            }
            array_push($creneaux, ...$trouves);
        }

        return ['creneaux' => $creneaux, 'nonReconnus' => $nonReconnus];
    }

    /** @return list<array{jour: int, moment: ?string, debut: ?string, fin: ?string}>|null */
    private static function segment(string $segment): ?array
    {
        $jours = self::JOUR;
        $heure = self::HEURE;
        if (!preg_match("~^(?:le|les)\s+(?:jours suivants\s*:\s*)?((?:(?:$jours)(?:\s*,\s*|\s+et\s+)?)+)(.*)$~iu", $segment, $m)) {
            return null;
        }
        preg_match_all("~$jours~iu", $m[1], $noms);
        $reste = trim($m[2]);

        $moment = $debut = $fin = null;
        if ('' === $reste) {
            // jour seul : horaire inconnu
        } elseif (preg_match("~^de\s+$heure\s+à\s+$heure$~iu", $reste, $h)) {
            $debut = self::heure($h[1], $h[2]);
            $fin = self::heure($h[3], $h[4] ?? '');
        } elseif (preg_match("~^à partir de\s+$heure$~iu", $reste, $h)) {
            $debut = self::heure($h[1], $h[2] ?? '');
        } elseif (preg_match("~^(?:le|l')\s*(matin|apr[eè]s-midi|soir)$~iu", $reste, $h)) {
            $moment = str_starts_with(mb_strtolower($h[1]), 'apr') ? 'après-midi' : mb_strtolower($h[1]);
        } elseif (preg_match('~^toute la journ[ée]e$~iu', $reste)) {
            $moment = 'journée';
        } else {
            return null;
        }

        $flip = array_flip(MarcheHoraire::JOURS);
        $creneaux = [];
        foreach (array_unique(array_map('mb_strtolower', $noms[0])) as $nom) {
            $creneaux[] = ['jour' => $flip[$nom], 'moment' => $moment, 'debut' => $debut, 'fin' => $fin];
        }

        return $creneaux;
    }

    private static function heure(string $h, string $min): string
    {
        return \sprintf('%02d:%02d', (int) $h, '' === $min ? 0 : (int) $min);
    }
}
