<?php

declare(strict_types=1);

namespace Geocodio\TailscaleIdentity;

use Geocodio\TailscaleIdentity\Exceptions\IdentityLookupException;
use Geocodio\TailscaleIdentity\Exceptions\IdentityRejectedException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Process;

/**
 * Identity from `tailscale whois --json`, spoken to the mounted LocalAPI
 * socket. No daemon runs in-process; the host's tailscaled answers.
 *
 * Caches the parsed FIELDS as a plain array, never the DTO. Laravel defaults
 * `cache.serializable_classes` to false, which makes unserialize() return
 * __PHP_Incomplete_Class for every object read back from a serializing store:
 * caching the object works exactly once per address and then 500s for the
 * rest of the TTL. The versioned key prefix means a release that changes the
 * cached shape does not have to wait out the TTL on old-shape entries.
 */
final readonly class TailscaleWhoisDriver implements IdentityDriver
{
    public const CACHE_PREFIX = 'tailscale-identity:v1:';

    public function __construct(
        private WhoisResponseParser $parser,
        private string $capabilityName,
        private string $binary = 'tailscale',
        private int $processTimeout = 3,
        private int $cacheTtl = 60,
    ) {}

    public function identify(string $remoteAddr): TailscaleIdentity
    {
        if (! TailscaleAddressRange::contains($remoteAddr)) {
            throw new IdentityRejectedException(
                "Peer address {$remoteAddr} is not a Tailscale address — check the proxy chain (see README: deployment requirements)."
            );
        }

        $fields = Cache::remember(
            self::CACHE_PREFIX.$remoteAddr,
            $this->cacheTtl,
            function () use ($remoteAddr): array {
                $result = Process::timeout($this->processTimeout)
                    ->run([$this->binary, 'whois', '--json', $remoteAddr]);

                if (! $result->successful()) {
                    // Fail closed: an unreachable tailscaled is a 503, never anonymous access.
                    throw new IdentityLookupException(
                        "tailscale whois failed for {$remoteAddr}: ".trim($result->errorOutput())
                    );
                }

                $identity = $this->parser->parse($result->output(), $remoteAddr, $this->capabilityName);

                return [
                    'email' => $identity->email,
                    'displayName' => $identity->displayName,
                    'nodeName' => $identity->nodeName,
                    'peerAddress' => $identity->peerAddress,
                    'capabilities' => $identity->capabilities,
                ];
            },
        );

        return new TailscaleIdentity(
            email: $fields['email'],
            displayName: $fields['displayName'],
            nodeName: $fields['nodeName'],
            peerAddress: $fields['peerAddress'],
            capabilities: $fields['capabilities'],
        );
    }
}
