# laminas-messenger

Factories to use Symfony Messenger in Laminas and Mezzio applications.

This maintained fork of `thomasvargiu/laminas-messenger` targets PHP 8.2+ and
Symfony Messenger, Console, and EventDispatcher 7.4. It preserves the
`TMV\Laminas\Messenger` namespace and existing module/configuration API.

Install `mihail-finsbury-bg/laminas-messenger` in place of the upstream package.
Until a release is published, configure this fork as a Composer VCS repository
and require its actual branch or commit. Do not install both packages: they
provide the same PHP classes. No `replace` declaration is made for upstream
versions that this fork no longer supports.

Install the matching `^7.4` transport bridge separately when using Doctrine,
AMQP, or Redis. These bridges are development dependencies of this library,
not runtime dependencies automatically installed for consumers.

## Usage

You need to add console commands to your application. The following command services are already configured for you:

- `Symfony\Component\Messenger\Command\ConsumeMessagesCommand`
- `Symfony\Component\Messenger\Command\SetupTransportsCommand`
- `Symfony\Component\Messenger\Command\FailedMessagesRemoveCommand`
- `Symfony\Component\Messenger\Command\FailedMessagesRetryCommand`
- `Symfony\Component\Messenger\Command\FailedMessagesShowCommand`
- `Symfony\Component\Messenger\Command\StopWorkersCommand` (see note below)

To use the `Symfony\Component\Messenger\Command\StopWorkersCommand` command you should set a `CacheItemPoolInterface`
implementation (see below).

A default message bus is already configured for you with the following service name: `messenger.bus.default`.
You can read the [Symfony documentation](https://symfony.com/doc/current/components/messenger.html) to know
how to use it.

## Configuration

This is an example configuration:

```php
use TMV\Laminas\Messenger\Factory;
use Symfony\Component\Messenger;

return [
    'dependencies' => [
        'factories' => [
            'messenger.bus.foo' => [Factory\MessageBusFactory::class, 'messenger.bus.foo'], // the name must be the same as the bus configuration key
            'messenger.transport.async' => [Factory\Transport\TransportFactory::class, 'messenger.transport.async'], // the name must be the same as the transport configuration key
        ],
    ],
    'messenger' => [
        'failure_transport' => null, // your failure transport service name (optional)
        'logger' => null, // your custom logger service name (optional)
        'default_serializer' => Messenger\Transport\Serialization\PhpSerializer::class, // default messenger serializer, it should be a service name
        'cache_pool_for_restart_signal' => null, // CacheItemPoolInterface service name implementation if you want to use stop workers command
        'transport_factories' => [
            // here you can add your custom transport factories services
        ],
        'subscribers' => [], // Subscribers (service name) to use with the default event dispatcher factory
        'buses' => [
            'messenger.bus.foo' => [ // bus service name, it should be registered as a service with the same name
                'default_middleware' => true, // if you want to include default middleware (default: true)
                'middleware' => [ // your custom middleware service names
                    My\FooMiddleware::class,
                ],
                'allow_no_handler' => false, // allow no handlers (default: false)
                'handlers' => [ // your handlers
                    My\FooMessageType::class => [
                        My\FooMessageHandler::class,
                    ],
                ],
                'routes' => [
                    My\FooMessageType::class => ['messenger.transport.async'], // route message types to this transport
                ],
            ],
        ],
        'transports' => [
            'messenger.transport.async' => [
                'dsn' => 'amqp://guest:guest@rabbitmq:5672',
                'serializer' => Messenger\Transport\Serialization\PhpSerializer::class, // custom serializer service
                'options' => [
                    'exchange' => [
                        'name' => 'messenger_events',
                    ],
                    'queues' => [
                        'messenger_events' => [],
                    ],
                ],
                'retry_strategy' => [
                    'max_retries' => 3,
                    'delay' => 1000,
                    'multiplier' => 2,
                    'max_delay' => 0,
                ],
            ],
        ],
    ],
];
```

## Doctrine Helpers

### Middlewares
There are some middleware available for Doctrine:

#### `TMV\Laminas\Messenger\Middleware\DoctrineCloseConnectionMiddleware`

Close connection on every message consumed by the worker.

#### `TMV\Laminas\Messenger\Middleware\DoctrinePingConnectionMiddleware`

Before to handle a message on worker, ping to check whether the connection is open or try to reconnect it.

#### `TMV\Laminas\Messenger\Middleware\DoctrineTransactionMiddleware`

For every message, wrap the message handler with a transaction.

### Subscribers

#### `TMV\Laminas\Messenger\Subscriber\DoctrineClearEntityManagerWorkerSubscriber`

This subscriber clear the EntityManager after a message is handled in a worker.

## Worker lifecycle

Messenger Console commands handle SIGTERM and SIGINT gracefully when PHP's PCNTL
extension is available. Run workers through a Symfony Console application (or
Laminas CLI); the removed `StopWorkerOnSigtermSignalListener` is no longer registered.

The global `failure_transport` remains a transport service name. Failures from
all configured transport and receiver names go to that sender after retries
are exhausted. The failure transport must also be a configured receiver to use
the failed-message show, retry, and remove commands.

Container resets and keepalive are not enabled by this integration. Register
Doctrine middleware/subscribers explicitly when needed; upgrading Messenger
does not add entity-manager cleanup automatically. Existing `PhpSerializer`
configuration is preserved.

## Development

Run `composer validate --strict`, `composer test`, `composer cs-check`, and
`composer psalm` with PHP 8.2+ and the development dependencies installed.
AMQP requires `ext-amqp`; PCNTL and POSIX are needed for signal regression tests.
Worker integration tests use in-memory transports and require no broker.
