import { createPrivateKey, sign as cryptoSign } from "node:crypto";
import { readFileSync } from "node:fs";

//   node scripts/signing-reference.mjs .env
//
// Produces the reference signature the Laravel signer is tested against. Kept
// rather than deleted: it is how the expected values in site/tests/Unit/
// SignerTest.php were produced, and the only way to regenerate them honestly
// if the canonical form ever has to change.
// Reproduces src/lib/signing.ts exactly, on fixed input so the answer is
// comparable rather than time-dependent.
//
// The payload is chosen to catch the two ways the canonical form goes wrong:
// a forward slash (PHP escapes it by default, JavaScript does not) and
// non-ASCII (PHP escapes to \uXXXX by default, JavaScript emits UTF-8).

const env = readFileSync(process.argv[2], "utf8");
const pem = env.match(/^SIGNING_PRIVATE_KEY="?(.+?)"?$/m)[1].replace(/\\n/g, "\n");

const data = {
  licence: "ASTRA-7K2M9-QX4RT",
  domain: "shop.example.com/path",
  note: "বাংলা",
};
const issuedAt = "2026-08-12T10:00:00.000Z";
const expiresAt = "2026-08-19T10:00:00.000Z";

const canonical = JSON.stringify({ data, issuedAt, expiresAt });
const signature = cryptoSign(null, Buffer.from(canonical, "utf8"), createPrivateKey(pem)).toString(
  "base64url",
);

console.log(JSON.stringify({ canonical, signature }, null, 1));
