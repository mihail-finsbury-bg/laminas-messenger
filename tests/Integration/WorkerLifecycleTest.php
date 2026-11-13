<?php

declare(strict_types=1);

namespace TMV\Laminas\Messenger\Test\Integration;

use Laminas\ServiceManager\ServiceManager;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Console\Tester\ApplicationTester;
use Symfony\Component\Console\Application;
use Symfony\Component\Messenger\Command\ConsumeMessagesCommand;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerRunningEvent;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Stamp\SentToFailureTransportStamp;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use TMV\Laminas\Messenger\ConfigProvider;
use TMV\Laminas\Messenger\Factory\Transport\TransportFactory;

/**
 * @coversNothing
 */
class WorkerLifecycleTest extends TestCase
{
    /** @dataProvider receiverNames */
    public function testWorkerRetriesThenSendsToGlobalFailureTransport(string $receiverName): void
    {
        $attempts = 0;
        $container = $this->createContainer(static function () use (&$attempts): void {
            ++$attempts;
            throw new RuntimeException('Handler failed');
        });
        $dispatcher = $container->get('messenger.event_dispatcher');
        $dispatcher->addListener(WorkerRunningEvent::class, static function (WorkerRunningEvent $event): void {
            if ($event->isWorkerIdle()) {
                $event->getWorker()->stop();
            }
        });

        /** @var InMemoryTransport $transport */
        $transport = $container->get($receiverName);
        $transport->send(new Envelope(new \stdClass()));
        $tester = new CommandTester($container->get(ConsumeMessagesCommand::class));
        self::assertSame(0, $tester->execute(['receivers' => [$receiverName]], ['interactive' => false]));

        self::assertSame(4, $attempts);
        self::assertCount(3, $transport->getAcknowledged());
        self::assertCount(1, $transport->getRejected());
        self::assertCount(0, iterator_to_array($transport->get()));
        $failed = $container->get('failed')->getSent();
        self::assertCount(1, $failed);
        self::assertSame($receiverName, $failed[0]->last(SentToFailureTransportStamp::class)->getOriginalReceiverName());
        self::assertSame(0, RedeliveryStamp::getRetryCountFromEnvelope($failed[0]));
        self::assertSame([1, 2, 3, 0], array_map(static fn(RedeliveryStamp $stamp): int => $stamp->getRetryCount(), $failed[0]->all(RedeliveryStamp::class)));
    }

    public static function receiverNames(): array
    {
        return [['async'], ['other']];
    }

    public function testWorkerHandlesAndAcknowledgesMessages(): void
    {
        $handled = [];
        $container = $this->createContainer(static function (\stdClass $message) use (&$handled): void {
            $handled[] = $message;
        });
        /** @var MessageBusInterface $bus */
        $bus = $container->get('messenger.bus.default');
        $bus->dispatch(new \stdClass());
        $bus->dispatch(new \stdClass());
        $tester = new CommandTester($container->get(ConsumeMessagesCommand::class));
        self::assertSame(0, $tester->execute(['receivers' => ['async'], '--limit' => 2], ['interactive' => false]));
        self::assertCount(2, $handled);
        self::assertCount(2, $container->get('async')->getAcknowledged());
        self::assertCount(0, $container->get('failed')->getSent());
    }

    /**
     * @dataProvider stopSignals
     *
     * @runInSeparateProcess
     *
     * @preserveGlobalState disabled
     */
    public function testSignalFinishesActiveMessageAndLeavesNextMessageQueued(int $signal): void
    {
        if (! function_exists('pcntl_signal') || ! function_exists('posix_kill')) {
            self::markTestSkipped('PCNTL and POSIX are required');
        }
        $handled = 0;
        $container = $this->createContainer(static function () use ($signal, &$handled): void {
            posix_kill(getmypid(), $signal);
            ++$handled;
        });
        $bus = $container->get('messenger.bus.default');
        $bus->dispatch(new \stdClass());
        $bus->dispatch(new \stdClass());
        $application = new Application();
        $application->setAutoExit(false);
        $application->setCatchExceptions(false);
        $application->add($container->get(ConsumeMessagesCommand::class));
        $tester = new ApplicationTester($application);
        self::assertSame(0, $tester->run([
            'command' => 'messenger:consume',
            'receivers' => ['async'],
            '--limit' => 2,
        ], ['interactive' => false]));
        self::assertSame(1, $handled);
        self::assertCount(1, $container->get('async')->getAcknowledged());
        self::assertCount(1, $container->get('async')->get());
    }

    public static function stopSignals(): array
    {
        return [[15], [2]];
    }

    private function createContainer(callable $handler): ServiceManager
    {
        $config = (new ConfigProvider())();
        $config['messenger']['logger'] = null;
        $config['messenger']['failure_transport'] = 'failed';
        $config['messenger']['buses']['messenger.bus.default']['handlers'] = [\stdClass::class => [$handler]];
        $config['messenger']['buses']['messenger.bus.default']['routes'] = [\stdClass::class => ['async']];
        foreach (['async', 'other', 'failed'] as $name) {
            $config['messenger']['transports'][$name] = [
                'dsn' => 'in-memory://',
                'retry_strategy' => ['max_retries' => 3, 'delay' => 0],
            ];
            $config['dependencies']['factories'][$name] = [TransportFactory::class, $name];
        }
        $container = new ServiceManager($config['dependencies']);
        $container->setService('config', $config);

        return $container;
    }
}
