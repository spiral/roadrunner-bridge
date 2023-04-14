<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\GRPC\Generator;

use Spiral\Core\InterceptableCore;
use Spiral\Files\FilesInterface;
use Spiral\Reactor\ClassDeclaration;
use Spiral\Reactor\FileDeclaration;
use Spiral\Reactor\InterfaceDeclaration;
use Spiral\Reactor\Partial\Method;
use Spiral\Reactor\Partial\PhpNamespace;
use Spiral\Reactor\Writer;
use Spiral\RoadRunner\GRPC\ContextInterface;

final class ServiceClientGenerator implements GeneratorInterface
{
    public function __construct(
        private readonly FilesInterface $files,
    ) {
    }

    public function run(array $files, string $targetPath, string $namespace): void
    {
        foreach ($files as $file) {
            if (!\str_ends_with($file, 'Interface.php')) {
                continue;
            }

            $interfaceFile = FileDeclaration::fromCode($this->files->read($file));

            /** @var InterfaceDeclaration $interface */
            $interface = $interfaceFile->getInterfaces()->getIterator()->current();

            /** @var PhpNamespace $interfaceNamespace */
            $interfaceNamespace = $interfaceFile->getNamespaces()->getIterator()->current();

            $clientFile = new FileDeclaration();
            /** @psalm-suppress PossiblyNullArgument */
            $clientNamespace = $clientFile->addNamespace($interfaceNamespace->getName());
            $clientNamespace->addUse($interfaceNamespace->getName() . '\\' . $interface->getName());
            $clientNamespace->addUse(ContextInterface::class);
            $clientNamespace->addUse(InterceptableCore::class);

            $client = $clientNamespace->addClass(\str_replace('Interface', 'Client', (string)$interface->getName()));
            $client->addImplement($interfaceNamespace->getName() . '\\' . $interface->getName());

            $constructor = $client->addMethod('__construct');
            $constructor->addPromotedParameter('core')
                ->setReadOnly()
                ->setPrivate()
                ->setType(InterceptableCore::class);

            foreach ($interface->getMethods() as $method) {
                $this->addMethodBody($method, $client, $interface);
            }

            (new Writer($this->files))->write(\str_replace('Interface', 'Client', $file), $clientFile);
        }
    }

    private function addMethodBody(Method $method, ClassDeclaration $client, InterfaceDeclaration $interface): void
    {
        $methodName = $method->getName();

        \assert($methodName !== null);

        $clientMethod = $client->addMethod($methodName);
        $clientMethod->setParameters($method->getParameters());
        $clientMethod->setReturnType($method->getReturnType());

        $clientMethod->addBody(
            \sprintf(
                <<<'EOL'
[$response, $status] = $this->core->callAction(%s::class, '/'.self::NAME.'/%s', [
    'in' => $in,
    'ctx' => $ctx,
    'responseClass' => %s::class,
]);

return $response;
EOL,
                (string)$interface->getName(),
                (string)$method->getName(),
                (string)$clientMethod->getReturnType(),
            ),
        );
    }
}
