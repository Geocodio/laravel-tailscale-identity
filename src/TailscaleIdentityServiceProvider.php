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
}
