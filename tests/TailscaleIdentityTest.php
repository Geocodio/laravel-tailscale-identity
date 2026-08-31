<?php

declare(strict_types=1);

use Geocodio\TailscaleIdentity\TailscaleIdentity;

it('is a readonly value object carrying capability values as data', function () {
    $identity = new TailscaleIdentity(
        email: 'user@example.com',
        displayName: 'Example User',
        nodeName: 'laptop.example.ts.net.',
        peerAddress: '100.101.102.103',
        capabilities: [['role' => 'admin'], ['role' => 'viewer']],
    );

    expect($identity->email)->toBe('user@example.com')
        ->and($identity->capabilities)->toBe([['role' => 'admin'], ['role' => 'viewer']])
        ->and(new ReflectionClass($identity))->isReadOnly()->toBeTrue();
});
