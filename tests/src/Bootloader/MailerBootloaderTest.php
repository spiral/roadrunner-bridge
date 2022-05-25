<?php

declare(strict_types=1);

namespace Spiral\Tests\Bootloader;

use Mockery as m;
use Spiral\Mailer\MailerInterface;
use Spiral\Queue\QueueConnectionProviderInterface;
use Spiral\Queue\QueueInterface;
use Spiral\SendIt\MailQueue;
use Spiral\Tests\TestCase;

final class MailerBootloaderTest extends TestCase
{
    /** @var \Mockery\LegacyMockInterface|\Mockery\MockInterface|QueueConnectionProviderInterface */
    private $queueProvider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->queueProvider = $this->mockContainer(QueueConnectionProviderInterface::class);
    }

    public function testMailerInterfaceBinding(): void
    {
        $this->queueProvider->shouldReceive('getConnection')
            ->with('foo')
            ->andReturn(m::mock(QueueInterface::class));

        $this->assertInstanceOf(
            MailQueue::class,
            $this->getContainer()->get(MailerInterface::class)
        );
    }

    public function testJobRegistryShouldNotBeBound(): void
    {
        $this->assertFalse($this->getContainer()->has('Spiral\Jobs\JobRegistry'));
    }
}
