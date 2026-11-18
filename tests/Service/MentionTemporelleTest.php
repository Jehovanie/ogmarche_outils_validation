<?php

namespace App\Tests\Service;

use App\Service\MentionTemporelle;
use PHPUnit\Framework\TestCase;

final class MentionTemporelleTest extends TestCase
{
    /**
     * @dataProvider cas
     *
     * @param list<string> $attendu
     */
    public function testDetecter(?string $texte, array $attendu): void
    {
        self::assertSame($attendu, MentionTemporelle::detecter($texte));
    }

    /** @return iterable<string, array{?string, list<string>}> */
    public static function cas(): iterable
    {
        yield 'vide' => [null, []];
        yield 'produits seuls' => ['Fruits, légumes, fromages et charcuterie', []];
        yield 'jour et plage horaire' => ['tous les Samedis 8h-12h30 Produits locaux', ['Samedis', '8h', '12h30']];
        yield 'heures avec deux-points' => ['de 8:30 à 13:00', ['8:30', '13:00']];
        yield 'moment de la journee' => ["Le jeudi le marché a lieu l'après midi.", ['jeudi', 'après midi']];
        yield 'mois et periodicite' => ["2èmes dimanches matins des mois d'avril à octobre", ['dimanches', 'matins', 'avril', 'octobre']];
        yield 'date' => ['15/06 AU 15/09', ['15/06', '15/09']];
        yield 'majuscules sans doublon' => ['MARCHE HEBDOMADAIRE, hebdomadaire', ['HEBDOMADAIRE']];
        // pas de faux positif dans un mot plus long
        yield 'mot contenant un jour' => ['Produits de la mairie, marsala', []];
    }
}
