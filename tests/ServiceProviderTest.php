<?php

declare(strict_types=1);

namespace TMV\Laminas\Messenger\Test;

use Laminas\ServiceManager\Exception\ServiceNotFoundException;
use PHPUnit\Framework\TestCase;
use TMV\Laminas\Messenger\ServiceProvider;

class ServiceProviderTest extends TestCase
{
    public function testProvidedServicesCanBeInspectedWithoutCreatingThem(): void
    {
        $calls = 0;
        $service = new \stdClass();
        $provider = new ServiceProvider(['async' => static function () use (&$calls, $service) {
            ++$calls;

            return $service;
        }]);
        self::assertSame(['async' => '?'], $provider->getProvidedServices());
        self::assertSame(0, $calls);
        self::assertTrue($provider->has('async'));
        self::assertFalse($provider->has('missing'));
        self::assertSame($service, $provider->get('async'));
        self::assertSame(1, $calls);
    }

    public function testMissingServiceThrowsContainerException(): void
    {
        $this->expectException(ServiceNotFoundException::class);
        (new ServiceProvider([]))->get('missing');
    }
}
