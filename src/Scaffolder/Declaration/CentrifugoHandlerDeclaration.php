<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Scaffolder\Declaration;

use RoadRunner\Centrifugo\CentrifugoApiInterface;
use Spiral\Scaffolder\Declaration\AbstractDeclaration;

class CentrifugoHandlerDeclaration extends AbstractDeclaration
{
    public const TYPE = 'centrifugo-handler';

    public function declare(): void
    {
        $this->namespace->addUse(CentrifugoApiInterface::class);

        $this->class->setFinal();

        $this->class->addMethod('__construct')
            ->setPublic()
            ->addPromotedParameter('api')
            ->setReadOnly()
            ->setType(CentrifugoApiInterface::class)
            ->setPrivate()
        ;

        $this->class->addMethod('handle')->setPublic()->setReturnType('void');
    }
}
