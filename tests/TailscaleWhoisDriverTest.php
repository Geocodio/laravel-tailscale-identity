<?php

declare(strict_types=1);

use Geocodio\TailscaleIdentity\Exceptions\IdentityLookupException;
use Geocodio\TailscaleIdentity\Exceptions\IdentityRejectedException;
use Geocodio\TailscaleIdentity\TailscaleIdentity;
use Geocodio\TailscaleIdentity\TailscaleWhoisDriver;
use Geocodio\TailscaleIdentity\WhoisResponseParser;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Process;

const DRIVER_CAP = 'example.com/cap/my-app';

function makeDriver(): TailscaleWhoisDriver
{
    return new TailscaleWhoisDriver(new WhoisResponseParser, DRIVER_CAP);
}

function fakeWhois(): void
{
    Process::fake(['*' => Process::result(output: file_get_contents(__DIR__.'/Fixtures/whois/user-node.json'))]);
}

/**
 * Pin a cache store that actually serializes, with the production
 * serializable_classes=false setting. The default array store does not
 * serialize, which is exactly how the DTO-caching bug once shipped:
 * the failure mode is invisible under a non-serializing test store.
 */
beforeEach(function () {
    config()->set('cache.default', 'array');
    config()->set('cache.stores.array.serialize', true);
    config()->set('cache.serializable_classes', false);
    Cache::flush();
});

it('rejects a non-Tailscale peer address before ever running whois', function () {
    Process::fake();

    try {
        makeDriver()->identify('172.17.0.1');
        $this->fail('expected rejection');
    } catch (IdentityRejectedException) {
    }

    Process::assertNothingRan();
});

it('fails closed with a lookup exception when the whois process fails', function () {
    Process::fake(['*' => Process::result(exitCode: 1, errorOutput: 'no such peer')]);

    makeDriver()->identify('100.101.102.103');
})->throws(IdentityLookupException::class);

it('returns a real identity on a cache hit, not __PHP_Incomplete_Class', function () {
    fakeWhois();
    $driver = makeDriver();

    $first = $driver->identify('100.101.102.103');
    $second = $driver->identify('100.101.102.103'); // served from cache

    expect($first)->toBeInstanceOf(TailscaleIdentity::class)
        ->and($second)->toBeInstanceOf(TailscaleIdentity::class)
        ->and($second->email)->toBe('user@example.com')
        ->and($second->capabilities)->toBe([['role' => 'admin']])
        ->and($second->peerAddress)->toBe('100.101.102.103');
});

it('caches per address and only shells out once within the TTL', function () {
    fakeWhois();
    $driver = makeDriver();

    $driver->identify('100.101.102.103');
    $driver->identify('100.101.102.103');

    Process::assertRanTimes(fn ($p) => str_contains(implode(' ', (array) $p->command), 'whois'), 1);
});

it('stores only a plain array in the cache, behind the versioned prefix', function () {
    fakeWhois();

    makeDriver()->identify('100.101.102.103');

    $cached = Cache::get(TailscaleWhoisDriver::CACHE_PREFIX.'100.101.102.103');

    expect($cached)->toBeArray()
        ->and($cached)->toHaveKeys(['email', 'displayName', 'nodeName', 'peerAddress', 'capabilities']);
});

it('does not cache rejections as identities', function () {
    Process::fake(['*' => Process::result(output: file_get_contents(__DIR__.'/Fixtures/whois/tagged-node.json'))]);
    $driver = makeDriver();

    expect(fn () => $driver->identify('100.101.102.103'))->toThrow(IdentityRejectedException::class)
        ->and(Cache::get(TailscaleWhoisDriver::CACHE_PREFIX.'100.101.102.103'))->toBeNull();
});
