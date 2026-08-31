<?php

declare(strict_types=1);

namespace Geocodio\TailscaleIdentity;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class TailscaleIdentityServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-tailscale-identity')
            ->hasConfigFile();
    }

    public function packageRegistered(): void
    {
        $this->app->bind(IdentityDriver::class, function (): IdentityDriver {
            $driver = (string) config('tailscale-identity.driver');
            FakeDriverGuard::check($driver, $this->app->environment());

            return match ($driver) {
                'whois' => new TailscaleWhoisDriver(
                    parser: new WhoisResponseParser,
                    capabilityName: $this->requiredCapabilityName(),
                    binary: (string) config('tailscale-identity.binary'),
                    processTimeout: (int) config('tailscale-identity.process_timeout'),
                    cacheTtl: (int) config('tailscale-identity.cache_ttl'),
                ),
                'fake' => new FakeIdentityDriver(
                    email: config('tailscale-identity.fake.email'),
                    displayName: (string) config('tailscale-identity.fake.display_name'),
                    capabilities: (array) config('tailscale-identity.fake.capabilities'),
                ),
                default => throw new \InvalidArgumentException("Unknown Tailscale identity driver [{$driver}]."),
            };
        });
    }

    public function packageBooted(): void
    {
        // Boot-time guard: a fake driver in the wrong environment fails at
        // boot, not on the first request.
        FakeDriverGuard::check((string) config('tailscale-identity.driver'), $this->app->environment());
    }

    private function requiredCapabilityName(): string
    {
        $name = config('tailscale-identity.capability');

        if (! is_string($name) || $name === '') {
            throw new \InvalidArgumentException(
                'tailscale-identity.capability must be set for the whois driver — it is the access gate.'
            );
        }

        return $name;
    }
}
