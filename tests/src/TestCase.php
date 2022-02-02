<?php

declare(strict_types=1);

namespace Spiral\Tests;

use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase as BaseTestCase;
use Spiral\App\App;
use Spiral\Boot\DirectoriesInterface;
use Spiral\Boot\Environment;
use Spiral\Boot\EnvironmentInterface;
use Spiral\Boot\KernelInterface;
use Spiral\Config\Patch\Set;
use Spiral\Core\ConfigsInterface;
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
        $environment = new Environment($env);

        $root = dirname(__DIR__);

        /** @var App $app */
        $app = App::create([
            'root' => $root,
            'app' => $root.'/App',
            'runtime' => $root.'/runtime/tests',
            'cache' => $root.'/runtime/tests/cache',
        ]);

        $this->container = $app->getContainer();
        $app->getContainer()->bindSingleton(EnvironmentInterface::class, $environment);

        $app->starting(...$this->beforeBootload);
        $app->started(...$this->afterBootload);
        $app->run($environment);

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

    public function assertDispatcherLoaded(string $class): void
    {
        $this->assertContains($class, $this->getLoadedDispatchers());
    }

    public function assertDispatcherMissed(string $class): void
    {
        $this->assertNotContains($class, $this->getLoadedDispatchers());
    }

    public function assertBootloaderLoaded(string $class): void
    {
        $this->assertContains($class, $this->getLoadedBootloaders());
    }

    public function assertBootloaderMissed(string $class): void
    {
        $this->assertNotContains($class, $this->getLoadedBootloaders());
    }

    /**
     * @return string[]
     */
    public function getLoadedDispatchers(): array
    {
        return array_map(static function ($dispatcher) {
            return get_class($dispatcher);
        }, $this->accessProtected($this->app, 'dispatchers'));
    }

    /**
     * @return string[]
     */
    public function getLoadedBootloaders(): array
    {
        $bootloader = $this->accessProtected($this->app, 'bootloader');

        return $this->accessProtected($bootloader, 'classes');
    }
}
