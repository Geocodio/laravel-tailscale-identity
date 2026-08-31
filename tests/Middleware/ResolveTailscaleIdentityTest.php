<?php

declare(strict_types=1);

use Geocodio\TailscaleIdentity\Middleware\ResolveTailscaleIdentity;
use Geocodio\TailscaleIdentity\TailscaleIdentity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    config()->set('tailscale-identity.driver', 'fake');
    config()->set('tailscale-identity.fake.email', 'dev@example.com');
    config()->set('tailscale-identity.fake.capabilities', [['role' => 'admin']]);

    Route::middleware('tailscale.identity')->get('/probe', function (Request $request) {
        $identity = $request->attributes->get(ResolveTailscaleIdentity::ATTRIBUTE);

        return response()->json(['email' => $identity instanceof TailscaleIdentity ? $identity->email : null]);
    });
});

it('resolves the identity and exposes it as a request attribute', function () {
    $this->get('/probe')->assertOk()->assertJson(['email' => 'dev@example.com']);
});

it('403s on rejection', function () {
    config()->set('tailscale-identity.fake.email', null); // fake driver rejects

    $this->get('/probe')->assertForbidden();
});

it('ignores forwarded headers — identity comes only from REMOTE_ADDR', function () {
    config()->set('tailscale-identity.driver', 'whois');
    config()->set('tailscale-identity.capability', 'example.com/cap/my-app');
    Process::fake();

    // REMOTE_ADDR in tests is 127.0.0.1 — outside the Tailscale range — so the
    // whois driver must reject regardless of any spoofed forwarded header.
    $this->get('/probe', ['X-Real-IP' => '100.101.102.103', 'X-Forwarded-For' => '100.101.102.103'])
        ->assertForbidden();

    Process::assertNothingRan();
});

it('503s and never grants anonymous access when the lookup fails', function () {
    config()->set('tailscale-identity.driver', 'whois');
    config()->set('tailscale-identity.capability', 'example.com/cap/my-app');
    Process::fake(['*' => Process::result(exitCode: 1)]);

    $this->withServerVariables(['REMOTE_ADDR' => '100.101.102.103'])
        ->get('/probe')
        ->assertServiceUnavailable();
});
