<?php

declare(strict_types=1);

namespace TMV\Laminas\Messenger\Factory\Listener;

use Psr\Container\ContainerInterface;
use Symfony\Component\Messenger\EventListener\SendFailedMessageToFailureTransportListener;
use Symfony\Component\Messenger\Transport\Sender\SenderInterface;
use Symfony\Contracts\Service\ServiceProviderInterface;
use TMV\Laminas\Messenger\Exception\InvalidArgumentException;
use TMV\Laminas\Messenger\ServiceProvider;

/**
 * @psalm-api
 */
final class SendFailedMessageToFailureTransportListenerFactory
{
    public function __invoke(ContainerInterface $container): SendFailedMessageToFailureTransportListener
    {
        /** @var array<string, mixed> $config */
        $config = $container->has('config') ? $container->get('config') : [];

        /** @var string|null $failureTransportName */
        $failureTransportName = $config['messenger']['failure_transport'] ?? null;

        if (null === $failureTransportName) {
            throw new InvalidArgumentException('Invalid failure_transport name');
        }

        /** @var string|null $logger */
        $logger = $config['messenger']['logger'] ?? null;

        /** @var ServiceProviderInterface $receivers */
        $receivers = $container->get('messenger.receivers_locator');
        $senders = [];
        foreach ($receivers->getProvidedServices() as $receiverName => $_) {
            $senders[$receiverName] = static function () use ($container, $failureTransportName): SenderInterface {
                /** @var SenderInterface $sender */
                $sender = $container->get($failureTransportName);

                return $sender;
            };
        }

        return new SendFailedMessageToFailureTransportListener(
            new ServiceProvider($senders),
            $logger && $container->has($logger) ? $container->get($logger) : null,
        );
    }
}
