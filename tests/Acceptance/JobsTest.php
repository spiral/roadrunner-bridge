<?php

declare(strict_types=1);

namespace Spiral\Tests\Acceptance;

use RoadRunner\Jobs\DTO\V1\Pipelines;
use RoadRunner\Lock\Lock;
use Spiral\Goridge\RPC\Codec\ProtobufCodec;
use Spiral\RoadRunner\Jobs\Jobs;
use Spiral\RoadRunnerBridge\Config\QueueConfig;
use Spiral\Tests\Acceptance\Support\Http;
use Testo\Assert;
use Testo\Test;

#[Test]
final class JobsTest extends ServerTestCase
{
    private const PIPELINE = 'acceptance';

    public function consumesPushedJob(): void
    {
        Assert::true(self::eventually(self::consumedPipeline(...)) === true, self::$server->log());
        Assert::string(self::$server->log())->notContains('Unable to declare consumer pipeline');

        $this->assertConsumed();
    }

    public function consumesPipelineDeclaredByProducer(): void
    {
        Assert::true(self::eventually(self::consumedPipeline(...)) === true, self::$server->log());
        $rpc = self::$server->rpc();
        $rpc->withCodec(new ProtobufCodec())->call('jobs.Destroy', new Pipelines(['pipelines' => [self::PIPELINE]]));
        # Restarted HTTP workers read the pipeline list afresh, so the push declares the missing pipeline.
        $rpc->call('resetter.Reset', 'http');

        $this->assertConsumed();
    }

    public function resumesPipelineLeftPendingWhenConsumerBoots(): void
    {
        Assert::true(self::eventually(self::consumedPipeline(...)) === true, self::$server->log());
        $rpc = self::$server->rpc();
        # As a declarer leaves it when it dies between the declaration and the resume.
        (new Jobs($rpc))->pause(self::PIPELINE);
        (new Lock($rpc))->lock(QueueConfig::DEFAULT_LOCK_PREFIX . 'pending:' . self::PIPELINE);

        $rpc->call('resetter.Reset', 'jobs');

        Assert::true(self::eventually(self::consumedPipeline(...)) === true, self::$server->log());
        $this->assertConsumed();
    }

    /**
     * @return true|null Null while the pipeline is missing or paused.
     */
    private static function consumedPipeline(): ?bool
    {
        $jobs = new Jobs(self::$server->rpc());
        foreach ($jobs as $name => $queue) {
            if ($name === self::PIPELINE && !$queue->isPaused()) {
                return true;
            }
        }

        return null;
    }

    private function assertConsumed(): void
    {
        $name = \bin2hex(\random_bytes(4));

        $pushed = Http::request('GET', self::$server->httpUrl("/jobs/push?name=$name&value=done"));
        Assert::same($pushed?->status, 200, self::$server->log());
        Assert::notSame($pushed->body, '', 'the queue returns the job id');

        $file = self::$server->runtime . "/jobs/$name";
        $value = self::eventually(static fn(): ?string => \is_file($file) ? \file_get_contents($file) : null);

        Assert::same($value, 'done', self::$server->log());
    }
}
