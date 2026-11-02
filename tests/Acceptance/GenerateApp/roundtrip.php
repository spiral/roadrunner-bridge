<?php

declare(strict_types=1);

/**
 * Builds messages from the generated classes, sends them through the binary and JSON encodings and prints
 * the results as JSON. Constructing a message registers its GPBMetadata and that of every file it imports.
 */

use Acceptance\Common\Money;
use Acceptance\Shop\Order;
use Acceptance\Types\Currency;
use AcceptanceExtra\Notes\Note;
use Google\Protobuf\Timestamp;

require \dirname(__DIR__, 3) . '/vendor/autoload.php';
require __DIR__ . '/autoload.php';

$order = (new Order())
    ->setId('order-1')
    ->setTotal((new Money())->setUnits(42)->setCurrency(Currency::EUR))
    ->setPlacedAt((new Timestamp())->setSeconds(1_700_000_000));

$binary = $order->serializeToString();
$decoded = new Order();
$decoded->mergeFromString($binary);

$note = new Note();
$note->mergeFromJsonString((new Note())->setText('hello')->serializeToJsonString());

echo \json_encode([
    'reencoded' => $decoded->serializeToString() === $binary,
    'id' => $decoded->getId(),
    'units' => $decoded->getTotal()?->getUnits(),
    'currency' => Currency::name($decoded->getTotal()?->getCurrency() ?? 0),
    'json' => $order->serializeToJsonString(),
    'note' => $note->getText(),
], \JSON_THROW_ON_ERROR);
