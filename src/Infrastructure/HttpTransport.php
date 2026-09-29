<?php

declare(strict_types=1);

namespace Hartenthaler\Webtrees\Module\GeMeDaModule\Infrastructure;

use Fisharebest\Webtrees\Registry;
use GuzzleHttp\Client;
use Psr\Http\Client\ClientInterface as PsrClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Throwable;

/** PSR-18 transport for webtrees 2.3 with a Guzzle fallback for 2.2. */
final class HttpTransport
{
    private function __construct(
        private readonly ?PsrClientInterface $psrClient,
        private readonly ?RequestFactoryInterface $requestFactory,
        private readonly ?StreamFactoryInterface $streamFactory,
        private readonly ?object $guzzleClient,
    ) {
    }

    public static function default(): self
    {
        try {
            if (class_exists(Registry::class) && interface_exists(PsrClientInterface::class) && interface_exists(RequestFactoryInterface::class)) {
                $container = Registry::container();
                if ($container->has(PsrClientInterface::class) && $container->has(RequestFactoryInterface::class)) {
                    return new self(
                        $container->get(PsrClientInterface::class),
                        $container->get(RequestFactoryInterface::class),
                        $container->has(StreamFactoryInterface::class) ? $container->get(StreamFactoryInterface::class) : null,
                        null,
                    );
                }
            }
        } catch (Throwable) {
            // Use the legacy client below.
        }

        return new self(null, null, null, class_exists(Client::class) ? new Client() : null);
    }

    /** @param array<string,string> $headers */
    public function jsonPost(string $url, array $payload, array $headers = [], float $timeout = 8.0): ?ResponseInterface
    {
        $headers = ['Accept' => 'application/json', 'Content-Type' => 'application/json'] + $headers;
        $body = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($this->psrClient !== null && $this->requestFactory !== null && $this->streamFactory !== null) {
            try {
                $request = $this->requestFactory->createRequest('POST', $url)
                    ->withBody($this->streamFactory->createStream($body));
                foreach ($headers as $name => $value) {
                    $request = $request->withHeader($name, $value);
                }
                return $this->psrClient->sendRequest($request);
            } catch (Throwable) {
                return null;
            }
        }

        if ($this->guzzleClient === null) {
            return null;
        }

        try {
            return $this->guzzleClient->request('POST', $url, [
                'allow_redirects' => false,
                'connect_timeout' => min(8.0, $timeout),
                'headers' => $headers,
                'http_errors' => false,
                'json' => $payload,
                'timeout' => $timeout,
            ]);
        } catch (Throwable) {
            return null;
        }
    }
}
