<?php

declare(strict_types=1);

use Geocodio\TailscaleIdentity\Exceptions\IdentityRejectedException;
use Geocodio\TailscaleIdentity\FakeDriverGuard;
use Geocodio\TailscaleIdentity\FakeIdentityDriver;

it('returns the configured fake identity', function () {
    $driver = new FakeIdentityDriver('Dev@example.com', 'Dev User', [['role' => 'admin']]);

    $identity = $driver->identify('100.64.0.9');

    expect($identity->email)->toBe('dev@example.com')
        ->and($identity->displayName)->toBe('Dev User')
        ->and($identity->nodeName)->toBe('fake-node.example.ts.net.')
        ->and($identity->peerAddress)->toBe('100.64.0.9')
        ->and($identity->capabilities)->toBe([['role' => 'admin']]);
});

it('rejects when no fake identity is configured', function () {
    (new FakeIdentityDriver(null))->identify('100.64.0.9');
})->throws(IdentityRejectedException::class);

it('guard refuses the fake driver outside local and testing', function (string $environment) {
    FakeDriverGuard::check('fake', $environment);
})->with(['production', 'staging'])->throws(RuntimeException::class);

it('guard allows the fake driver in local and testing, and any real driver anywhere', function () {
    FakeDriverGuard::check('fake', 'local');
    FakeDriverGuard::check('fake', 'testing');
    FakeDriverGuard::check('whois', 'production');

    expect(true)->toBeTrue();
});
