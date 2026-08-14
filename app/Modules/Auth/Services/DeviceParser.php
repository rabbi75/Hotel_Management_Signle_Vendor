<?php

declare(strict_types=1);

namespace App\Modules\Auth\Services;

/**
 * A deliberately small user-agent classifier.
 *
 * Full UA parsing needs a maintained database and belongs in a package; all the
 * session list needs is enough to let someone recognise their own devices, so
 * this matches the handful of tokens that actually distinguish them.
 */
class DeviceParser
{
    /**
     * @return array{device: string, platform: string, browser: string}
     */
    public function parse(?string $userAgent): array
    {
        $agent = $userAgent ?? '';

        return [
            'device' => $this->device($agent),
            'platform' => $this->platform($agent),
            'browser' => $this->browser($agent),
        ];
    }

    public function device(string $agent): string
    {
        return match (true) {
            $this->matches($agent, ['ipad', 'tablet', 'playbook', 'silk']) => 'tablet',
            $this->matches($agent, ['mobile', 'iphone', 'ipod', 'android', 'blackberry', 'windows phone']) => 'mobile',
            $agent === '' => 'unknown',
            default => 'desktop',
        };
    }

    public function platform(string $agent): string
    {
        return match (true) {
            $this->matches($agent, ['windows nt', 'win64', 'win32']) => 'Windows',
            $this->matches($agent, ['iphone', 'ipad', 'ipod']) => 'iOS',
            $this->matches($agent, ['mac os x', 'macintosh']) => 'macOS',
            $this->matches($agent, ['android']) => 'Android',
            $this->matches($agent, ['cros']) => 'ChromeOS',
            $this->matches($agent, ['ubuntu', 'linux', 'x11']) => 'Linux',
            default => 'Unknown',
        };
    }

    public function browser(string $agent): string
    {
        return match (true) {
            $this->matches($agent, ['edg/', 'edge']) => 'Edge',
            $this->matches($agent, ['opr/', 'opera']) => 'Opera',
            $this->matches($agent, ['samsungbrowser']) => 'Samsung Internet',
            $this->matches($agent, ['firefox']) => 'Firefox',
            // Chrome advertises Safari, so Chrome has to be ruled out first.
            $this->matches($agent, ['chrome', 'crios']) => 'Chrome',
            $this->matches($agent, ['safari']) => 'Safari',
            default => 'Unknown',
        };
    }

    /**
     * @param  list<string>  $needles
     */
    protected function matches(string $agent, array $needles): bool
    {
        $haystack = mb_strtolower($agent);

        foreach ($needles as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }
}
