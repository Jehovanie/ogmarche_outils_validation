<?php

namespace App\Tests\Service;

use App\Service\CodePostalDepartement;
use PHPUnit\Framework\TestCase;

final class CodePostalDepartementTest extends TestCase
{
    /** @dataProvider cas */
    public function testCorrespondance(string $cp, string $dep, bool $attendu): void
    {
        self::assertSame($attendu, CodePostalDepartement::correspond($cp, $dep));
    }

    /** @return iterable<string, array{string, string, bool}> */
    public static function cas(): iterable
    {
        yield 'ain' => ['01000', '01', true];
        yield 'autre departement' => ['69000', '01', false];
        yield 'corse du sud' => ['20000', '2A', true];
        yield 'outre-mer' => ['97100', '971', true];
    }
}
