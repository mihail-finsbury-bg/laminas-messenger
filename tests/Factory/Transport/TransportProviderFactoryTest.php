<?php

declare(strict_types=1);

namespace TMV\Laminas\Messenger\Test\Factory\Transport;

use Laminas\ServiceManager\ServiceManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use TMV\Laminas\Messenger\Factory\Transport\TransportProviderFactory;

class TransportProviderFactoryTest extends TestCase
{
    public function testLocatorListsAndResolvesConfiguredTransports(): void
    {
        $transport = new InMemoryTransport();
        $container = new ServiceManager(['services' => [
            'config' => ['messenger' => ['transports' => ['async' => 'in-memory://']]],
            'async' => $transport,
        ]]);
        $locator = (new TransportProviderFactory())($container);
        self::assertSame(['async' => '?'], $locator->getProvidedServices());
        self::assertSame($transport, $locator->get('async'));
    }
}
