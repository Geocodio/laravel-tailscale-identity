<?php

declare(strict_types=1);

use Geocodio\TailscaleIdentity\Exceptions\IdentityLookupException;
use Geocodio\TailscaleIdentity\Exceptions\IdentityRejectedException;

it('exposes the two exception types callers switch on', function () {
    expect(new IdentityLookupException('x'))->toBeInstanceOf(RuntimeException::class)
        ->and(new IdentityRejectedException('x'))->toBeInstanceOf(RuntimeException::class);
});
