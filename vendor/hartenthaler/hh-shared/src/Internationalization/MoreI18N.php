<?php

declare(strict_types=1);

namespace Hartenthaler\Webtrees\Shared\Internationalization;

use Fisharebest\Webtrees\I18N;

/**
 * Wrappers for strings which are already translated by webtrees core.
 *
 * The different method names intentionally keep gettext from extracting
 * these calls into a module-specific catalogue.
 */
final class MoreI18N
{
    public static function xlate(string $message, mixed ...$args): string
    {
        return I18N::translate($message, ...$args);
    }

    public static function xlateContext(string $context, string $message, mixed ...$args): string
    {
        return I18N::translateContext($context, $message, ...$args);
    }

    public static function plural(string $singular, string $plural, int $count, mixed ...$args): string
    {
        return I18N::plural($singular, $plural, $count, ...$args);
    }

    public static function number(float $number, int $precision = 0): string
    {
        return I18N::number($number, $precision);
    }
}
