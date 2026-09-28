<?php

declare(strict_types=1);

namespace Hartenthaler\Webtrees\Module\GeMeDaModule\Infrastructure;

interface GeMeDaApiClientInterface
{
    /** @return array<string,mixed>|null */
    public function person(string $personHash): ?array;

    /** @param array<string,mixed> $claim @return array<string,mixed> */
    public function createClaim(array $claim, string $contributorId): array;
}
