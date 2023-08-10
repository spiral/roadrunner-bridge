<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\GRPC\Generator;

use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Boot\EnvironmentInterface;
use Spiral\Config\ConfiguratorInterface;
use Spiral\Core\Container;
use Spiral\Core\InterceptableCore;
use Spiral\Files\FilesInterface;
use Spiral\Reactor\FileDeclaration;
use Spiral\Reactor\InterfaceDeclaration;
use Spiral\Reactor\Partial\Method;
use Spiral\Reactor\Partial\PhpNamespace;
use Spiral\Reactor\Writer;
use Spiral\RoadRunnerBridge\GRPC\Interceptor\ServiceClientCore;

final class BootloaderGenerator implements GeneratorInterface
{
    private const BOOTLOADER_NAME = 'ServiceBootloader';
    private const READONLY_COMMENT = 'Don\'t edit this method manually, it is generated by GRPC services generator.';

    public function __construct(
        private readonly FilesInterface $files
    ) {
    }

    /**
     * @param non-empty-string[] $files
     * @param non-empty-string $targetPath
     * @param non-empty-string $namespace
     */
    public function run(array $files, string $targetPath, string $namespace): void
    {
        $file = $this->files->exists($this->getPath($targetPath))
            ? FileDeclaration::fromCode($this->files->read($this->getPath($targetPath)))
            : $this->createBootloader($namespace);

        $this->updateInitConfigMethod($file, $files);
        $this->updateInitServicesMethod($file, $files);

        (new Writer($this->files))->write($this->getPath($targetPath), $file);
    }

    /**
     * @param non-empty-string[] $files
     */
    private function updateInitConfigMethod(FileDeclaration $file, array $files): void
    {
        $result = [];
        $port = 9000;
        foreach ($files as $service) {
            if (!\str_ends_with($service, 'Interface.php')) {
                continue;
            }

            $interfaceFile = FileDeclaration::fromCode($this->files->read($service));
            $interface = $interfaceFile->getInterfaces()->getIterator()->current();
            $interfaceName = $interface->getName();

            \assert($interfaceName !== null);

            $result[] = \sprintf(
                '%s::class => [\'host\' => $env->get(\'%s_HOST\', \'127.0.0.1:%d\')],',
                \str_replace('Interface', 'Client', $interfaceName),
                \strtoupper(\trim(implode(
                    '_',
                    \preg_split('/(?=[A-Z])/', \str_replace('Interface', '', $interfaceName))
                ), '_')),
                $port
            );
            $port++;
        }

        $body = \sprintf(
            <<<'EOL'
$this->config->setDefaults(
    GRPCServicesConfig::CONFIG,
    [
        'services' => [
            %s
        ],
    ]
);
EOL,
            \implode("\n\t\t\t", $result)
        );

        $file->getClass(self::BOOTLOADER_NAME)->getMethod('initConfig')->setBody($body);
    }

    /**
     * @param non-empty-string[] $files
     */
    private function updateInitServicesMethod(FileDeclaration $file, array $files): void
    {
        $method = $file->getClass(self::BOOTLOADER_NAME)->getMethod('initServices');
        $method->setBody('');
        
        foreach ($files as $service) {
            if (!\str_ends_with($service, 'Interface.php')) {
                continue;
            }

            $this->addSingleton($method, $file, $service);
        }
    }

    /**
     * @param non-empty-string $service
     */
    private function addSingleton(Method $servicesMethod, FileDeclaration $bootloader, string $service): void
    {
        $interfaceFile = FileDeclaration::fromCode($this->files->read($service));
        /** @var InterfaceDeclaration $interface */
        $interface = $interfaceFile->getInterfaces()->getIterator()->current();
        /** @var PhpNamespace $interfaceNamespace */
        $interfaceNamespace = $interfaceFile->getNamespaces()->getIterator()->current();
        /** @var PhpNamespace $namespace */
        $namespace = $bootloader->getNamespaces()->getIterator()->current();
        $clientClassName = \str_replace('Interface', 'Client', (string) $interface->getName());

        $interfaceName = $interface->getName();
        \assert($interfaceName !== null);

        $servicesMethod->addBody(
            \sprintf(
                <<<'EOL'
$container->bindSingleton(
    %s::class,
    static function(GRPCServicesConfig $config): %s
    {
        $service = $config->getService(%s::class);

        return new %s(
            new InterceptableCore(new ServiceClientCore(
                $service['host'],
                ['credentials' => $service['credentials'] ?? $config->getDefaultCredentials()]
            ))
        );
    }
);
EOL,
                $interfaceName,
                $interfaceName,
                $clientClassName,
                $clientClassName
            )
        );

        $namespace->addUse($interfaceNamespace->getName() . '\\' . $interface->getName());
        $namespace->addUse($interfaceNamespace->getName() . '\\' . $clientClassName);
    }

    private function createBootloader(string $namespace): FileDeclaration
    {
        $file = new FileDeclaration();
        $bootloaderNamespace = $file->addNamespace($namespace . '\\' . 'Bootloader');
        $bootloaderNamespace->addUse(Bootloader::class);
        $bootloader = $bootloaderNamespace->addClass(self::BOOTLOADER_NAME);
        $bootloader->setExtends(Bootloader::class);

        $bootloaderNamespace->addUse(ConfiguratorInterface::class);
        $bootloaderNamespace->addUse(ServiceClientCore::class);
        $bootloaderNamespace->addUse(InterceptableCore::class);

        $bootloader
            ->addMethod('__construct')
            ->addPromotedParameter('config')
            ->setReadOnly()
            ->setPrivate()
            ->setType(ConfiguratorInterface::class);

        $bootloaderNamespace->addUse(EnvironmentInterface::class);

        $bootloader
            ->addMethod('init')
            ->setReturnType('void')
            ->addBody('$this->initConfig($env);')
            ->addParameter('env')
            ->setType(EnvironmentInterface::class);

        $bootloaderNamespace->addUse(Container::class);

        $bootloader
            ->addMethod('boot')
            ->setReturnType('void')
            ->addBody('$this->initServices($container);')
            ->addParameter('container')
            ->setType(Container::class);

        $bootloader
            ->addMethod('initConfig')
            ->setReturnType('void')
            ->setPrivate()
            ->setComment(self::READONLY_COMMENT)
            ->addParameter('env')
            ->setType(EnvironmentInterface::class);

        $bootloader
            ->addMethod('initServices')
            ->setReturnType('void')
            ->setPrivate()
            ->setComment(self::READONLY_COMMENT)
            ->addParameter('container')
            ->setType(Container::class);

        $bootloaderNamespace->addUse($namespace . '\\Config\\' . 'GRPCServicesConfig');

        return $file;
    }

    private function getPath(string $targetPath): string
    {
        return \sprintf('%s/Bootloader/%s.php', $targetPath, self::BOOTLOADER_NAME);
    }
}
