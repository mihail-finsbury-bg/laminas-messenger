<?php

declare(strict_types=1);

namespace TMV\Laminas\Messenger\Factory\Transport;

use Psr\Container\ContainerInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;
use Symfony\Contracts\Service\ServiceProviderInterface;
use TMV\Laminas\Messenger\ServiceProvider;

class TransportProviderFactory
{
    public function __invoke(ContainerInterface $container): ServiceProviderInterface
    {
        $factories = [];

        /** @var array{messenger: array{transports?: array<string, mixed>}} $config */
        $config = $container->has('config') ? $container->get('config') : [];
        $transportNames = array_keys($config['messenger']['transports'] ?? []);

        foreach ($transportNames as $name) {
            $factories[$name] = static function () use ($name, $container): TransportInterface {
                /** @var TransportInterface $transport */
                $transport = $container->get($name);

                return $transport;
            };
        }

        return new ServiceProvider($factories);
    }
}
