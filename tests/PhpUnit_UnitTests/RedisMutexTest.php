<?php

namespace UnitTests;

use M6Web\Component\RedisMock\RedisMockFactory;
use PHPUnit\Framework\TestCase;
use Predis\ClientInterface;
use Soarce\RedisMutex;

class RedisMutexTest extends TestCase
{
    public function testSeeding()
    {
        $redisMock = $this->getRedisMock();
        $redisMutex = new RedisMutex($redisMock, 'test', 3);
        $redisMutex->seed();

        $keys = $redisMock->keys('lock:*');
        $this->assertCount(3, $keys);

        $redisMutex->clean();

        $keys = $redisMock->keys('lock:*');
        $this->assertCount(0, $keys);
    }

    public function testLocking()
    {
        $this->markTestIncomplete('This needs to become an integration test one day as "brpop" is understandably not mockable.');

        $redisMock = $this->getRedisMock();
        $redisMutex = new RedisMutex($redisMock, 'test', 3);
        $redisMutex->seed();

        $locks = array(
            $redisMutex->obtainLock(),
            $redisMutex->obtainLock(),
            $redisMutex->obtainLock(),
        );

        sort($locks);

        $this->assertEquals(array(1, 2, 3), $locks);
    }

    /**
     * @return ClientInterface
     */
    private function getRedisMock()
    {
        $factory   = new RedisMockFactory();

        /** @var ClientInterface $redisMock */
        $redisMock = $factory->getAdapter('\Predis\Client', true);

        return $redisMock;
    }
}