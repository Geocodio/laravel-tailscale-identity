# Changelog

All notable changes to `laravel-tailscale-identity` will be documented in this file.

## v0.1.0 - 2026-08-31

Initial release.

Resolves Tailscale tailnet identity for Laravel apps via `tailscale whois`, with app-capability (grant) values as the access gate. Fail-closed throughout: 403 for every rejection, 503 when the lookup infrastructure is unavailable.

**Read the README's deployment requirements before installing** — the security model depends on your proxy chain guaranteeing `REMOTE_ADDR` is the genuine tailnet peer.

Versioning: `0.x` while the API settles; `1.0.0` once battle-tested by its first consumers.
