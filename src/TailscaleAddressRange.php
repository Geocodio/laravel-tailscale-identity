<?php

declare(strict_types=1);

namespace Geocodio\TailscaleIdentity;

/**
 * Tailscale assigns node addresses from 100.64.0.0/10 (CGNAT) and
 * fd7a:115c:a1e0::/48. A peer outside both is a deployment
 * misconfiguration (docker bridge gateway, public IP) — reject before
 * whois ever runs.
 */
final class TailscaleAddressRange
{
    public static function contains(string $address): bool
    {
        $binary = @inet_pton($address);
        if ($binary === false) {
            return false;
        }

        if (strlen($binary) === 4) {
            // 100.64.0.0/10: first octet 100, top two bits of the second octet 01.
            return ord($binary[0]) === 100
                && (ord($binary[1]) & 0b11000000) === 0b01000000;
        }

        // fd7a:115c:a1e0::/48: first six bytes fixed.
        return substr($binary, 0, 6) === "\xfd\x7a\x11\x5c\xa1\xe0";
    }
}
