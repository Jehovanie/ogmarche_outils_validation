<?php

namespace App\Tests\Command;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class MarcheImporterCommandTest extends KernelTestCase
{
    public function testImporteOuIndiqueQueLaTableEstDejaRemplie(): void
    {
        $application = new Application(self::bootKernel());
        $tester = new CommandTester($application->find('app:marche:importer'));

        // dans une transaction annulee par le test : ni echec SQL, ni donnees laissees en base
        $code = $tester->execute([]);

        self::assertSame(0, $code, $tester->getDisplay());
        self::assertMatchesRegularExpression('/fiche\(s\)/', $tester->getDisplay());
    }
}
