<?php

declare(strict_types=1);

use Geocodio\TailscaleIdentity\TailscaleAddressRange;

it('accepts addresses inside the Tailscale ranges', function (string $ip) {
    expect(TailscaleAddressRange::contains($ip))->toBeTrue();
})->with([
    '100.64.0.0',
    '100.101.102.103',
    '100.127.255.255',
    'fd7a:115c:a1e0::1',
    'fd7a:115c:a1e0:ab12::4321',
]);

it('rejects everything else — bridge gateways, public and private addresses, garbage', function (string $ip) {
    expect(TailscaleAddressRange::contains($ip))->toBeFalse();
})->with([
    '172.17.0.1',
    '127.0.0.1',
    '100.63.255.255',
    '100.128.0.0',
    '8.8.8.8',
    '192.168.1.1',
    'fd7a:115c:a1e1::1',
    '::1',
    'not-an-ip',
    '',
]);
