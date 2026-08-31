<?php

declare(strict_types=1);

namespace Geocodio\TailscaleIdentity;

use Geocodio\TailscaleIdentity\Exceptions\IdentityRejectedException;

final readonly class FakeIdentityDriver implements IdentityDriver
{
    /** @param list<array<string, mixed>> $capabilities */
    public function __construct(
        private ?string $email,
        private string $displayName = 'Fake Identity',
        private array $capabilities = [],
    ) {}

    public function identify(string $remoteAddr): TailscaleIdentity
    {
        if ($this->email === null || $this->email === '') {
            throw new IdentityRejectedException('No fake identity configured.');
        }

        return new TailscaleIdentity(
            email: mb_strtolower($this->email),
            displayName: $this->displayName,
            nodeName: 'fake-node.example.ts.net.',
            peerAddress: $remoteAddr,
            capabilities: $this->capabilities,
        );
    }
}
