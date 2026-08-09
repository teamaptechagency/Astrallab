import { createHmac, createPrivateKey, generateKeyPairSync, sign as cryptoSign } from "node:crypto";

// Two different signing jobs, with two different key types — the difference
// matters and is easy to get wrong.
//
// 1. Responses to CMS installs are signed with Ed25519. Every install verifies
//    with a PUBLIC key baked into the CMS, so a customer can confirm a reply
//    really came from us but cannot forge one. An HMAC would mean shipping the
//    signing secret to every customer, which is the same as having no
//    signature at all.
//
// 2. Calls from our own WooCommerce store are authenticated with a plain
//    shared-secret HMAC. Both ends are servers we control, so symmetric is
//    fine and much simpler.

// How long a signed validation stays acceptable to an install that can't reach
// us. Long enough to ride out an outage or a DNS problem without taking
// customer storefronts down; short enough that a revoked licence stops working
// within a few days.
const VALIDATION_TTL_MS = 7 * 24 * 60 * 60_000;

function privateKey() {
  const pem = process.env.SIGNING_PRIVATE_KEY;
  if (!pem) {
    throw new Error(
      "SIGNING_PRIVATE_KEY is not set. Run `npm run keys:generate` and add the output to .env.",
    );
  }
  return createPrivateKey(pem.replace(/\\n/g, "\n"));
}

export interface SignedEnvelope<T> {
  data: T;
  issuedAt: string;
  expiresAt: string;
  signature: string;
}

/**
 * Wrap a payload with issue/expiry timestamps and an Ed25519 signature.
 *
 * The timestamps are inside the signed bytes, so an install can't be replayed
 * an old "your licence is valid" response after it was revoked.
 */
export function signEnvelope<T>(data: T, ttlMs = VALIDATION_TTL_MS): SignedEnvelope<T> {
  const issuedAt = new Date().toISOString();
  const expiresAt = new Date(Date.now() + ttlMs).toISOString();
  const canonical = JSON.stringify({ data, issuedAt, expiresAt });
  const signature = cryptoSign(null, Buffer.from(canonical, "utf8"), privateKey()).toString(
    "base64url",
  );
  return { data, issuedAt, expiresAt, signature };
}

/** Generate an Ed25519 keypair. Used by scripts/generate-keys.ts. */
export function generateSigningKeypair(): { privateKey: string; publicKey: string } {
  const { privateKey: priv, publicKey: pub } = generateKeyPairSync("ed25519");
  return {
    privateKey: priv.export({ type: "pkcs8", format: "pem" }).toString(),
    publicKey: pub.export({ type: "spki", format: "pem" }).toString(),
  };
}

/**
 * Verify an HMAC from the WooCommerce store.
 *
 * The signature covers the raw body, so the check has to run on the exact
 * bytes received — parsing to JSON and re-stringifying reorders keys and
 * breaks it.
 */
export function verifyStoreSignature(rawBody: string, provided: string | null): boolean {
  const secret = process.env.STORE_API_SECRET;
  if (!secret || !provided) return false;
  const expected = createHmac("sha256", secret).update(rawBody).digest("hex");
  const a = Buffer.from(expected);
  const b = Buffer.from(provided);
  if (a.length !== b.length) return false;
  let diff = 0;
  for (let i = 0; i < a.length; i++) diff |= a[i]! ^ b[i]!;
  return diff === 0;
}

/**
 * A time-limited download link for a release package.
 *
 * The real package location is never handed to a client. A link carries the
 * release id, the licence it was issued for, and an expiry, all covered by an
 * HMAC — so a link that leaks is useless within the hour and traceable to the
 * licence it was minted for.
 */
export function signDownloadToken(releaseId: string, licenceId: string, ttlMs = 60 * 60_000): string {
  const secret = process.env.PACKAGE_URL_SECRET ?? process.env.STORE_API_SECRET;
  if (!secret) throw new Error("PACKAGE_URL_SECRET is not set.");
  const expires = Date.now() + ttlMs;
  const payload = `${releaseId}.${licenceId}.${expires}`;
  const mac = createHmac("sha256", secret).update(payload).digest("base64url");
  return `${Buffer.from(payload).toString("base64url")}.${mac}`;
}
