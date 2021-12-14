<?php

namespace Mangati\Notifier\Zenvia\Tests;

use Mangati\Notifier\Zenvia\ZenviaTransport;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\Notifier\Exception\InvalidArgumentException;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Test\TransportTestCase;
use Symfony\Component\Notifier\Transport\TransportInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class ZenviaTransportTest extends TransportTestCase
{
    /**
     * @return ZenviaTransport
     */
    public function createTransport(HttpClientInterface $client = null, string $from = 'from'): TransportInterface
    {
        return new ZenviaTransport($from, 'authToken', $client ?? $this->createMock(HttpClientInterface::class));
    }

    public function toStringProvider(): iterable
    {
        yield ['zenvia://api.zenvia.com', $this->createTransport()];
    }

    public function supportedMessagesProvider(): iterable
    {
        yield [new SmsMessage('0611223344', 'Hello!')];
    }

    public function unsupportedMessagesProvider(): iterable
    {
        yield [new ChatMessage('Hello!')];
        yield [$this->createMock(MessageInterface::class)];
    }

    /**
     * @dataProvider invalidFromProvider
     */
    public function testInvalidArgumentExceptionIsThrownIfFromIsInvalid(string $from)
    {
        $transport = $this->createTransport(null, $from);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf('The "From" number "%s" is not a valid phone number.', $from));

        $transport->send(new SmsMessage('33612345678', 'Hello!'));
    }

    public function invalidFromProvider(): iterable
    {
        // alphanumeric sender ids
        yield 'too short' => ['a'];
        yield 'too long' => ['abcdefghijkl'];

        // phone numbers
        yield 'no zero at start if phone number' => ['+0'];
        yield 'phone number to short' => ['+1'];
    }

    /**
     * @dataProvider validFromProvider
     */
    public function testNoInvalidArgumentExceptionIsThrownIfFromIsValid(string $from)
    {
        $message = new SmsMessage('33612345678', 'Hello!');

        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->exactly(2))
            ->method('getStatusCode')
            ->willReturn(200);
        $response->expects($this->once())
            ->method('getContent')
            ->willReturn(json_encode([
                'id' => '123',
            ]));

        $client = new MockHttpClient(function (string $method, string $url, array $options = []) use ($response): ResponseInterface {
            $this->assertSame('POST', $method);
            $this->assertSame('https://api.zenvia.com/v2/channels/sms/messages', $url);

            return $response;
        });

        $transport = $this->createTransport($client, $from);

        $sentMessage = $transport->send($message);

        $this->assertSame('123', $sentMessage->getMessageId());
    }

    public function validFromProvider(): iterable
    {
        // phone numbers (12 or 13 numbers)
        yield ['112345678912'];
        yield ['1123456789123'];
    }
}
