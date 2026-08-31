<?php

return [

    // 'whois' (production) or 'fake' (local/testing only — boot-guarded:
    // the app refuses to boot with the fake driver in any other environment).
    'driver' => env('TAILSCALE_IDENTITY_DRIVER', 'whois'),

    // The app capability name your tailnet policy grants use, e.g.
    // "example.com/cap/my-app". Required for the whois driver; identities
    // without this capability are rejected (403) — it is the access gate.
    'capability' => env('TAILSCALE_IDENTITY_CAPABILITY'),

    // Path to the tailscale CLI inside the environment serving requests.
    'binary' => env('TAILSCALE_IDENTITY_BINARY', 'tailscale'),

    // Seconds before a hung `tailscale whois` process is abandoned (503).
    'process_timeout' => 3,

    // Per-address cache TTL (seconds) for the PARSED FIELDS — never the DTO
    // (see README). Tailscale addresses are node-stable, so a short TTL is safe.
    'cache_ttl' => (int) env('TAILSCALE_IDENTITY_CACHE_TTL', 60),

    'fake' => [
        // The identity every request resolves to under the fake driver.
        'email' => env('TAILSCALE_IDENTITY_FAKE_EMAIL'),
        'display_name' => env('TAILSCALE_IDENTITY_FAKE_NAME', 'Fake Identity'),
        // Capability objects the fake identity carries, e.g. [['role' => 'admin']].
        'capabilities' => [['role' => 'admin']],
    ],

];
