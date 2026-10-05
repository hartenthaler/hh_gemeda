<?php

declare(strict_types=1);

namespace Hartenthaler\Webtrees\Module\GeMeDaModule\Infrastructure;

use Hartenthaler\Webtrees\Module\GeMeDaModule\Domain\GeMeDaSearchCriteria;
use Hartenthaler\Webtrees\Module\GeMeDaModule\Domain\GeMeDaSearchResult;
use Hartenthaler\Webtrees\Module\GeMeDaModule\Domain\GeMeDaProvider;
use Hartenthaler\Webtrees\Module\GeMeDaModule\Domain\GeMeDaProviderEntry;
use Hartenthaler\Webtrees\Module\GeMeDaModule\Domain\GeMeDaProviderResult;
use Hartenthaler\Webtrees\Shared\Http\HttpTransport;
use RuntimeException;
use Throwable;

/** HTTP adapter for the GeMeDa person search endpoint. */
final class HttpGeMeDaApiClient implements GeMeDaApiClientInterface
{
    private const SEARCH_PATH = '/api/v1/lookup/search';
    private const PROVIDERS_PATH = '/api/v1/providers';
    private const META_SEARCH_URL = 'https://meta.genealogy.net/proxy';
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

    /** @return list<GeMeDaProvider> */
    public function providers(): array
    {
        $response = ($this->transport ?? HttpTransport::default())->request(
            'GET',
            $this->url(self::PROVIDERS_PATH),
            [],
            ['Accept' => 'application/json', 'User-Agent' => 'webtrees hh_gemeda/0.1'],
            8.0,
        );

        if ($response === null) {
            throw new RuntimeException('The GeMeDa provider catalogue returned no response.');
        }
        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            throw new RuntimeException('The GeMeDa provider catalogue returned HTTP ' . $response->getStatusCode() . '.');
        }

        $body = (string) $response->getBody();
        if (strlen($body) > self::MAX_RESPONSE_BYTES) {
            throw new RuntimeException('The GeMeDa provider catalogue is too large.');
        }
        try {
            $decoded = json_decode($body, true, 32, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            throw new RuntimeException('The GeMeDa provider catalogue is not valid JSON.');
        }

        $rows = $decoded['providers'] ?? $decoded;
        if (!is_array($rows)) {
            throw new RuntimeException('The GeMeDa provider catalogue has an invalid format.');
        }

        $providers = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = trim((string) ($row['id'] ?? ''));
            $name = trim((string) ($row['name'] ?? $id));
            $metaSearchId = (int) ($row['metaSearchId'] ?? $row['meta_search_id'] ?? 0);
            if ($id === '' || $name === '' || $metaSearchId < 1) {
                continue;
            }
            $providers[] = new GeMeDaProvider($id, $name, $metaSearchId);
        }

        usort($providers, static fn (GeMeDaProvider $left, GeMeDaProvider $right): int => strcasecmp($left->name, $right->name));

        return $providers;
    }

    /** @param list<GeMeDaProvider> $providers @return list<GeMeDaProviderResult> */
    public function searchProviders(GeMeDaSearchCriteria $criteria, array $providers): array
    {
        $lastName = trim($criteria->surname !== '' ? $criteria->surname : $criteria->givenName);
        $placeName = trim($criteria->place);
        if ($lastName === '' && $placeName === '') {
            return [];
        }

        $results = [];
        foreach ($providers as $provider) {
            $query = ['db' => (string) $provider->metaSearchId];
            if ($lastName !== '') {
                $query['lastname'] = $lastName;
            }
            if ($placeName !== '') {
                $query['placename'] = $placeName;
            }
            try {
                $response = ($this->transport ?? HttpTransport::default())->request(
                    'GET',
                    self::META_SEARCH_URL,
                    $query,
                    [
                        'Accept' => 'text/xml, application/xml, text/plain, */*',
                        'User-Agent' => 'Genealogy JSON API',
                    ],
                    15.0,
                );
            } catch (Throwable $exception) {
                $results[] = new GeMeDaProviderResult($provider, 'error', error: $exception->getMessage());
                continue;
            }

            if ($response === null) {
                $results[] = new GeMeDaProviderResult($provider, 'error', error: 'No response from provider.');
                continue;
            }
            if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
                $results[] = new GeMeDaProviderResult($provider, 'error', error: 'HTTP ' . $response->getStatusCode());
                continue;
            }
            $body = (string) $response->getBody();
            if (strlen($body) > self::MAX_RESPONSE_BYTES) {
                $results[] = new GeMeDaProviderResult($provider, 'error', error: 'The provider response is too large.');
                continue;
            }

            try {
                $parsed = $this->parseMetaSearchXml($body);
            } catch (Throwable $exception) {
                $results[] = new GeMeDaProviderResult($provider, 'invalid_xml', error: $exception->getMessage());
                continue;
            }
            if ($parsed === null) {
                $results[] = new GeMeDaProviderResult($provider, 'invalid_xml', error: 'The provider returned invalid XML.');
                continue;
            }
            $results[] = new GeMeDaProviderResult(
                $provider,
                'ok',
                $parsed['entries'],
                $parsed['has_more'],
                $parsed['provider_url'],
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

    private function url(string $path): string
    {
        $base = rtrim($this->baseUrl, '/');

        return str_ends_with($base, '/api/v1')
            ? $base . substr($path, strlen('/api/v1'))
            : $base . $path;
    }

    /** @return array{entries:list<GeMeDaProviderEntry>,has_more:bool,provider_url:?string}|null */
    private function parseMetaSearchXml(string $xml): ?array
    {
        if (preg_match('/<database(?:\s[^>]*)?>(.*?)<\/database>/is', $xml, $databaseMatch) !== 1) {
            return null;
        }

        $entries = [];
        $providerUrl = null;
        $hasMore = false;
        preg_match_all('/<database(?:\s[^>]*)?>(.*?)<\/database>/is', $xml, $databases);
        foreach ($databases[1] ?? [] as $database) {
            $providerUrl ??= $this->xmlTag($database, 'url');
            $hasMore = $hasMore || strtolower($this->xmlTag($database, 'more') ?? '') === 'true';
            preg_match_all('/<entry(?:\s[^>]*)?>(.*?)<\/entry>/is', $database, $entryBlocks);
            foreach ($entryBlocks[1] ?? [] as $entry) {
                $url = trim((string) ($this->xmlTag($entry, 'url') ?? ''));
                if ($url === '') {
                    continue;
                }
                $entries[] = new GeMeDaProviderEntry(
                    trim((string) ($this->xmlTag($entry, 'lastname') ?? '')),
                    trim((string) ($this->xmlTag($entry, 'firstname') ?? '')),
                    trim((string) ($this->xmlTag($entry, 'details') ?? '')),
                    $url,
                    $this->xmlTag($entry, 'identityUrl') ?? $this->xmlTag($entry, 'identity_url'),
                );
            }
        }

        return ['entries' => $entries, 'has_more' => $hasMore, 'provider_url' => $providerUrl];
    }

    private function xmlTag(string $xml, string $tag): ?string
    {
        $pattern = '/<' . preg_quote($tag, '/') . '(?:\s[^>]*)?>(.*?)<\/' . preg_quote($tag, '/') . '>/is';
        if (preg_match($pattern, $xml, $matches) !== 1) {
            return null;
        }

        return html_entity_decode(strip_tags($matches[1]), ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
