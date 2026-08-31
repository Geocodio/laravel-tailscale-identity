<?php

declare(strict_types=1);

namespace Geocodio\TailscaleIdentity\Commands;

use Geocodio\TailscaleIdentity\TailscaleAddressRange;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Process;

/**
 * Asserts the deployed chain is sound. The one thing code cannot verify —
 * that exactly one proxy always overwrites X-Real-IP — is a deployment
 * property; this command checks everything that IS checkable from inside.
 */
final class VerifyTopologyCommand extends Command
{
    protected $signature = 'tailscale-identity:verify-topology';

    protected $description = 'Verify the Tailscale identity deployment chain: CLI, socket, address ranges, trusted-proxy config';

    public function handle(): int
    {
        $binary = (string) config('tailscale-identity.binary', 'tailscale');
        $ok = true;

        $version = Process::timeout(5)->run([$binary, 'version']);
        $ok = $this->report('tailscale CLI present', $version->successful(), trim($version->errorOutput())) && $ok;

        $status = Process::timeout(5)->run([$binary, 'status', '--json']);
        $ok = $this->report('LocalAPI socket reachable', $status->successful(), trim($status->errorOutput())) && $ok;

        if ($status->successful()) {
            /** @var array<string, mixed> $data */
            $data = json_decode($status->output(), true) ?? [];
            $self = is_array($data['Self'] ?? null) ? $data['Self'] : [];
            $ips = array_values(array_filter((array) ($self['TailscaleIPs'] ?? []), 'is_string'));
            $inRange = $ips !== [] && ! in_array(false, array_map(TailscaleAddressRange::contains(...), $ips), true);
            $ok = $this->report('node addresses inside Tailscale ranges', $inRange, implode(', ', $ips)) && $ok;
        }

        $proxies = Request::getTrustedProxies();
        $wildcarded = array_intersect(['*', '0.0.0.0/0', '::/0'], $proxies) !== [];
        $ok = $this->report('trusted proxies not wildcarded', ! $wildcarded, implode(', ', $proxies) ?: '(none)') && $ok;

        return $ok ? self::SUCCESS : self::FAILURE;
    }

    private function report(string $check, bool $passed, string $detail = ''): bool
    {
        $this->line(sprintf('[%s] %s%s', $passed ? 'PASS' : 'FAIL', $check, $detail !== '' ? " — {$detail}" : ''));

        return $passed;
    }
}
