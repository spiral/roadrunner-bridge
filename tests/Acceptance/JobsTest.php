<?php

declare(strict_types=1);

namespace Spiral\Tests\Acceptance;

use Spiral\RoadRunner\Jobs\Jobs;
use Spiral\Tests\Acceptance\Support\Http;
use Testo\Assert;
use Testo\Skip;
use Testo\Test;

#[Test]
final class JobsTest extends ServerTestCase
{
    private const PIPELINE = 'acceptance';

    public function consumesPushedJob(): void
    {
        # Pushing before the jobs worker has declared the pipeline would leave it paused, see the skipped test below.
        Assert::true(self::eventually(self::consumedPipeline(...)) === true, self::$server->log());

        $this->assertConsumed();
    }

    #[Skip('Bug: declareConsumerPipelines() skips an existing paused pipeline, so a pipeline that a producer declared first is never consumed.')]
    public function resumesPausedPipelineWhenConsumerBoots(): void
    {
        Assert::true(self::eventually(self::consumedPipeline(...)) === true, self::$server->log());
        $rpc = self::$server->rpc();
        # Leaves the pipeline as `jobs.Declare` from a producer worker creates it: present but paused.
        (new Jobs($rpc))->pause(self::PIPELINE);

        $rpc->call('resetter.Reset', 'jobs');

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
