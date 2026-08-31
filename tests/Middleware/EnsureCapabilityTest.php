<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

beforeEach(function () {
    config()->set('tailscale-identity.driver', 'fake');
    config()->set('tailscale-identity.fake.email', 'dev@example.com');
    config()->set('tailscale-identity.fake.capabilities', [['role' => 'viewer'], ['role' => 'agent']]);

    Route::middleware(['tailscale.identity', 'tailscale.capability:role,agent'])
        ->get('/agent-only', fn () => response()->json(['ok' => true]));
    Route::middleware(['tailscale.identity', 'tailscale.capability:role,admin'])
        ->get('/admin-only', fn () => response()->json(['ok' => true]));
    Route::middleware('tailscale.capability:role,agent')
        ->get('/misordered', fn () => response()->json(['ok' => true]));
    Route::middleware(['tailscale.identity', 'tailscale.capability'])
        ->get('/any-identity', fn () => response()->json(['ok' => true]));
});

it('passes when some capability object carries the required key/value', function () {
    $this->get('/agent-only')->assertOk();
});

it('403s when no capability object matches', function () {
    $this->get('/admin-only')->assertForbidden();
});

it('403s when no identity was resolved — middleware misordering fails closed', function () {
    $this->get('/misordered')->assertForbidden();
});

it('with no parameters, only asserts an identity was resolved', function () {
    $this->get('/any-identity')->assertOk();
});
