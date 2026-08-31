<?php

declare(strict_types=1);

namespace Geocodio\TailscaleIdentity\Tests;

use Geocodio\TailscaleIdentity\TailscaleIdentityServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            TailscaleIdentityServiceProvider::class,
        ];
    }
}
