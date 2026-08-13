<?php

namespace Tests\Unit;

use App\Support\Licence\Signer;
use Tests\TestCase;

/**
 * The signer against the implementation it replaces.
 *
 * Every copy of the CMS already carries this hub's public key and refuses any
 * reply it cannot verify. So this is not "does the signature work" — it is
 * "does it produce the same bytes the previous hub did", which is a different
 * and much stricter question. A signer that verified against itself but
 * differed from the old one would look perfect here and silently tell every
 * installed shop it was unlicensed.
 *
 * Ed25519 is deterministic, so the same key over the same bytes gives the same
 * signature. The expected values below were produced by the Node
 * implementation in src/lib/signing.ts, from the fixed payload here, and are
 * pasted in rather than recomputed — a test that regenerates its own expected
 * value proves nothing.
 *
 * The payload is chosen to catch the two ways the canonical form goes wrong:
 * a forward slash, which PHP escapes by default and JavaScript does not, and
 * Bengali text, which PHP escapes to \uXXXX by default and JavaScript emits as
 * UTF-8. Both are things this system actually carries — domains have paths and
 * shop names are in Bangla.
 */
class SignerTest extends TestCase
{
    private const KEY = 'SIGNING_PRIVATE_KEY';

    /** The envelope the Node implementation signed. */
    private const ENVELOPE = [
        'data' => [
            'licence' => 'ASTRA-7K2M9-QX4RT',
            'domain' => 'shop.example.com/path',
            'note' => 'বাংলা',
        ],
        'issuedAt' => '2026-08-12T10:00:00.000Z',
        'expiresAt' => '2026-08-19T10:00:00.000Z',
    ];

    private const EXPECTED_CANONICAL = '{"data":{"licence":"ASTRA-7K2M9-QX4RT","domain":"shop.example.com/path","note":"বাংলা"},"issuedAt":"2026-08-12T10:00:00.000Z","expiresAt":"2026-08-19T10:00:00.000Z"}';

    private const EXPECTED_SIGNATURE = 'eZUuXtXbSdTIr_wZSWwP1ZKgkVHECtnw1XvpDsmnPLiXpiYMOOkfTTXkrHlClja0rFNuHRF9A0xJVraVrGpxDA';

    protected function setUp(): void
    {
        parent::setUp();

        if (! env(self::KEY)) {
            $this->markTestSkipped(self::KEY.' is not set, so there is nothing to sign with.');
        }

        config(['astralab.signing_key' => env(self::KEY)]);
    }

    public function test_the_canonical_bytes_match_javascript_exactly(): void
    {
        // Asserted separately from the signature because they fail differently.
        // A wrong canonical form is a mistake in this file; a wrong signature
        // over a right canonical form is a mistake in the key handling. Rolling
        // them into one assertion would mean reading a base64 diff to work out
        // which.
        $this->assertSame(
            self::EXPECTED_CANONICAL,
            (new Signer)->canonical(self::ENVELOPE),
        );
    }

    public function test_the_signature_matches_the_implementation_it_replaces(): void
    {
        $signer = new Signer;

        $this->assertSame(
            self::EXPECTED_SIGNATURE,
            $signer->sign($signer->canonical(self::ENVELOPE)),
        );
    }

    public function test_slashes_and_bengali_survive_unescaped(): void
    {
        // The two flags that are the whole difference between a signature that
        // verifies and thousands of shops deciding they are unlicensed. Named
        // on their own so that if PHP's defaults ever change, the failure says
        // what happened.
        $canonical = (new Signer)->canonical(self::ENVELOPE);

        $this->assertStringContainsString('shop.example.com/path', $canonical);
        $this->assertStringNotContainsString('shop.example.com\\/path', $canonical);
        $this->assertStringContainsString('বাংলা', $canonical);
        $this->assertStringNotContainsString('\\u0993', $canonical);
    }

    public function test_the_public_key_is_the_one_baked_into_the_cms(): void
    {
        // If this ever fails, the hub has a different keypair from the one
        // every installed shop verifies against, and no reply it sends will be
        // believed by anybody.
        $cms = 'D:/AP Tech Server/Astralab-CMS/config/astralab.php';

        if (! is_file($cms)) {
            $this->markTestSkipped('The CMS is not checked out beside this repository.');
        }

        $config = file_get_contents($cms);
        preg_match('/-----BEGIN PUBLIC KEY-----.*?-----END PUBLIC KEY-----/s', $config, $match);

        $baked = preg_replace('/\s+/', '', $match[0] ?? '');
        $ours = preg_replace('/\s+/', '', (new Signer)->publicKeyPem());

        $this->assertSame($baked, $ours, 'The hub is signing with a key the CMS will not accept.');
    }

    public function test_an_envelope_carries_its_own_expiry_inside_the_signature(): void
    {
        $envelope = (new Signer)->envelope(['licence' => 'ASTRA-TEST']);

        $this->assertArrayHasKey('signature', $envelope);
        $this->assertSame('ASTRA-TEST', $envelope['data']['licence']);

        // Milliseconds and a trailing Z, which is what JavaScript's
        // toISOString produces and what installs have always been sent.
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{3}Z$/',
            $envelope['expiresAt'],
        );

        // Changing anything after signing must break it — that is the point.
        $tampered = $envelope;
        $tampered['data']['licence'] = 'ASTRA-SOMEONE-ELSE';

        $signer = new Signer;

        $this->assertNotSame(
            $envelope['signature'],
            $signer->sign($signer->canonical($tampered)),
        );
    }
}
