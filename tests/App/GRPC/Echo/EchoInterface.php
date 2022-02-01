<?php

declare(strict_types=1);
# Generated by the protocol buffer compiler (spiral/php-grpc). DO NOT EDIT!
# source: echo.proto

namespace Spiral\App\GRPC\Echo;

use Spiral\RoadRunner\GRPC;

interface EchoInterface extends GRPC\ServiceInterface
{
    // GRPC specific service name.
    public const NAME = 'service.Echo';

    /**
    * @param GRPC\ContextInterface $ctx
    * @param Message $in
    *
    * @throws GRPC\Exception\InvokeException
    *
    * @return Message
    */
    public function Ping(GRPC\ContextInterface $ctx, Message $in): Message;
}
