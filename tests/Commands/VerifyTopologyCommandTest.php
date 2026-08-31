<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Process;

const STATUS_JSON = '{"Self":{"TailscaleIPs":["100.101.102.103","fd7a:115c:a1e0::1"]}}';

beforeEach(function () {
    Request::setTrustedProxies([], Request::HEADER_X_FORWARDED_FOR);
});

it('passes when CLI, socket, address range and proxy config are all sound', function () {
    Process::fake([
        '*version*' => Process::result(output: "1.86.0\n"),
        '*status*' => Process::result(output: STATUS_JSON),
    ]);

    $this->artisan('tailscale-identity:verify-topology')->assertExitCode(0);
});

it('fails when the CLI is missing', function () {
    Process::fake(['*' => Process::result(exitCode: 127, errorOutput: 'not found')]);

    $this->artisan('tailscale-identity:verify-topology')->assertExitCode(1);
});

it('fails when the node addresses are outside the Tailscale ranges', function () {
    Process::fake([
        '*version*' => Process::result(output: "1.86.0\n"),
        '*status*' => Process::result(output: '{"Self":{"TailscaleIPs":["192.168.1.5"]}}'),
    ]);

    $this->artisan('tailscale-identity:verify-topology')->assertExitCode(1);
});

it('fails when trusted proxies are wildcarded', function () {
    Process::fake([
        '*version*' => Process::result(output: "1.86.0\n"),
        '*status*' => Process::result(output: STATUS_JSON),
    ]);
    Request::setTrustedProxies(['0.0.0.0/0'], Request::HEADER_X_FORWARDED_FOR);

    $this->artisan('tailscale-identity:verify-topology')->assertExitCode(1);
});
