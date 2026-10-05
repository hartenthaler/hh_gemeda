<?php

declare(strict_types=1);

namespace Hartenthaler\Webtrees\Module\GeMeDaModule\Domain;

/** One read-only entry returned by a GeMeDa meta-search provider. */
final class GeMeDaProviderEntry
{
    public function __construct(
        public readonly string $lastName,
        public readonly string $firstName,
        public readonly string $details,
        public readonly string $url,
        public readonly ?string $identityUrl = null,
    ) {
    }

    public function displayName(): string
    {
        return trim(implode(' ', array_filter([$this->firstName, $this->lastName])));
    }
}
