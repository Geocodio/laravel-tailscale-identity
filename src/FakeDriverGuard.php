<?php

declare(strict_types=1);

namespace Geocodio\TailscaleIdentity;

final class FakeDriverGuard
{
    /**
     * Fail closed: a fake identity driver outside local/testing is a
     * deployment error severe enough to abort the whole app.
     */
    public static function check(string $driver, string $environment): void
    {
        if ($driver === 'fake' && ! in_array($environment, ['local', 'testing'], true)) {
            throw new \RuntimeException(
                "The fake Tailscale identity driver must never be active outside local/testing (APP_ENV={$environment})."
            );
        }
    }
}
