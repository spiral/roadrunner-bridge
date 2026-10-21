<?php

declare(strict_types=1);

namespace Spiral\Tests\Bootloader;

use Mockery as m;
use Spiral\Mailer\MailerInterface;
use Spiral\Queue\QueueConnectionProviderInterface;
use Spiral\Queue\QueueInterface;
use Spiral\SendIt\MailQueue;
use Spiral\Tests\TestCase;
use Testo\Assert;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;

final class MailerBootloaderTest extends TestCase
{
    /** @var m\LegacyMockInterface|m\MockInterface|QueueConnectionProviderInterface */
    private $queueProvider;

    #[Test]
    public function mailerInterfaceBinding(): void
    {
        $this->queueProvider->shouldReceive('getConnection')
            ->with('foo')
            ->andReturn(m::mock(QueueInterface::class));

        Assert::instanceOf($this->getContainer()->get(MailerInterface::class), MailQueue::class);
    }

    #[Test]
    public function jobRegistryShouldNotBeBound(): void
    {
        Assert::false($this->getContainer()->has('Spiral\Jobs\JobRegistry'));
    }

    #[BeforeTest]
    protected function prepare(): void
    {
        $this->queueProvider = $this->mockContainer(QueueConnectionProviderInterface::class);
    }
}
