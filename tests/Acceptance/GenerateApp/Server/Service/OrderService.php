<?php

declare(strict_types=1);

namespace Spiral\Tests\Acceptance\GenerateApp\Server\Service;

use Acceptance\Shop\Order;
use Acceptance\Shop\OrderServiceInterface;
use Acceptance\Shop\PlaceOrderRequest;
use Spiral\RoadRunner\GRPC\ContextInterface;

/**
 * Implements the interface that `grpc:generate` writes: it exists only in the generated directory.
 */
final class OrderService implements OrderServiceInterface
{
    public function Place(ContextInterface $ctx, PlaceOrderRequest $in): Order
    {
        return (new Order())
            ->setId('order-of-' . $in->getCustomer()?->getId())
            ->setTotal($in->getTotal());
    }
}
