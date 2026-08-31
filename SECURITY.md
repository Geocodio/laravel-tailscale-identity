# Security Policy

## Supported Versions

| Version | Supported |
| ------- | --------- |
| 0.x (latest release) | ✅ |

During the `0.x` series only the latest release receives security fixes.

## Reporting a Vulnerability

Please **do not** open a public issue for security problems.

Report vulnerabilities to **security@geocod.io**, or privately via
[GitHub security advisories](https://github.com/geocodio/laravel-tailscale-identity/security/advisories/new).

We will acknowledge your report within **3 business days**, keep you informed of
progress, and coordinate disclosure with you. We ask that you give us a
reasonable window to ship a fix before any public disclosure.

## Scope

This package's authentication guarantees depend on a correctly configured
deployment — in particular that exactly one proxy sets `X-Real-IP` and the
app-side server trusts it from that proxy alone. Reports about deployments
that violate the documented requirements (see the README's *Deployment
requirements* section) are configuration issues rather than package
vulnerabilities — but if the documentation made the safe configuration
unclear, we absolutely want to hear about that too.
