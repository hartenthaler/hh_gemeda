<?php

declare(strict_types=1);

namespace Hartenthaler\Webtrees\Module\GeMeDaModule\Infrastructure;

use RuntimeException;

/** Safe phase-1 placeholder until the GeMeDa endpoint and authentication contract are confirmed. */
final class UnavailableGeMeDaApiClient implements GeMeDaApiClientInterface
{
    public function person(string $personHash): ?array
    {
        throw new RuntimeException('The GeMeDa API client is not configured yet.');
    }

    public function createClaim(array $claim, string $contributorId): array
    {
        throw new RuntimeException('The GeMeDa API client is not configured yet.');
    }
}
