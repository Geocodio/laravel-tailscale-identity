<?php

declare(strict_types=1);

namespace Geocodio\TailscaleIdentity;

use Geocodio\TailscaleIdentity\Exceptions\IdentityLookupException;
use Geocodio\TailscaleIdentity\Exceptions\IdentityRejectedException;

interface IdentityDriver
{
    /**
     * Resolve the connecting IP (REMOTE_ADDR only — never forwarded headers)
     * to an authenticated Tailscale user.
     *
     * @throws IdentityLookupException infrastructure failure — callers must fail closed (503)
     * @throws IdentityRejectedException any auth-boundary rejection (403)
     */
    public function identify(string $remoteAddr): TailscaleIdentity;
}
