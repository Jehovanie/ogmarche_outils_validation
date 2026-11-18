<?php

namespace App\Service;

use App\Entity\MarcheHoraire;

/**
 * Resume lisible des creneaux : les jours qui partagent la meme plage sont regroupes,
 * "Mar, Ven, Sam 7h30–12h30 · Dim 7h30–13h".
 */
final class ResumeHoraire
{
    /** @param iterable<MarcheHoraire> $creneaux */
    public static function resumer(iterable $creneaux): string
    {
        $parPlage = [];
        foreach ($creneaux as $c) {
            $plage = self::plage($c);
            $parPlage[$plage][$c->getJour()] = ucfirst(mb_substr($c->getNomJour(), 0, 3));
        }

        $morceaux = [];
        foreach ($parPlage as $plage => $jours) {
            ksort($jours);
            $morceaux[] = trim(implode(', ', $jours).' '.$plage);
        }

        return implode(' · ', $morceaux);
    }

    public static function plage(MarcheHoraire $c): string
    {
        $h = static fn (\DateTimeImmutable $t): string => '00' === $t->format('i') ? $t->format('G\h') : $t->format('G\hi');
        $debut = $c->getHeureDebut();
        $fin = $c->getHeureFin();

        $texte = match (true) {
            null !== $debut && null !== $fin => $h($debut).'–'.$h($fin),
            null !== $debut => 'dès '.$h($debut),
            default => '',
        };

        return trim($texte.(null !== $c->getMoment() ? ' '.$c->getMoment() : ''));
    }
}
