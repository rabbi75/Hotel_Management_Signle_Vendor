<?php

declare(strict_types=1);

namespace App\Modules\Billing\Support;

use App\Modules\Billing\Exceptions\BillingException;

/**
 * The RSA envelope every Nagad call travels in.
 *
 * Nagad is the only processor in the kit that does not authenticate with a
 * bearer token or an HMAC. Each request body is encrypted with Nagad's public
 * key and signed with the merchant's private key; each response comes back
 * encrypted with the merchant's public key and signed with Nagad's, and both
 * have to be undone before there is anything to read.
 *
 * Kept out of the driver because it is the one genuinely novel piece here and
 * the one worth testing on its own — a mistake in it fails as an opaque
 * "invalid signature" from Nagad with nothing to debug against.
 *
 * Keys arrive from the console as bare base64 bodies (which is how Nagad issues
 * them) or as full PEM blocks. Both are accepted; {@see self::pem()} normalises.
 */
final readonly class NagadCrypto
{
    public function __construct(
        private string $nagadPublicKey,
        private string $merchantPrivateKey,
    ) {}

    /**
     * Encrypt a payload for Nagad, base64-encoded as their API expects.
     *
     * @param  array<string, mixed>  $payload
     */
    public function encrypt(array $payload): string
    {
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            throw new BillingException('Could not encode the Nagad payload.');
        }

        $key = openssl_pkey_get_public($this->pem($this->nagadPublicKey, 'PUBLIC KEY'));

        if ($key === false) {
            throw new BillingException('The Nagad public key is not a usable RSA key.');
        }

        // RSA/PKCS1 caps a message at the key size minus eleven bytes. Nagad's
        // payloads sit well inside that, so a failure here is a malformed key
        // rather than an oversized body — say so instead of silently truncating.
        if (! openssl_public_encrypt($json, $encrypted, $key)) {
            throw new BillingException('Could not encrypt the Nagad payload.');
        }

        return base64_encode($encrypted);
    }

    /**
     * Decrypt a base64 payload Nagad encrypted for us.
     *
     * @return array<string, mixed>|null Null when the blob is not ours to read.
     */
    public function decrypt(string $encrypted): ?array
    {
        $raw = base64_decode($encrypted, true);

        if ($raw === false) {
            return null;
        }

        $key = openssl_pkey_get_private($this->pem($this->merchantPrivateKey, 'PRIVATE KEY'));

        if ($key === false) {
            return null;
        }

        if (! openssl_private_decrypt($raw, $decrypted, $key)) {
            return null;
        }

        $decoded = json_decode($decrypted, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Sign a payload with the merchant private key (SHA-256), base64-encoded.
     *
     * @param  array<string, mixed>  $payload
     */
    public function sign(array $payload): string
    {
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            throw new BillingException('Could not encode the Nagad payload for signing.');
        }

        $key = openssl_pkey_get_private($this->pem($this->merchantPrivateKey, 'PRIVATE KEY'));

        if ($key === false) {
            throw new BillingException('The Nagad merchant private key is not a usable RSA key.');
        }

        if (! openssl_sign($json, $signature, $key, OPENSSL_ALGO_SHA256)) {
            throw new BillingException('Could not sign the Nagad payload.');
        }

        return base64_encode($signature);
    }

    /**
     * Verify a signature Nagad produced over a payload.
     *
     * @param  array<string, mixed>  $payload
     */
    public function verify(array $payload, string $signature): bool
    {
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $raw = base64_decode($signature, true);

        if ($json === false || $raw === false) {
            return false;
        }

        $key = openssl_pkey_get_public($this->pem($this->nagadPublicKey, 'PUBLIC KEY'));

        if ($key === false) {
            return false;
        }

        return openssl_verify($json, $raw, $key, OPENSSL_ALGO_SHA256) === 1;
    }

    /**
     * A key in PEM form, whether it arrived that way or as a bare base64 body.
     *
     * Nagad hands merchants the base64 alone, and an operator pasting exactly
     * what they were given should not have to know that OpenSSL needs the
     * armour and the 64-column wrapping around it.
     */
    private function pem(string $key, string $label): string
    {
        $key = trim($key);

        if (str_contains($key, '-----BEGIN')) {
            return $key;
        }

        $body = preg_replace('/\s+/', '', $key) ?? '';

        return "-----BEGIN {$label}-----\n"
            .chunk_split($body, 64, "\n")
            ."-----END {$label}-----\n";
    }
}
