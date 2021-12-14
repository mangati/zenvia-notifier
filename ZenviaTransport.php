<?php

namespace Mangati\Notifier\Zenvia;

use Symfony\Component\Notifier\Exception\InvalidArgumentException;
use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Exception\UnsupportedMessageTypeException;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\SentMessage;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Transport\AbstractTransport;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;


final class ZenviaTransport extends AbstractTransport
{
    protected const HOST = 'api.zenvia.com';

    private string $from;
    private string $token;

    public function __construct(string $from, string $token, HttpClientInterface $client = null, EventDispatcherInterface $dispatcher = null)
    {
        $this->from = $from;
        $this->token = $token;

        parent::__construct($client, $dispatcher);
    }

    public function supports(MessageInterface $message): bool
    {
        return $message instanceof SmsMessage;
    }

    protected function doSend(MessageInterface $message): SentMessage
    {
        if (!$message instanceof SmsMessage) {
            throw new UnsupportedMessageTypeException(__CLASS__, SmsMessage::class, $message);
        }

        if (!preg_match('/^[0-9]{12,13}$/', $this->from)) {
            throw new InvalidArgumentException(sprintf('The "From" number "%s" is not a valid phone number.', $this->from));
        }

        $endpoint = sprintf('https://%s/v2/channels/sms/messages', $this->getEndpoint());
        $response = $this->client->request('POST', $endpoint, [
            'headers' => [
                'X-API-TOKEN' => $this->token,
            ],
            'json' => [
                'from' => $this->from,
                'to' => $message->getPhone(),
                'contents' => [
                    [
                        'type' => 'text',
                        'text' => $message->getSubject(),
                    ]
                ],
            ],
        ]);

        try {
            $statusCode = $response->getStatusCode();
        } catch (TransportExceptionInterface $e) {
            throw new TransportException('Could not reach the remote Zenvia server.', $response, 0, $e);
        }

        if (200 !== $statusCode) {
            $error = $response->toArray(false);
            throw new TransportException('Unable to send the SMS: '.$error['code'].' '.$error['message'], $response);
        }

        $success = $response->toArray(false);

        $sentMessage = new SentMessage($message, (string) $this);
        $sentMessage->setMessageId($success['id']);

        return $sentMessage;
    }


    public function __toString(): string
    {
        return sprintf('zenvia://%s', $this->getEndpoint());
    }
}
