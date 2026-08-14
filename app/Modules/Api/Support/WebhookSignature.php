<?php

declare(strict_types=1);

namespace App\Modules\Api\Support;

/**
 * HMAC-SHA256 signatures over the raw delivery body.
 *
 * The timestamp is part of the signed material, not merely a sibling header:
 * signing the body alone would let anyone who captured one delivery replay it
 * verbatim for as long as the endpoint lives.
 */
final class WebhookSignature
{
    public const TIMESTAMP_HEADER = 'X-Signature-Timestamp';

    /**
     * How far a delivery's timestamp may drift before a receiver following our
     * documented verification recipe should reject it, in seconds.
     */
    public const TOLERANCE = 300;

    public static function sign(string $payload, string $secret, int $timestamp): string
    {
        return 'v1='.hash_hmac('sha256', self::signedPayload($payload, $timestamp), $secret);
    }

    public static function verify(string $payload, string $secret, int $timestamp, string $signature, int $now = 0): bool
    {
        $now = $now !== 0 ? $now : time();

        if (abs($now - $timestamp) > self::TOLERANCE) {
            return false;
        }

        return hash_equals(self::sign($payload, $secret, $timestamp), $signature);
    }

    public static function generateSecret(): string
    {
        return 'whsec_'.bin2hex(random_bytes(24));
    }

    private static function signedPayload(string $payload, int $timestamp): string
    {
        return $timestamp.'.'.$payload;
    }
}
