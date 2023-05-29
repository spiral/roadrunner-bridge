<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Scaffolder\Declaration;

use Spiral\RoadRunner\Tcp\Request;
use Spiral\RoadRunnerBridge\Tcp\Response\RespondMessage;
use Spiral\RoadRunnerBridge\Tcp\Response\ResponseInterface;
use Spiral\RoadRunnerBridge\Tcp\Service\ServiceInterface;
use Spiral\Scaffolder\Declaration\AbstractDeclaration;

class TcpServiceDeclaration extends AbstractDeclaration
{
    public const TYPE = 'tcp-service';

    public function declare(): void
    {
        $this->namespace->addUse(ServiceInterface::class);
        $this->namespace->addUse(Request::class);
        $this->namespace->addUse(RespondMessage::class);
        $this->namespace->addUse(ResponseInterface::class);

        $this->class->addImplement(ServiceInterface::class);
        $this->class->setFinal();

        $this->class->addMethod('handle')
            ->setPublic()
            ->addBody("return new RespondMessage('some message', true);")
            ->setReturnType(ResponseInterface::class)
            ->addParameter('request')
            ->setType(Request::class)
        ;
    }
}
