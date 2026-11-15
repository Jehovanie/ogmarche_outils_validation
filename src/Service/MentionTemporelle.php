<?php

namespace App\Service;

/**
 * Repere les mentions de temps (jour, heure, moment de la journee, mois, periodicite) dans
 * un texte libre. Les produits d'un marche ne doivent pas en contenir :
 * les jours et horaires ont leurs propres colonnes. Le resultat sert d'avertissement a
 * l'agent, qui retire lui-meme ces mentions. Meme liste dans public/js/validation.js.
 */
final class MentionTemporelle
{
    private const MOTIFS = [
        // jours : lundi, samedis
        '(?:lundi|mardi|mercredi|jeudi|vendredi|samedi|dimanche)s?',
        // heures : 8h, 8 h 30, 15h30, 10 heures, 8:30
        '\d{1,2}\s?(?:h|heures?)(?:\s?\d{2})?',
        '\d{1,2}:\d{2}',
        // moments de la journee
        'matin(?:s|ée|ées)?', 'apr[eè]s[- ]midi', 'soir(?:s|ée|ées)?', 'midi', 'nocturnes?',
        // mois
        'janvier', 'f[ée]vrier', 'mars', 'avril', 'mai', 'juin', 'juillet', 'ao[uû]t', 'septembre', 'octobre', 'novembre', 'd[ée]cembre',
        // periodicite et dates : hebdomadaire, du mois, 15/06
        'hebdomadaires?', 'mensuel(?:le)?s?', 'quotidien(?:ne)?s?', 'du mois', 'chaque mois', 'semaines?', "toute l'ann[ée]e",
        '\d{1,2}/\d{1,2}',
    ];

    /** @return list<string> les mentions trouvees, sans doublon, dans l'ordre du texte */
    public static function detecter(?string $texte): array
    {
        if (null === $texte || '' === trim($texte)) {
            return [];
        }

        // (?<!\pL) / (?!\pL) plutot que \b, qui ne gere pas les lettres accentuees
        $regex = '~(?<![\pL\d])(?:'.implode('|', self::MOTIFS).')(?![\pL\d])~iu';
        preg_match_all($regex, $texte, $trouves, \PREG_OFFSET_CAPTURE);

        $mentions = [];
        foreach ($trouves[0] as [$mention]) {
            $mentions[mb_strtolower($mention)] ??= $mention;
        }

        return array_values($mentions);
    }
}
