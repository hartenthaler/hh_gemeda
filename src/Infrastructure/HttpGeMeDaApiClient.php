<?php

declare(strict_types=1);

namespace Hartenthaler\Webtrees\Module\GeMeDaModule\Infrastructure;

use Hartenthaler\Webtrees\Module\GeMeDaModule\Domain\GeMeDaSearchCriteria;
use Hartenthaler\Webtrees\Module\GeMeDaModule\Domain\GeMeDaSearchResult;
use Hartenthaler\Webtrees\Shared\Http\HttpTransport;
use RuntimeException;
use Throwable;

/** HTTP adapter for the GeMeDa person search endpoint. */
final class HttpGeMeDaApiClient implements GeMeDaApiClientInterface
{
    private const SEARCH_PATH = '/api/v1/lookup/search';
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
            ? $base . '/lookup/search'
            : $base . self::SEARCH_PATH;
        $headers = ['User-Agent' => 'webtrees hh_gemeda/0.1'];
        if (trim($this->serviceKey) !== '') {
            $headers['Authorization'] = 'Bearer ' . trim($this->serviceKey);
        }

        $query = array_filter([
            'q' => implode(' ', array_filter([
                $criteria->givenName,
                $criteria->surname,
                $criteria->place,
            ], static fn (string $value): bool => trim($value) !== '')),
        ], static fn (string $value): bool => trim($value) !== '');
        if ($query === []) {
            return [];
        }

        $response = ($this->transport ?? HttpTransport::default())->request('GET', $url, $query, $headers, 8.0);
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

            // The current search endpoint returns public persons directly;
            // older test responses wrapped the person below a `person` key.
            $person = is_array($row['person'] ?? null) ? $row['person'] : $row;
            $hash = (string) ($person['person_hash'] ?? $person['personHash'] ?? $person['gemeDaHash'] ?? $person['hash'] ?? '');
            if ($hash === '') {
                continue;
            }
            $sources = [];
            $sourceRows = $person['links'] ?? $person['sources'] ?? $person['linked_sources'] ?? $person['linkedSources'] ?? [];
            if (!is_array($sourceRows)) {
                $sourceRows = [];
            }
            foreach ($sourceRows as $source) {
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
                    'label' => isset($source['label']) || isset($source['labelSnapshot']) || isset($source['label_snapshot'])
                        ? (string) ($source['label'] ?? $source['labelSnapshot'] ?? $source['label_snapshot'])
                        : null,
                ];
            }
            $results[] = new GeMeDaSearchResult(
                $hash,
                (string) ($person['display_name'] ?? $person['displayName'] ?? $person['name'] ?? $hash),
                $sources,
                isset($person['score']) || isset($person['confidence']) ? (float) ($person['score'] ?? $person['confidence']) : null,
                array_values(array_map('strval', is_array($person['match_reasons'] ?? $person['matchReasons'] ?? null) ? ($person['match_reasons'] ?? $person['matchReasons']) : [])),
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
