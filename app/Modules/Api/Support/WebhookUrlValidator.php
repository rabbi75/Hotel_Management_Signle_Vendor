<?php

declare(strict_types=1);

namespace App\Modules\Api\Support;

use Closure;

/**
 * SSRF guard for customer-supplied webhook URLs.
 *
 * A hostname string tells you nothing: `evil.example.com` may resolve to
 * 127.0.0.1, and a DNS record can change between validation and delivery. So
 * the host is resolved and every resulting address is checked, and the same
 * check is re-run on each redirect hop by the delivery job.
 */
final class WebhookUrlValidator
{
    /**
     * Ranges that are never a legitimate webhook destination. Checked in
     * addition to PHP's own private/reserved filters, which miss the cloud
     * metadata endpoints and IPv6 unique-local space on some builds.
     *
     * @var list<array{0: string, 1: int}>
     */
    private const BLOCKED_CIDRS = [
        ['0.0.0.0', 8],
        ['10.0.0.0', 8],
        ['100.64.0.0', 10],
        ['127.0.0.0', 8],
        ['169.254.0.0', 16],   // link-local, includes 169.254.169.254 metadata
        ['172.16.0.0', 12],
        ['192.0.0.0', 24],
        ['192.168.0.0', 16],
        ['198.18.0.0', 15],
        ['224.0.0.0', 4],
        ['240.0.0.0', 4],
    ];

    /**
     * Overrides host resolution. Tests use it so the suite never depends on
     * live DNS; nothing in the application calls it.
     *
     * @var (Closure(string): list<string>)|null
     */
    private static ?Closure $resolver = null;

    /**
     * @param  (Closure(string): list<string>)|null  $resolver
     */
    public static function resolveUsing(?Closure $resolver): void
    {
        self::$resolver = $resolver;
    }

    /**
     * @return string|null The reason the URL was rejected, or null when it is safe.
     */
    public static function reject(string $url): ?string
    {
        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            return __('The webhook URL is not a valid absolute URL.');
        }

        if (mb_strtolower($parts['scheme']) !== 'https') {
            return __('Webhook URLs must use HTTPS.');
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            return __('Webhook URLs may not embed credentials.');
        }

        return self::rejectHost($parts['host']);
    }

    /**
     * @return string|null The reason the host was rejected, or null when it is safe.
     */
    public static function rejectHost(string $host): ?string
    {
        $host = trim($host, '[]');

        if ($host === '' || str_ends_with(mb_strtolower($host), '.localhost') || mb_strtolower($host) === 'localhost') {
            return __('Webhook URLs may not point at a local address.');
        }

        $addresses = self::resolve($host);

        if ($addresses === []) {
            return __('The webhook host could not be resolved.');
        }

        foreach ($addresses as $address) {
            if (self::isBlockedAddress($address)) {
                return __('The webhook host resolves to a private or reserved address.');
            }
        }

        return null;
    }

    public static function isBlockedAddress(string $address): bool
    {
        if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            foreach (self::BLOCKED_CIDRS as [$subnet, $bits]) {
                if (self::inCidr($address, $subnet, $bits)) {
                    return true;
                }
            }

            return false;
        }

        if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) === false) {
            return true;
        }

        $packed = inet_pton($address);

        if ($packed === false) {
            return true;
        }

        // ::1 loopback, fc00::/7 unique-local, fe80::/10 link-local.
        $first = ord($packed[0]);

        return $packed === inet_pton('::1')
            || ($first & 0xFE) === 0xFC
            || ($first === 0xFE && (ord($packed[1]) & 0xC0) === 0x80);
    }

    /**
     * @return list<string>
     */
    private static function resolve(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return [$host];
        }

        if (self::$resolver instanceof Closure) {
            return (self::$resolver)($host);
        }

        $records = @dns_get_record($host, DNS_A | DNS_AAAA);
        $addresses = [];

        foreach ($records === false ? [] : $records as $record) {
            if (isset($record['ip']) && is_string($record['ip'])) {
                $addresses[] = $record['ip'];
            }

            if (isset($record['ipv6']) && is_string($record['ipv6'])) {
                $addresses[] = $record['ipv6'];
            }
        }

        if ($addresses === []) {
            $resolved = gethostbyname($host);

            if ($resolved !== $host) {
                $addresses[] = $resolved;
            }
        }

        return array_values(array_unique($addresses));
    }

    private static function inCidr(string $address, string $subnet, int $bits): bool
    {
        $ip = ip2long($address);
        $net = ip2long($subnet);

        if ($ip === false || $net === false) {
            return false;
        }

        $mask = $bits === 0 ? 0 : -1 << (32 - $bits);

        return ($ip & $mask) === ($net & $mask);
    }
}
