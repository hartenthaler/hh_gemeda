<?php

declare(strict_types=1);

namespace Hartenthaler\Webtrees\Module\GeMeDaModule\Domain;

/** Results and status for one provider in a GeMeDa meta-search. */
final class GeMeDaProviderResult
{
    /** @param list<GeMeDaProviderEntry> $entries */
    public function __construct(
        public readonly GeMeDaProvider $provider,
        public readonly string $status,
        public readonly array $entries = [],
        public readonly bool $hasMore = false,
        public readonly ?string $providerUrl = null,
        public readonly ?string $error = null,
    ) {
    }
}
