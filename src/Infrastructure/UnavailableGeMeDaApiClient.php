<?php

declare(strict_types=1);

namespace Hartenthaler\Webtrees\Module\GeMeDaModule\Infrastructure;

use RuntimeException;
use Hartenthaler\Webtrees\Module\GeMeDaModule\Domain\GeMeDaSearchCriteria;

/** Safe phase-1 placeholder until the GeMeDa endpoint and authentication contract are confirmed. */
final class UnavailableGeMeDaApiClient implements GeMeDaApiClientInterface
{
    public function search(GeMeDaSearchCriteria $criteria): array
    {
        throw new RuntimeException('The GeMeDa API client is not configured yet.');
    }

    public function providers(): array
    {
        throw new RuntimeException('The GeMeDa API client is not configured yet.');
    }

    public function searchProviders(GeMeDaSearchCriteria $criteria, array $providers): array
    {
        throw new RuntimeException('The GeMeDa API client is not configured yet.');
    }

    public function person(string $personHash): ?array
    {
        throw new RuntimeException('The GeMeDa API client is not configured yet.');
    }

    public function createClaim(array $claim, string $contributorId): array
    {
        throw new RuntimeException('The GeMeDa API client is not configured yet.');
    }
}
