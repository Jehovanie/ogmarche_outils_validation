<?php

namespace App\Tests\Service;

use App\Entity\User;
use App\Service\Repartition;
use PHPUnit\Framework\TestCase;

final class RepartitionTest extends TestCase
{
    public function testEquilibreLesLignesEntreLesAgents(): void
    {
        [$a, $b] = [$this->agent('a'), $this->agent('b')];

        $plan = Repartition::planifier(['01' => 500, '02' => 300, '03' => 250, '04' => 200, '05' => 50], [$a, $b]);

        // 500 -> a ; 300 -> b ; 250 -> b (550) ; 200 -> a (700) ; 50 -> b (600)
        self::assertSame(['01', '04'], $plan[0]['departements']);
        self::assertSame(700, $plan[0]['lignes']);
        self::assertSame(['02', '03', '05'], $plan[1]['departements']);
        self::assertSame(600, $plan[1]['lignes']);
    }

    public function testChaqueDepartementVaAUnSeulAgent(): void
    {
        $agents = [$this->agent('a'), $this->agent('b'), $this->agent('c')];
        $lignes = ['01' => 40, '2A' => 35, '2B' => 30, '971' => 20, '99' => 10, '13' => 5];

        $plan = Repartition::planifier($lignes, $agents);

        $tous = array_merge(...array_column($plan, 'departements'));
        sort($tous);
        $attendus = array_map('strval', array_keys($lignes));
        sort($attendus);
        self::assertSame($attendus, $tous);
        self::assertSame(array_sum($lignes), array_sum(array_column($plan, 'lignes')));
    }

    public function testPlusDAgentsQueDeDepartements(): void
    {
        $plan = Repartition::planifier(['01' => 10], [$this->agent('a'), $this->agent('b')]);

        self::assertSame(['01'], $plan[0]['departements']);
        self::assertSame([], $plan[1]['departements']);
    }

    public function testSansAgentRienAPlanifier(): void
    {
        self::assertSame([], Repartition::planifier(['01' => 10], []));
    }

    private function agent(string $nom): User
    {
        return (new User())->setEmail("$nom@test.local");
    }
}
