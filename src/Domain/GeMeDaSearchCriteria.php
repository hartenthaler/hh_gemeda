<?php

declare(strict_types=1);

namespace Hartenthaler\Webtrees\Module\GeMeDaModule\Domain;

/** The data sent to the GeMeDa person-search endpoint. */
final class GeMeDaSearchCriteria
{
    public function __construct(
        public readonly string $givenName = '',
        public readonly string $surname = '',
        public readonly string $place = '',
        public readonly string $birthDate = '',
        public readonly string $deathDate = '',
        public readonly int $limit = 20,
    ) {
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        $values = [
            'given_name' => $this->givenName,
            'surname' => $this->surname,
            'place' => $this->place,
            'birth_date' => $this->birthDate,
            'death_date' => $this->deathDate,
            'limit' => max(1, min(20, $this->limit)),
        ];

        return array_filter($values, static fn (mixed $value): bool => $value !== '');
    }
}
