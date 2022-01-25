<?php

declare(strict_types=1);

namespace Spiral\Tests;

use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase as BaseTestCase;
use Spiral\App\App;
use Spiral\Boot\AbstractKernel;
use Spiral\Boot\DirectoriesInterface;
use Spiral\Boot\Environment;
use Spiral\Boot\EnvironmentInterface;
use Spiral\Boot\KernelInterface;
use Spiral\Config\Patch\Set;
use Spiral\Core\ConfigsInterface;
use Spiral\Core\Container;
use Spiral\Files\Files;

abstract class TestCase extends BaseTestCase
{
    use MockeryPHPUnitIntegration;

    protected KernelInterface $app;
    protected \Spiral\Core\Container $container;
    private array $beforeBootload = [];
    private array $afterBootload = [];

    public const ENV = [];

    protected function setUp(): void
    {
        $this->container = new Container();

        parent::setUp();

        $this->app = $this->makeApp(static::ENV);
    }

    public function beforeBootload(\Closure $callback): void
    {
        $this->beforeBootload[] = $callback;
    }

    public function afterBootload(\Closure $callback): void
    {
        $this->afterBootload[] = $callback;
    }

    public function getConfig(string $config): array
    {
        return $this->app->get(ConfigsInterface::class)->getConfig($config);
    }

    public function setConfig(string $config, $data): void
    {
        $this->app->get(ConfigsInterface::class)->setDefaults(
            $config,
            $data
        );
    }

    public function updateConfig(string $key, $data): void
    {
        [$config, $key] = explode('.', $key, 2);

        $this->app->get(ConfigsInterface::class)->modify(
            $config,
            new Set($key, $data)
        );
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $fs = new Files();

        $runtime = $this->app->get(DirectoriesInterface::class)->get('runtime');
        if ($fs->isDirectory($runtime)) {
            $fs->deleteDirectory($runtime);
        }
    }

    private function makeApp(array $env = []): KernelInterface
    {
        $beforeBootload = $this->beforeBootload;
        $afterBootload = $this->afterBootload;

        $environment = new Environment($env);

        $root = dirname(__DIR__);

        $app = new App($this->container, [
            'root' => $root,
            'app' => $root.'/App',
            'runtime' => $root.'/runtime/tests',
            'cache' => $root.'/runtime/tests/cache',
        ]);

        $this->container->bindSingleton(EnvironmentInterface::class, $environment);

        $this->container->runScope(
            [EnvironmentInterface::class => $environment],
            \Closure::bind(function () use ($beforeBootload, $afterBootload): void {
                foreach ($beforeBootload as $callback) {
                    $callback($this->container);
                }

                $this->bootload();
                $this->bootstrap();

                foreach ($afterBootload as $callback) {
                    $callback($this->container);
                }
            }, $app, AbstractKernel::class)
        );

        return $app;
    }

    protected function accessProtected(object $obj, string $prop)
    {
        $reflection = new \ReflectionClass($obj);
        $property = $reflection->getProperty($prop);
        $property->setAccessible(true);

        return $property->getValue($obj);
    }

    public function assertContainerBound(string $alias, string $class): void
    {
        $this->assertInstanceOf(
            $class,
            $this->container->get($alias)
        );
    }

    public function assertContainerBoundAsSingleton(string $alias, string $class): void
    {
        $this->assertInstanceOf(
            $class,
            $object = $this->container->get($alias)
        );

        $this->assertSame($object, $this->container->get($alias));
    }

    public function getEnvironment(): EnvironmentInterface
    {
        return $this->container->get(EnvironmentInterface::class);
    }
}
