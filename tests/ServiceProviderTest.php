<?php

declare(strict_types=1);

use Geocodio\TailscaleIdentity\FakeIdentityDriver;
use Geocodio\TailscaleIdentity\IdentityDriver;
use Geocodio\TailscaleIdentity\TailscaleWhoisDriver;

it('binds the whois driver by default', function () {
    config()->set('tailscale-identity.capability', 'example.com/cap/my-app');

    expect(app(IdentityDriver::class))->toBeInstanceOf(TailscaleWhoisDriver::class);
});

it('binds the fake driver when configured (testing env is allowed)', function () {
    config()->set('tailscale-identity.driver', 'fake');
    config()->set('tailscale-identity.fake.email', 'dev@example.com');

    expect(app(IdentityDriver::class))->toBeInstanceOf(FakeIdentityDriver::class);
});

it('throws on an unknown driver', function () {
    config()->set('tailscale-identity.driver', 'nope');

    app(IdentityDriver::class);
})->throws(InvalidArgumentException::class);

it('requires a capability name for the whois driver', function () {
    config()->set('tailscale-identity.capability', null);

    app(IdentityDriver::class);
})->throws(InvalidArgumentException::class, 'capability');
