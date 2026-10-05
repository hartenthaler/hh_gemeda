<?php

declare(strict_types=1);

namespace Hartenthaler\Webtrees\Module\GeMeDaModule\Infrastructure;

use Hartenthaler\Webtrees\Module\GeMeDaModule\Domain\GeMeDaSearchCriteria;
use Hartenthaler\Webtrees\Module\GeMeDaModule\Domain\GeMeDaSearchResult;
use Hartenthaler\Webtrees\Module\GeMeDaModule\Domain\GeMeDaProvider;
use Hartenthaler\Webtrees\Module\GeMeDaModule\Domain\GeMeDaProviderResult;

interface GeMeDaApiClientInterface
{
    /** @return list<GeMeDaSearchResult> */
    public function search(GeMeDaSearchCriteria $criteria): array;

    /** @return list<GeMeDaProvider> */
    public function providers(): array;

    /** @param list<GeMeDaProvider> $providers @return list<GeMeDaProviderResult> */
    public function searchProviders(GeMeDaSearchCriteria $criteria, array $providers): array;

    /** @return array<string,mixed>|null */
    public function person(string $personHash): ?array;

    /** @param array<string,mixed> $claim @return array<string,mixed> */
    public function createClaim(array $claim, string $contributorId): array;
}
