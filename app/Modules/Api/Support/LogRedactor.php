<?php

declare(strict_types=1);

namespace App\Modules\Api\Support;

/**
 * Removes credentials from anything that is about to be persisted to the API
 * request log.
 *
 * Redaction walks the whole structure rather than the top level only: a token
 * nested three levels deep inside a JSON body is exactly as damaging as one in
 * a header, and payload shapes are not ours to predict.
 */
final class LogRedactor
{
    public const REDACTED = '[redacted]';

    /**
     * Matched case-insensitively against the *whole* key, and as a substring so
     * `x_api_key` and `refresh_token` are caught too.
     *
     * @var list<string>
     */
    private const SENSITIVE = [
        'authorization', 'password', 'password_confirmation', 'token', 'secret',
        'api_key', 'apikey', 'access_key', 'private_key', 'credit_card', 'cvv',
    ];

    /**
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    public static function redact(array $data, int $depth = 0): array
    {
        if ($depth > 12) {
            return [self::REDACTED];
        }

        $result = [];

        foreach ($data as $key => $value) {
            if (is_string($key) && self::isSensitive($key)) {
                $result[$key] = self::REDACTED;

                continue;
            }

            $result[$key] = is_array($value) ? self::redact($value, $depth + 1) : $value;
        }

        return $result;
    }

    public static function isSensitive(string $key): bool
    {
        $normalised = str_replace(['-', ' '], '_', mb_strtolower($key));

        foreach (self::SENSITIVE as $needle) {
            if (str_contains($normalised, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Cap the stored payload so one oversized request cannot bloat the log
     * table; truncation happens after redaction, never instead of it.
     *
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    public static function truncate(array $data, int $maxBytes = 16384): array
    {
        $encoded = json_encode($data);

        if ($encoded === false || strlen($encoded) <= $maxBytes) {
            return $data;
        }

        return ['_truncated' => true, '_bytes' => strlen($encoded)];
    }
}
