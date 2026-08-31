<?php

declare(strict_types=1);

use Geocodio\TailscaleIdentity\Exceptions\IdentityRejectedException;
use Geocodio\TailscaleIdentity\WhoisResponseParser;

const CAP = 'example.com/cap/my-app';

function whoisFixture(string $name): string
{
    return file_get_contents(__DIR__.'/Fixtures/whois/'.$name.'.json');
}

/** @param array<string, mixed> $overrides */
function whoisJson(array $overrides = []): string
{
    return json_encode(array_merge(json_decode(whoisFixture('user-node'), true), $overrides));
}

it('parses a user node into an identity with lowercased email and capability values', function () {
    $identity = (new WhoisResponseParser)->parse(whoisFixture('user-node'), '100.101.102.103', CAP);

    expect($identity->email)->toBe('user@example.com')
        ->and($identity->displayName)->toBe('Example User')
        ->and($identity->nodeName)->toBe('laptop.example.ts.net.')
        ->and($identity->peerAddress)->toBe('100.101.102.103')
        ->and($identity->capabilities)->toBe([['role' => 'admin']]);
});

it('returns every capability object when multiple grants apply', function () {
    $identity = (new WhoisResponseParser)->parse(whoisFixture('user-node-multi-cap'), '100.101.102.103', CAP);

    expect($identity->capabilities)->toBe([['role' => 'admin'], ['role' => 'viewer']]);
});

it('rejects tagged nodes — tags identify machines, not people', function () {
    (new WhoisResponseParser)->parse(whoisFixture('tagged-node'), '100.101.102.103', CAP);
})->throws(IdentityRejectedException::class, 'tagged');

it('rejects subnet routers — relayed users must connect directly', function () {
    (new WhoisResponseParser)->parse(whoisFixture('subnet-router'), '100.101.102.103', CAP);
})->throws(IdentityRejectedException::class, 'subnet router');

it('rejects when the queried IP is not among the node addresses', function () {
    (new WhoisResponseParser)->parse(whoisFixture('user-node'), '100.1.1.1', CAP);
})->throws(IdentityRejectedException::class, 'address mismatch');

it('rejects malformed JSON', function () {
    (new WhoisResponseParser)->parse('not-json', '100.101.102.103', CAP);
})->throws(IdentityRejectedException::class);

it('rejects unusable logins', function (string $login) {
    (new WhoisResponseParser)->parse(whoisJson([
        'UserProfile' => ['LoginName' => $login, 'DisplayName' => ''],
    ]), '100.101.102.103', CAP);
})->with(['', 'tagged-devices', 'no-at-sign'])->throws(IdentityRejectedException::class);

it('rejects when the configured capability is absent — null CapMap, as whois returns for a no-grant identity', function () {
    (new WhoisResponseParser)->parse(whoisFixture('user-node-no-cap'), '100.101.102.103', CAP);
})->throws(IdentityRejectedException::class, 'capability');

it('rejects when CapMap is an empty map', function () {
    (new WhoisResponseParser)->parse(whoisJson(['CapMap' => []]), '100.101.102.103', CAP);
})->throws(IdentityRejectedException::class, 'capability');

it('rejects when the CapMap key is missing entirely', function () {
    $data = json_decode(whoisFixture('user-node'), true);
    unset($data['CapMap']);

    (new WhoisResponseParser)->parse(json_encode($data), '100.101.102.103', CAP);
})->throws(IdentityRejectedException::class, 'capability');

it("rejects when only a different app's capability is present — defensive even though CapMap is dst-scoped", function () {
    (new WhoisResponseParser)->parse(whoisFixture('user-node-foreign-cap'), '100.101.102.103', CAP);
})->throws(IdentityRejectedException::class, 'capability');

it('reads only the top-level CapMap, never Node.CapMap system capabilities', function () {
    // Real whois responses carry a second CapMap inside Node holding system
    // capabilities (funnel, https, ...). A grant name colliding there must not count.
    $data = json_decode(whoisFixture('user-node'), true);
    $data['Node']['CapMap'] = [CAP => [['role' => 'admin']]];
    $data['CapMap'] = null;

    (new WhoisResponseParser)->parse(json_encode($data), '100.101.102.103', CAP);
})->throws(IdentityRejectedException::class, 'capability');

it('rejects unexpected JSON shapes inside the capability value', function (mixed $value) {
    (new WhoisResponseParser)->parse(whoisJson(['CapMap' => [CAP => $value]]), '100.101.102.103', CAP);
})->with([
    'string value' => ['not-an-array'],
    'empty array' => [[]],
    'array of non-objects' => [[true, 'x', 3]],
])->throws(IdentityRejectedException::class);

it('keeps only object values when a capability array mixes shapes', function () {
    $identity = (new WhoisResponseParser)->parse(
        whoisJson(['CapMap' => [CAP => [true, ['role' => 'agent'], 'noise']]]),
        '100.101.102.103',
        CAP,
    );

    expect($identity->capabilities)->toBe([['role' => 'agent']]);
});
