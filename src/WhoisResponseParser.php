<?php

declare(strict_types=1);

namespace Geocodio\TailscaleIdentity;

use Geocodio\TailscaleIdentity\Exceptions\IdentityRejectedException;

/**
 * Parse `tailscale whois --json <ip>` output. Every rejection here is an
 * auth boundary; when in doubt, reject.
 */
final class WhoisResponseParser
{
    public function parse(string $json, string $remoteAddr, string $capabilityName): TailscaleIdentity
    {
        try {
            /** @var array<string, mixed> $data */
            $data = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new IdentityRejectedException('Whois response was not valid JSON.', previous: $e);
        }

        $node = is_array($data['Node'] ?? null) ? $data['Node'] : [];
        $profile = is_array($data['UserProfile'] ?? null) ? $data['UserProfile'] : [];

        $tags = $node['Tags'] ?? null;
        if (is_array($tags) && $tags !== []) {
            throw new IdentityRejectedException('Rejected tagged node: tags identify machines, not people.');
        }

        $routes = $node['PrimaryRoutes'] ?? null;
        if (is_array($routes) && $routes !== []) {
            throw new IdentityRejectedException('Rejected subnet router: relayed users must connect directly.');
        }

        $addresses = array_map(
            static fn (string $cidr): string => explode('/', $cidr)[0],
            array_filter((array) ($node['Addresses'] ?? []), 'is_string'),
        );
        if (! in_array($remoteAddr, $addresses, true)) {
            throw new IdentityRejectedException("Whois address mismatch for {$remoteAddr}.");
        }

        $login = (string) ($profile['LoginName'] ?? '');
        if ($login === '' || $login === 'tagged-devices' || ! str_contains($login, '@')) {
            throw new IdentityRejectedException('Whois returned no usable login name.');
        }

        return new TailscaleIdentity(
            email: mb_strtolower($login),
            displayName: (string) ($profile['DisplayName'] ?? ''),
            nodeName: (string) ($node['Name'] ?? ''),
            peerAddress: $remoteAddr,
            capabilities: $this->extractCapabilities($data, $capabilityName),
        );
    }

    /**
     * App capability grants land in the TOP-LEVEL `CapMap` (sibling of `Node`
     * and `UserProfile`) — `Node.CapMap` holds unrelated system capabilities
     * and is never read. A no-grant identity returns `"CapMap": null`. The
     * parser reads only its own configured capability name: CapMap is
     * dst-scoped (verified 2026-08-31), but selecting defensively keeps that
     * a property of this code rather than of Tailscale's scoping.
     *
     * @param  array<string, mixed>  $data
     * @return list<array<string, mixed>>
     */
    private function extractCapabilities(array $data, string $capabilityName): array
    {
        $capMap = $data['CapMap'] ?? null; // null, absent, or {} all mean: no grant
        $values = is_array($capMap) ? ($capMap[$capabilityName] ?? null) : null;

        if (! is_array($values) || $values === []) {
            throw new IdentityRejectedException("No '{$capabilityName}' capability granted to this identity.");
        }

        $objects = array_values(array_filter($values, 'is_array'));
        if ($objects === []) {
            throw new IdentityRejectedException("Capability '{$capabilityName}' carried no usable values.");
        }

        return $objects;
    }
}
