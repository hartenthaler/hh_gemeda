<?php

declare(strict_types=1);

namespace Hartenthaler\Webtrees\Shared\Http;

use Fisharebest\Webtrees\Registry;
use GuzzleHttp\Client;
use Psr\Http\Client\ClientInterface as PsrClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Throwable;

/**
 * HTTP boundary shared by modules that call external services.
 *
 * webtrees 2.3 can provide a PSR-18 client through its container. Older
 * installations, and standalone module tests, use the Guzzle fallback.
 */
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
        if (class_exists(Registry::class) && interface_exists(PsrClientInterface::class) && interface_exists(RequestFactoryInterface::class)) {
            try {
                $container = Registry::container();

                if ($container->has(PsrClientInterface::class) && $container->has(RequestFactoryInterface::class)) {
                    return new self(
                        $container->get(PsrClientInterface::class),
                        $container->get(RequestFactoryInterface::class),
                        $container->has(StreamFactoryInterface::class) ? $container->get(StreamFactoryInterface::class) : null,
                        null,
                    );
                }
            } catch (Throwable) {
                // Fall through to the legacy client when the container is
                // unavailable, for example in a standalone module test.
            }
        }

        if (class_exists(Client::class)) {
            return new self(null, null, null, new Client());
        }

        return new self(null, null, null, null);
    }

    /**
     * Send a bounded request and return a PSR-7 response.
     *
     * PSR-18 does not expose a per-request timeout. The webtrees-provided
     * client owns that policy; the Guzzle fallback applies the requested
     * timeout. Callers retain responsibility for redirects and response
     * interpretation.
     *
     * @param array<string,mixed> $query
     * @param array<string,string> $headers
     */
    public function request(string $method, string $url, array $query = [], array $headers = [], float $timeout = 6.0): ?ResponseInterface
    {
        // Accept the former Guzzle-style options shape while callers are
        // migrated incrementally. A string-valued query option is still a
        // normal query parameter and must not be mistaken for this shape.
        if (is_array($query['query'] ?? null) || array_key_exists('headers', $query) || array_key_exists('timeout', $query)) {
            $options = $query;
            $query = is_array($options['query'] ?? null) ? $options['query'] : [];
            $headers = is_array($options['headers'] ?? null) ? $options['headers'] : [];
            $timeout = is_numeric($options['timeout'] ?? null) ? (float) $options['timeout'] : $timeout;
        }

        if ($this->psrClient !== null && $this->requestFactory !== null) {
            try {
                if ($query !== []) {
                    $separator = str_contains($url, '?') ? '&' : '?';
                    $url .= $separator . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
                }

                $request = $this->requestFactory->createRequest($method, $url);

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
            return $this->guzzleClient->request($method, $url, [
                'allow_redirects' => false,
                'connect_timeout' => min(8.0, $timeout),
                'headers' => $headers,
                'http_errors' => false,
                'query' => $query,
                'timeout' => $timeout,
            ]);
        } catch (Throwable) {
            return null;
        }
    }

    /** Send a form-encoded request through PSR-18 or the Guzzle fallback. */
    public function requestForm(string $method, string $url, array $form = [], array $headers = [], float $timeout = 6.0): ?ResponseInterface
    {
        if ($this->psrClient !== null && $this->requestFactory !== null && $this->streamFactory !== null) {
            try {
                $request = $this->requestFactory->createRequest($method, $url)
                    ->withHeader('Content-Type', 'application/x-www-form-urlencoded');

                foreach ($headers as $name => $value) {
                    $request = $request->withHeader($name, $value);
                }

                $request = $request->withBody($this->streamFactory->createStream(http_build_query($form, '', '&', PHP_QUERY_RFC3986)));

                return $this->psrClient->sendRequest($request);
            } catch (Throwable) {
                return null;
            }
        }

        if ($this->guzzleClient === null) {
            return null;
        }

        try {
            return $this->guzzleClient->request($method, $url, [
                'allow_redirects' => false,
                'connect_timeout' => min(8.0, $timeout),
                'form_params' => $form,
                'headers' => $headers,
                'http_errors' => false,
                'timeout' => $timeout,
            ]);
        } catch (Throwable) {
            return null;
        }
    }
}
