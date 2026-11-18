<?php

namespace App\Tests\Service;

use App\Service\HoraireBrut;
use PHPUnit\Framework\TestCase;

final class HoraireBrutTest extends TestCase
{
    /** @param list<array{int, ?string, ?string, ?string}> $attendu jour, moment, debut, fin */
    /** @dataProvider cas */
    public function testAnalyse(string $brut, array $attendu, int $nonReconnus = 0): void
    {
        $r = HoraireBrut::analyser($brut);
        self::assertSame($attendu, array_map(static fn (array $c): array => [$c['jour'], $c['moment'], $c['debut'], $c['fin']], $r['creneaux']));
        self::assertCount($nonReconnus, $r['nonReconnus']);
    }

    public static function cas(): iterable
    {
        yield 'un jour' => ["Ce marché a lieu toute l'année le samedi de 8h à 13h", [[6, null, '08:00', '13:00']]];
        yield 'plusieurs jours, meme plage' => ["Ce marché a lieu toute l'année les jours suivants : mercredi, samedi de 8h à 13h", [[3, null, '08:00', '13:00'], [6, null, '08:00', '13:00']]];
        yield 'horaires differents par jour' => ['Ce marché a lieu les mardi et samedi de 9h à 20h30; le dimanche de 8h30 à 14h', [[2, null, '09:00', '20:30'], [6, null, '09:00', '20:30'], [7, null, '08:30', '14:00']]];
        yield 'a partir de' => ['Ce marché a lieu le mardi à partir de 7h13; les mercredi, jeudi et vendredi de 8h13 à 16h19', [[2, null, '07:13', null], [3, null, '08:13', '16:19'], [4, null, '08:13', '16:19'], [5, null, '08:13', '16:19']]];
        yield 'moment' => ["Ce marché a lieu de Juillet à Août les lundi et jeudi le matin", [[1, 'matin', null, null], [4, 'matin', null, null]]];
        yield 'apres-midi' => ["Ce marché a lieu toute l'année le jeudi l'après-midi", [[4, 'après-midi', null, null]]];
        yield 'journee' => ["Ce marché a lieu toute l'année le jeudi toute la journée", [[4, 'journée', null, null]]];
        yield 'jour seul' => ["Ce marché a lieu toute l'année les lundi et jeudi", [[1, null, null, null], [4, null, null, null]]];
        yield 'non reconnu' => ['Ce marché a lieu uniquement le 06/12/2026 de 12h à 19h', [], 1];
    }

    public function testVide(): void
    {
        self::assertSame(['creneaux' => [], 'nonReconnus' => []], HoraireBrut::analyser(null));
    }
}
