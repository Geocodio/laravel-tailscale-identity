<?php

declare(strict_types=1);

namespace Geocodio\TailscaleIdentity\Middleware;

use Closure;
use Geocodio\TailscaleIdentity\Exceptions\IdentityLookupException;
use Geocodio\TailscaleIdentity\Exceptions\IdentityRejectedException;
use Geocodio\TailscaleIdentity\IdentityDriver;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the tailnet identity and exposes it as a request attribute.
 * Deliberately touches no user model: just-in-time provisioning, roles,
 * and sessions are the application's concern, layered on top.
 *
 * Identity comes ONLY from REMOTE_ADDR — never from forwarded headers,
 * which are attacker-controlled. The deployment (see README) must make
 * REMOTE_ADDR the genuine tailnet peer before PHP sees the request.
 */
final readonly class ResolveTailscaleIdentity
{
    public const string ATTRIBUTE = 'tailscale.identity';

    public function __construct(private IdentityDriver $driver) {}

    public function handle(Request $request, Closure $next): Response
    {
        $remoteAddr = (string) $request->server('REMOTE_ADDR');

        try {
            $identity = $this->driver->identify($remoteAddr);
        } catch (IdentityLookupException) {
            abort(503, 'Identity lookup unavailable.'); // fail closed, never anonymous
        } catch (IdentityRejectedException) {
            abort(403);
        }

        $request->attributes->set(self::ATTRIBUTE, $identity);

        return $next($request);
    }
}
