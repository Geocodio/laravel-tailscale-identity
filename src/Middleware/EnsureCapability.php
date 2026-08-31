<?php

declare(strict_types=1);

namespace Geocodio\TailscaleIdentity\Middleware;

use Closure;
use Geocodio\TailscaleIdentity\TailscaleIdentity;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate on raw capability values, without knowing what they mean.
 * `tailscale.capability:role,admin` passes iff some capability object has
 * ['role' => 'admin']. Apps that reduce capabilities to a role enum keep
 * their own role-aware middleware on top; this stays semantics-free.
 *
 * Must run after ResolveTailscaleIdentity; fails closed (403) otherwise.
 */
final readonly class EnsureCapability
{
    public function handle(Request $request, Closure $next, ?string $key = null, ?string $value = null): Response
    {
        $identity = $request->attributes->get(ResolveTailscaleIdentity::ATTRIBUTE);

        if (! $identity instanceof TailscaleIdentity) {
            abort(403);
        }

        if ($key !== null) {
            $matched = array_any(
                $identity->capabilities,
                static fn (array $capability): bool => ($capability[$key] ?? null) === $value,
            );

            abort_unless($matched, 403);
        }

        return $next($request);
    }
}
