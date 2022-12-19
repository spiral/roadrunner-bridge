<?php

namespace GRPC\Users\v1;

use Spiral\RoadRunner\GRPC;

interface UsersServiceInterface extends GRPC\ServiceInterface
{
    // GRPC specific service name.
    public const NAME = "api.users.v1.UsersService";

    /**
    * @param GRPC\ContextInterface $ctx
    * @param \GRPC\Users\v1\DTO\AuthRequest $in
    * @return \GRPC\Users\v1\DTO\AuthResponse
    *
    * @throws GRPC\Exception\InvokeException
    */
    public function Auth(GRPC\ContextInterface $ctx, \GRPC\Users\v1\DTO\AuthRequest $in): \GRPC\Users\v1\DTO\AuthResponse;

    /**
    * @param GRPC\ContextInterface $ctx
    * @param \GRPC\Users\v1\DTO\RegisterRequest $in
    * @return \GRPC\Users\v1\DTO\RegisterResponse
    *
    * @throws GRPC\Exception\InvokeException
    */
    public function Register(GRPC\ContextInterface $ctx, \GRPC\Users\v1\DTO\RegisterRequest $in): \GRPC\Users\v1\DTO\RegisterResponse;

    /**
    * @param GRPC\ContextInterface $ctx
    * @param \GRPC\Users\v1\DTO\GetRequest $in
    * @return \GRPC\Users\v1\DTO\GetResponse
    *
    * @throws GRPC\Exception\InvokeException
    */
    public function Get(GRPC\ContextInterface $ctx, \GRPC\Users\v1\DTO\GetRequest $in): \GRPC\Users\v1\DTO\GetResponse;
}
