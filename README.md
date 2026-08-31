# Tailscale identity for Laravel

[![Latest Version on Packagist](https://img.shields.io/packagist/v/geocodio/laravel-tailscale-identity.svg?style=flat-square)](https://packagist.org/packages/geocodio/laravel-tailscale-identity)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/geocodio/laravel-tailscale-identity/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/geocodio/laravel-tailscale-identity/actions?query=workflow%3Arun-tests+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/geocodio/laravel-tailscale-identity.svg?style=flat-square)](https://packagist.org/packages/geocodio/laravel-tailscale-identity)

Authenticate internal Laravel apps with your [Tailscale](https://tailscale.com) tailnet instead of passwords: the tailnet answers *who is connecting*, and a Tailscale [grant](https://tailscale.com/kb/1324/grants) (app capability) answers *what they may do* — both defined in your tailnet policy file, reviewed like any other code.

The package resolves the connecting peer's address with `tailscale whois`, applies a strict set of rejections, and hands your app a `TailscaleIdentity` value object: email, display name, node name, peer address, and the raw capability values from your policy's grants. No password, no session to steal, no user admin UI to build. Removing someone from the tailnet (or the grant) removes their access.

It is intentionally small and fail-closed. It does **not** touch your user model, define roles, or render error pages — your app decides what a capability value means.

## Deployment requirements — read this before installing

**The security model is the network binding, not this code.** Identity is derived from `REMOTE_ADDR`, so your deployment must guarantee that `REMOTE_ADDR` is the genuine tailnet peer address by the time PHP sees the request. Four requirements, each of which silently breaks authentication if missed:

1. **The tailnet-facing proxy is the only listener on the Tailscale interface, and it always overwrites `X-Real-IP`.** Never append, never trust the inbound value.
2. **The app-side server trusts `X-Real-IP` from exactly one place** — the loopback (or private) address that proxy connects from. Nothing else may set it.
3. **`/var/run/tailscale` is mounted into the app container** (if you use containers). Host networking does *not* expose unix sockets — that is a mount namespace, not a network namespace.
4. **The `tailscale` CLI is present in the app image.** No daemon runs in-container; the CLI only talks to the mounted socket.

A worked two-tier nginx example:

```nginx
# Host nginx — bound to the Tailscale interface ONLY (e.g. 100.x.y.z:443).
server {
    listen 100.x.y.z:443 ssl;          # the host's tailnet address — never 0.0.0.0
    server_name app.example.com;

    location / {
        proxy_pass http://127.0.0.1:8080;
        proxy_set_header X-Real-IP $remote_addr;   # always overwritten, never appended
        proxy_set_header Host $host;
    }
}
```

```nginx
# Container nginx — in front of php-fpm.
server {
    listen 127.0.0.1:8080;             # loopback only; fail closed if unset

    set_real_ip_from 127.0.0.1;        # trust X-Real-IP from the host proxy ONLY
    real_ip_header   X-Real-IP;

    # ... fastcgi_pass to php-fpm; REMOTE_ADDR is now the tailnet peer
}
```

> [!WARNING]
> **If you cannot guarantee that exactly one proxy sets `X-Real-IP` and nothing else can, do not use this package.**

### What this package cannot protect against

An over-broad `set_real_ip_from`. If your app-side server trusts `X-Real-IP` from anything but the single proxy that always overwrites it, a tailnet member can present another member's address; whois will confirm that member, and the address check will pass. That is a **deployment property, not a code property**. The `tailscale-identity:verify-topology` command exists to make the checkable parts checkable:

```bash
php artisan tailscale-identity:verify-topology
```

It asserts the `tailscale` CLI is present, the LocalAPI socket is reachable, the node's addresses fall inside Tailscale's ranges, and Laravel's trusted-proxy configuration is not wildcarded. Run it as a deploy smoke test.

## Installation

```bash
composer require geocodio/laravel-tailscale-identity
php artisan vendor:publish --tag="tailscale-identity-config"
```

Set your capability name in `.env`:

```dotenv
TAILSCALE_IDENTITY_CAPABILITY=example.com/cap/my-app
```

## Granting access in your tailnet policy

Access is granted with an [app capability grant](https://tailscale.com/kb/1324/grants) in your tailnet policy file. Grants coexist with legacy `ACLs`, so no migration is needed:

```jsonc
"grants": [
    {
        "src": ["group:my-app-admins"],
        "dst": ["tag:my-app-server"],
        "app": { "example.com/cap/my-app": [{ "role": "admin" }] }
    },
    {
        "src": ["group:my-app-viewers"],
        "dst": ["tag:my-app-server"],
        "app": { "example.com/cap/my-app": [{ "role": "viewer" }] }
    }
]
```

The capability name is a namespaced string you choose (conventionally a domain you control). The values (`{"role": "admin"}`) are arbitrary JSON objects — this package returns them as data and never interprets them. **No capability means no access (403):** the grant is the access gate, so there is no in-app role admin to build or drift out of sync.

## Usage

### Middleware

```php
// routes/web.php
Route::middleware('tailscale.identity')->group(function () {
    Route::get('/dashboard', DashboardController::class);

    // Gate on a raw capability value: passes iff some capability object
    // has ['role' => 'admin'].
    Route::middleware('tailscale.capability:role,admin')->group(function () {
        Route::get('/admin', AdminController::class);
    });
});
```

`tailscale.identity` resolves the peer and exposes the identity; it returns **403** for any rejection and **503** when the lookup infrastructure fails (never anonymous access). It deliberately does not log anyone into Laravel's auth system — read the identity and decide yourself:

```php
use Geocodio\TailscaleIdentity\Middleware\ResolveTailscaleIdentity;
use Geocodio\TailscaleIdentity\TailscaleIdentity;

$identity = $request->attributes->get(ResolveTailscaleIdentity::ATTRIBUTE);

$identity->email;        // "person@example.com" (lowercased login)
$identity->displayName;  // "Person Example"
$identity->nodeName;     // "laptop.example.ts.net."
$identity->peerAddress;  // "100.101.102.103"
$identity->capabilities; // [["role" => "admin"], ...] — raw grant values
```

A typical app layers just-in-time provisioning on top in its own middleware:

```php
$user = User::firstOrCreate(['email' => $identity->email], ['name' => $identity->displayName]);
Auth::setUser($user);
```

Someone in two groups receives two capability objects. Reducing them to one effective role needs an ordering only your app can know — do it where you map capabilities to your role enum.

### Rejections

Every check fails closed. A request is rejected (403) when:

- the peer address is outside Tailscale's ranges (`100.64.0.0/10`, `fd7a:115c:a1e0::/48`) — the misconfiguration guard: a docker bridge gateway or public address fails here rather than reaching whois;
- the node is **tagged** (tags identify machines, not people);
- the node is a **subnet router** (relayed users arrive SNAT'd as the router — they must connect directly);
- the queried address is not among the node's addresses;
- the login name is unusable (empty, `tagged-devices`, or lacking `@`);
- the identity holds **no values for your configured capability name**.

An unreachable `tailscaled` or failing CLI is a 503, never anonymous access.

### Local development and testing

The `fake` driver serves a configured identity without a tailnet. A boot guard refuses to start the app if it is active outside `local`/`testing` environments:

```dotenv
TAILSCALE_IDENTITY_DRIVER=fake
TAILSCALE_IDENTITY_FAKE_EMAIL=dev@example.com
```

```php
// In tests:
config()->set('tailscale-identity.driver', 'fake');
config()->set('tailscale-identity.fake.email', 'dev@example.com');
config()->set('tailscale-identity.fake.capabilities', [['role' => 'admin']]);
```

### Caching

Lookups are cached per address for 60 seconds by default (`TAILSCALE_IDENTITY_CACHE_TTL`). The cache stores the parsed **fields as a plain array — never the DTO**: Laravel defaults `cache.serializable_classes` to `false`, under which any object read back from a serializing store (like Redis) returns `__PHP_Incomplete_Class`. Caching an identity object works exactly once per address and then errors for the rest of the TTL; this package does not do that, and the versioned key prefix means upgrades never read old-shape entries.

### Why `whois` and not Tailscale Serve's identity headers?

Serve injects `Tailscale-User-Login` headers with no subprocess or socket mount — but Serve must terminate the connection to identify the peer, and it can only terminate on a `<device>.<tailnet>.ts.net` name. If you want your own hostname and certificate, identity has to come out-of-band from the peer address, which is what this package does. whois also returns the full node record, enabling the tagged-node and subnet-router rejections the headers cannot express.

A header-based driver for Serve deployments is out of scope for v1; the `IdentityDriver` interface is the seam where one would slot in.

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Mathias Hansen](https://github.com/MathiasHansen)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
