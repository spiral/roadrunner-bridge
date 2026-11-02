<?php

declare(strict_types=1);

namespace Spiral\Tests\Acceptance;

use Acceptance\Common\Money;
use Acceptance\Shop\Order;
use Acceptance\Shop\PlaceOrderRequest;
use Acceptance\Types\Currency;
use Acceptance\Types\Customer;
use Spiral\Tests\Acceptance\Support\GenerateProject;
use Spiral\Tests\Acceptance\Support\Http;
use Spiral\Tests\Acceptance\Support\RoadRunner;
use Testo\Assert;
use Testo\Lifecycle\AfterClass;
use Testo\Lifecycle\BeforeClass;
use Testo\Test;

/**
 * RoadRunner serves `GenerateApp/Server/Service/OrderService`, which implements an interface that `grpc:generate`
 * writes from `shop/order.proto` before the server starts.
 */
#[Test]
final class GrpcGeneratedServiceTest
{
    private static ?GenerateProject $project = null;
    private static ?RoadRunner $server = null;

    #[BeforeClass]
    public static function generateAndServe(): void
    {
        self::$project = GenerateProject::create(['services' => ['proto/shop/order.proto']]);
        $generated = self::$project->generate(['--no-ansi']);
        $generated->exitCode === 0 or throw new \RuntimeException("grpc:generate failed:\n" . $generated->output);

        # The test process decodes the reply with the same classes.
        self::autoload(self::$project->root . '/generated/');

        self::$server = RoadRunner::start(
            GenerateProject::APP_DIR . '/Server',
            [
                'GENERATE_ROOT' => self::$project->root,
                'GENERATED_AUTOLOAD' => \json_encode(['Acceptance\\' => self::$project->root . '/generated/']),
            ],
            ['grpc'],
        );
    }

    #[AfterClass]
    public static function stop(): void
    {
        self::$server?->stop();
        self::$project?->remove();
    }

    public function callsGeneratedServiceMethod(): void
    {
        $message = (new PlaceOrderRequest())
            ->setCustomer((new Customer())->setId('c-7'))
            ->setTotal((new Money())->setUnits(15)->setCurrency(Currency::USD))
            ->serializeToString();

        $response = Http::request(
            'POST',
            self::$server->grpcUrl('/acceptance.shop.OrderService/Place'),
            # gRPC frame: an uncompressed flag byte and the big-endian message length.
            "\0" . \pack('N', \strlen($message)) . $message,
            ['Content-Type' => 'application/grpc', 'TE' => 'trailers'],
            http2: true,
        );

        Assert::same($response?->status, 200, self::$server->log());
        Assert::same($response->header('grpc-status'), '0', self::$server->log());
        $order = new Order();
        $order->mergeFromString(\substr($response->body, 5));
        Assert::same($order->getId(), 'order-of-c-7');
        Assert::same($order->getTotal()?->getUnits(), 15);
        Assert::same($order->getTotal()?->getCurrency(), Currency::USD);
    }

    private static function autoload(string $dir): void
    {
        \spl_autoload_register(static function (string $class) use ($dir): void {
            if (\str_starts_with($class, 'Acceptance\\')) {
                $file = $dir . \str_replace('\\', '/', \substr($class, \strlen('Acceptance\\'))) . '.php';
                \is_file($file) and require $file;
            }
        });
    }
}
