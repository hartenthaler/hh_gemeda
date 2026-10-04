<?php

declare(strict_types=1);

// Load Composer dependencies first. The shared HTTP transport is a required
// runtime dependency, not an optional fallback implementation.
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/vendor/hartenthaler/hh-shared/autoload.php';
require_once __DIR__ . '/src/autoload.php';
