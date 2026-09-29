<?php

declare(strict_types=1);

namespace Hartenthaler\Webtrees\Module\GeMeDaModule\Infrastructure;

use Hartenthaler\Webtrees\Module\GeMeDaModule\Domain\GeMeDaSearchCriteria;
use Hartenthaler\Webtrees\Module\GeMeDaModule\Domain\GeMeDaSearchResult;
use RuntimeException;
use Throwable;

/** HTTP adapter for the assumed GeMeDa person search endpoint. */
final class HttpGeMeDaApiClient implements GeMeDaApiClientInterface
{
    private const SEARCH_PATH = '/api/v1/search';
    private const MAX_RESPONSE_BYTES = 524288;

    public function __construct(
        private readonly string $baseUrl,
        private readonly string $serviceKey,
        private readonly ?HttpTransport $transport = null,
    ) {
    }

    public function search(GeMeDaSearchCriteria $criteria): array
    {
        $base = rtrim($this->baseUrl, '/');
        $url = str_ends_with($base, '/api/v1')
            ? $base . '/search'
            : $base . self::SEARCH_PATH;
        $headers = ['User-Agent' => 'webtrees hh_gemeda/0.1'];
        if (trim($this->serviceKey) !== '') {
            $headers['Authorization'] = 'Bearer ' . trim($this->serviceKey);
        }

        $response = ($this->transport ?? HttpTransport::default())->jsonPost($url, $criteria->toArray(), $headers);
        if ($response === null) {
            throw new RuntimeException('The GeMeDa search request returned no response.');
        }
        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            throw new RuntimeException('The GeMeDa search endpoint returned HTTP ' . $response->getStatusCode() . '.');
        }

        $body = (string) $response->getBody();
        if (strlen($body) > self::MAX_RESPONSE_BYTES) {
            throw new RuntimeException('The GeMeDa search response is too large.');
        }
        try {
            $decoded = json_decode($body, true, 32, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            throw new RuntimeException('The GeMeDa search response is not valid JSON.');
        }

        $rows = $decoded['results'] ?? $decoded['items'] ?? $decoded['data'] ?? $decoded;
        if (!is_array($rows)) {
            return [];
        }

        $results = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $hash = (string) ($row['person_hash'] ?? $row['personHash'] ?? $row['hash'] ?? '');
            if ($hash === '') {
                continue;
            }
            $sources = [];
            foreach (($row['sources'] ?? $row['linked_sources'] ?? $row['linkedSources'] ?? []) as $source) {
                if (!is_array($source)) {
                    continue;
                }
                $id = (string) ($source['external_id'] ?? $source['externalId'] ?? $source['id'] ?? '');
                if ($id === '') {
                    continue;
                }
                $sources[] = [
                    'provider' => (string) ($source['provider'] ?? $source['source'] ?? ''),
                    'external_id' => $id,
                    'type_uri' => isset($source['type_uri']) || isset($source['typeUri']) ? (string) ($source['type_uri'] ?? $source['typeUri']) : null,
                    'url' => isset($source['url']) || isset($source['external_url']) ? (string) ($source['url'] ?? $source['external_url']) : null,
                    'label' => isset($source['label']) ? (string) $source['label'] : null,
                ];
            }
            $results[] = new GeMeDaSearchResult(
                $hash,
                (string) ($row['display_name'] ?? $row['displayName'] ?? $row['name'] ?? $hash),
                $sources,
                isset($row['score']) || isset($row['confidence']) ? (float) ($row['score'] ?? $row['confidence']) : null,
                array_values(array_map('strval', is_array($row['match_reasons'] ?? $row['matchReasons'] ?? null) ? ($row['match_reasons'] ?? $row['matchReasons']) : [])),
            );
        }

        return $results;
    }

    public function person(string $personHash): ?array
    {
        return null;
    }

    public function createClaim(array $claim, string $contributorId): array
    {
        throw new RuntimeException('Claim writing is not implemented until the GeMeDa write contract is confirmed.');
    }
}
