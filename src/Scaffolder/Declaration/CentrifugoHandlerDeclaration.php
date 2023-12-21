<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Scaffolder\Declaration;

use RoadRunner\Centrifugo\CentrifugoApiInterface;
use RoadRunner\Centrifugo\Request\RequestInterface;
use Spiral\RoadRunnerBridge\Centrifugo\ServiceInterface;
use Spiral\Scaffolder\Config\ScaffolderConfig;
use Spiral\Scaffolder\Declaration\AbstractDeclaration;
use Spiral\Scaffolder\Declaration\HasInstructions;

class CentrifugoHandlerDeclaration extends AbstractDeclaration implements HasInstructions
{
    public const TYPE = 'centrifugo-handler';

    public function __construct(
        ScaffolderConfig $config,
        string $name,
        ?string $comment = null,
        ?string $namespace = null,
        private bool $withApi = false,
    ) {
        parent::__construct($config, $name, $comment, $namespace);
    }

    public function declare(): void
    {
        $this->class->setFinal();

        if ($this->withApi) {
            $this->namespace->addUse(CentrifugoApiInterface::class);

            $this->class->addMethod('__construct')
                ->setPublic()
                ->addPromotedParameter('api')
                ->setPrivate()
                ->setReadOnly()
                ->setType(CentrifugoApiInterface::class);
        }
    }

    public function setType(array $data): void
    {
        $this->namespace->addUse($data['request']);
        $this->namespace->addUse(RequestInterface::class);
        $this->namespace->addUse(ServiceInterface::class);

        foreach ($data['use'] as $use) {
            $this->namespace->addUse($use);
        }

        $className = (new \ReflectionClass($data['request']))->getShortName();

        $this->class
            ->addImplement(ServiceInterface::class)
            ->addMethod('handle')
            ->setPublic()
            ->setBody($data['body'] ?? '// Put your code here')
            ->setReturnType('void')
            ->setComment("\n@param {$className} \$request")
            ->addParameter('request')
            ->setType(RequestInterface::class);
    }

    public function getInstructions(): array
    {
        return [
            'Register your handler in `app/config/centrifugo.php` file. Read more in the documentation: https://spiral.dev/docs/websockets-services#service-registration',
            'Read more about Centrifugo handlers: https://spiral.dev/docs/websockets-services',
        ];
    }
}
