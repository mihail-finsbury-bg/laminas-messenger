<?php

declare(strict_types=1);

namespace TMV\Laminas\Messenger\Test\Factory\Listener;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\EventListener\SendFailedMessageToFailureTransportListener;
use Symfony\Component\Messenger\Transport\TransportInterface;
use TMV\Laminas\Messenger\Exception\InvalidArgumentException;
use TMV\Laminas\Messenger\Factory\Listener\SendFailedMessageToFailureTransportListenerFactory;
use TMV\Laminas\Messenger\ServiceProvider;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Prophecy\Argument;

class SendFailedMessageToFailureTransportListenerFactoryTest extends TestCase
{
    use ProphecyTrait;

    public function testFactory(): void
    {
        $container = $this->prophesize(ContainerInterface::class);

        $container->has('config')->willReturn(true);
        $container->get('config')->willReturn([
            'messenger' => [
                'failure_transport' => 'failure-transport-name',
                'logger' => 'logger.service',
            ],
        ]);

        $sender = $this->prophesize(TransportInterface::class);
        $logger = $this->prophesize(LoggerInterface::class);

        $container->get('messenger.receivers_locator')->willReturn(new ServiceProvider(['async' => static function () {}]));
        $sender->send(Argument::type(Envelope::class))->willReturnArgument(0)->shouldBeCalled();
        $container->get('failure-transport-name')
            ->shouldBeCalled()
            ->willReturn($sender->reveal());
        $container->has('logger.service')
            ->shouldBeCalled()
            ->willReturn(true);
        $container->get('logger.service')
            ->shouldBeCalled()
            ->willReturn($logger->reveal());

        $factory = new SendFailedMessageToFailureTransportListenerFactory();
        $service = $factory($container->reveal());

        $this->assertInstanceOf(SendFailedMessageToFailureTransportListener::class, $service);
        $service->onMessageFailed(new WorkerMessageFailedEvent(new Envelope(new \stdClass()), 'async', new \RuntimeException('Failed')));
    }

    public function testFactoryShouldWorkWithoutLogger(): void
    {
        $container = $this->prophesize(ContainerInterface::class);

        $container->has('config')->willReturn(true);
        $container->get('config')->willReturn([
            'messenger' => [
                'failure_transport' => 'failure-transport-name',
                'logger' => null,
            ],
        ]);

        $sender = $this->prophesize(TransportInterface::class);

        $container->get('messenger.receivers_locator')->willReturn(new ServiceProvider(['async' => static function () {}]));
        $sender->send(Argument::type(Envelope::class))->willReturnArgument(0)->shouldBeCalled();
        $container->get('failure-transport-name')
            ->shouldBeCalled()
            ->willReturn($sender->reveal());

        $factory = new SendFailedMessageToFailureTransportListenerFactory();
        $service = $factory($container->reveal());

        $this->assertInstanceOf(SendFailedMessageToFailureTransportListener::class, $service);
        $service->onMessageFailed(new WorkerMessageFailedEvent(new Envelope(new \stdClass()), 'async', new \RuntimeException('Failed')));
    }

    public function testFactoryShouldThrowExceptionWithoutFailureTransport(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $container = $this->prophesize(ContainerInterface::class);

        $container->has('config')->willReturn(true);
        $container->get('config')->willReturn([
            'messenger' => [
                'failure_transport' => null,
            ],
        ]);

        $factory = new SendFailedMessageToFailureTransportListenerFactory();
        $factory($container->reveal());
    }
}
