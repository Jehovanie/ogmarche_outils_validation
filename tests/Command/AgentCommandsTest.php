<?php

namespace App\Tests\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

/** Les commandes doivent etre reconnues par la version de Symfony installee (6.4 comme 8). */
final class AgentCommandsTest extends KernelTestCase
{
    private function commande(string $nom): CommandTester
    {
        return new CommandTester(new Application(self::bootKernel())->find($nom));
    }

    public function testCreerUnAdministrateurPuisRefuserUnDoublon(): void
    {
        $tester = $this->commande('app:user:create');

        $code = $tester->execute(['email' => 'admin-cmd@test.local', 'password' => 'motdepasse', '--name' => 'Admin Cmd', '--admin' => true]);
        self::assertSame(0, $code, $tester->getDisplay());

        $user = self::getContainer()->get(EntityManagerInterface::class)->getRepository(User::class)->findOneBy(['email' => 'admin-cmd@test.local']);
        self::assertSame('Admin Cmd', $user->getName());
        self::assertTrue($user->isAdmin());

        self::assertSame(1, $tester->execute(['email' => 'admin-cmd@test.local', 'password' => 'autre']));
        self::assertStringContainsString('existe déjà', $tester->getDisplay());
    }

    public function testAttribuerUnDepartementAUnAgent(): void
    {
        $this->commande('app:user:create')->execute(['email' => 'agent-cmd@test.local', 'password' => 'motdepasse']);

        // departement de test 99 : sans fiche, aucune commune a attribuer, mais la commande doit aboutir
        $tester = $this->commande('app:attribution:assign');
        self::assertSame(0, $tester->execute(['email' => 'agent-cmd@test.local', 'dep' => '99']), $tester->getDisplay());
        self::assertStringContainsString('attribué', $tester->getDisplay());

        self::assertSame(1, $tester->execute(['email' => 'inconnu@test.local', 'dep' => '99']));
        self::assertStringContainsString('introuvable', $tester->getDisplay());
    }
}
