<?php

namespace App\Support\Licence;

use RuntimeException;

/**
 * Signs the replies that installed shops verify.
 *
 * Every copy of the CMS has this hub's Ed25519 PUBLIC key compiled into
 * config/astralab.php, and refuses any reply it cannot verify against it. That
 * is what stops a hostile DNS answer, a hosts-file entry or a proxy from
 * telling an installation it is licensed when it is not.
 *
 * Which makes this class a compatibility surface, not just an implementation.
 * The bytes it signs have to match what the CMS's SignatureVerifier rebuilds,
 * exactly:
 *
 *   - the object is {data, issuedAt, expiresAt}, in that order
 *   - no whitespace
 *   - slashes are not escaped, unicode is not escaped
 *
 * PHP's json_encode escapes both by default and JavaScript's JSON.stringify
 * escapes neither, so the two flags are the whole difference between a
 * signature that verifies and thousands of shops deciding they are unlicensed.
 *
 * Ed25519 is deterministic: the same key over the same bytes gives the same
 * signature every time. That is what makes this testable against the
 * implementation it replaces rather than merely believed — see SignerTest.
 */
class Signer
{
    /**
     * How long a signed validation stays acceptable to an install that cannot
     * reach us. Long enough to ride out our outage without taking customer
     * storefronts down; short enough that a revoked licence stops working
     * within days. The CMS reads the expiry out of the signed bytes, so this
     * cannot be extended by moving a clock.
     */
    public const TTL_DAYS = 7;

    /**
     * Wrap a payload with timestamps and sign it.
     *
     * The timestamps are inside the signed bytes, so an old "your licence is
     * valid" cannot be replayed at an install after the licence was revoked.
     *
     * @param  array<string, mixed>  $data
     * @return array{data: array<string, mixed>, issuedAt: string, expiresAt: string, signature: string}
     */
    public function envelope(array $data, ?int $ttlDays = null): array
    {
        $issuedAt = now()->utc();
        $expiresAt = $issuedAt->copy()->addDays($ttlDays ?? self::TTL_DAYS);

        $envelope = [
            'data' => $data,
            // JavaScript's toISOString, which is what the installs have always
            // been sent: UTC, milliseconds, trailing Z.
            'issuedAt' => $issuedAt->format('Y-m-d\TH:i:s.v\Z'),
            'expiresAt' => $expiresAt->format('Y-m-d\TH:i:s.v\Z'),
        ];

        return $envelope + ['signature' => $this->sign($this->canonical($envelope))];
    }

    /**
     * The exact bytes both sides agree on.
     *
     * Public so a test can assert on them directly. A signature test that only
     * checks "it verifies" would pass against a canonical form both sides got
     * wrong in the same way.
     *
     * @param  array{data: mixed, issuedAt: string, expiresAt: string}  $envelope
     */
    public function canonical(array $envelope): string
    {
        $json = json_encode([
            'data' => $envelope['data'],
            'issuedAt' => $envelope['issuedAt'],
            'expiresAt' => $envelope['expiresAt'],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($json === false) {
            throw new RuntimeException('The envelope could not be encoded: '.json_last_error_msg());
        }

        return $json;
    }

    /** Detached Ed25519, base64url — the form the CMS decodes. */
    public function sign(string $message): string
    {
        $signature = sodium_crypto_sign_detached($message, $this->secretKey());

        return rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');
    }

    /**
     * The 64-byte sodium secret key, out of the PKCS#8 PEM.
     *
     * An Ed25519 PKCS#8 body is 48 bytes: a fixed 16-byte header followed by
     * the 32-byte seed. sodium wants a keypair derived from that seed rather
     * than the seed itself, which is the step that catches people out — the
     * seed is not the secret key, it is what the secret key is grown from.
     */
    private function secretKey(): string
    {
        $pem = (string) config('astralab.signing_key');

        if ($pem === '') {
            throw new RuntimeException(
                'No signing key. Generate one with `php artisan astralab:keys` and put it in .env — '
                .'without it, no installation can be told anything it will believe.'
            );
        }

        // Stored in .env as one line with literal \n, which is the only way a
        // multi-line PEM survives a settings file.
        $pem = str_replace('\\n', "\n", $pem);

        $body = preg_replace('/-----(BEGIN|END) PRIVATE KEY-----|\s+/', '', $pem);
        $der = is_string($body) ? base64_decode($body, true) : false;

        if ($der === false || strlen($der) !== 48) {
            throw new RuntimeException('The signing key is not a PKCS#8 Ed25519 private key.');
        }

        $seed = substr($der, -SODIUM_CRYPTO_SIGN_SEEDBYTES);

        return sodium_crypto_sign_secretkey(sodium_crypto_sign_seed_keypair($seed));
    }

    /** The matching public key, in the PEM form baked into every CMS. */
    public function publicKeyPem(): string
    {
        $raw = sodium_crypto_sign_publickey_from_secretkey($this->secretKey());

        // SPKI: the fixed 12-byte Ed25519 algorithm header, then the key.
        $der = hex2bin('302a300506032b6570032100').$raw;

        return "-----BEGIN PUBLIC KEY-----\n"
            .chunk_split(base64_encode($der), 64, "\n")
            ."-----END PUBLIC KEY-----\n";
    }
}
