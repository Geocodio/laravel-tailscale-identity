<?php

declare(strict_types=1);

namespace Geocodio\TailscaleIdentity;

final readonly class TailscaleIdentity
{
    /**
     * @param  list<array<string, mixed>>  $capabilities  Raw capability objects for the app's
     *                                                    configured capability name. Reducing these
     *                                                    to a role is the application's job.
     */
    public function __construct(
        public string $email,
        public string $displayName,
        public string $nodeName,
        public string $peerAddress,
        public array $capabilities,
    ) {}
}
