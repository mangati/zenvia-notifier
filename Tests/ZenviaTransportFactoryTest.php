<?php

namespace Mangati\Notifier\Zenvia\Tests;

use Mangati\Notifier\Zenvia\ZenviaTransportFactory;
use Symfony\Component\Notifier\Test\TransportFactoryTestCase;
use Symfony\Component\Notifier\Transport\TransportFactoryInterface;

final class ZenviaTransportFactoryTest extends TransportFactoryTestCase
{
    /**
     * @return ZenviaTransportFactory
     */
    public function createFactory(): TransportFactoryInterface
    {
        return new ZenviaTransportFactory();
    }

    public function createProvider(): iterable
    {
        yield [
            'zenvia://api.zenvia.com',
            'zenvia://5527999999999:authToken@default',
        ];
    }

    public function supportsProvider(): iterable
    {
        yield [true, 'zenvia://5527999999999:authToken@default'];
        yield [false, 'somethingElse://5527999999999:authToken@default'];
    }

    public function unsupportedSchemeProvider(): iterable
    {
        yield ['somethingElse://accountSid:authToken@default'];
    }
}
