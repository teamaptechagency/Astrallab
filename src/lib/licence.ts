import { createHmac, randomBytes, timingSafeEqual } from "node:crypto";

// Licence key handling.
//
// Same discipline as a session token: the key is a high-entropy random value,
// shown to the customer exactly once, and stored only as a keyed hash. Support
// can identify a licence by its last four characters without the database ever
// holding something presentable as a working key.

const GROUPS = 4;
const GROUP_LEN = 5;
// Crockford-style alphabet: no I, L, O, U — so a key read aloud down a support
// call can't be mistyped as 1/0, and nothing spells anything unfortunate.
const ALPHABET = "0123456789ABCDEFGHJKMNPQRSTVWXYZ";

function secret(): string {
  const value = process.env.LICENCE_SECRET;
  if (!value) {
    throw new Error(
      "LICENCE_SECRET is not set. Generate one with `openssl rand -base64 32` and add it to .env.",
    );
  }
  return value;
}

/** e.g. "ASTRA-7K2M9-QX4RT-8NBVC-3WHDZ" */
export function generateLicenceKey(): string {
  const bytes = randomBytes(GROUPS * GROUP_LEN);
  const groups: string[] = [];
  for (let g = 0; g < GROUPS; g++) {
    let group = "";
    for (let i = 0; i < GROUP_LEN; i++) {
      // Rejection-free mapping is fine here: 256 % 32 === 0, so taking the
      // byte modulo the alphabet length stays uniform.
      group += ALPHABET[bytes[g * GROUP_LEN + i]! % ALPHABET.length];
    }
    groups.push(group);
  }
  return `ASTRA-${groups.join("-")}`;
}

/** Keyed hash — what actually goes in the database. */
export function hashLicenceKey(key: string): string {
  return createHmac("sha256", secret()).update(normaliseKey(key)).digest("hex");
}

/**
 * Accept what a human actually types: lowercase, missing dashes, stray spaces.
 * Everything is compared and hashed in this canonical form.
 */
export function normaliseKey(raw: string): string {
  return raw.trim().toUpperCase().replace(/[^A-Z0-9]/g, "");
}

export function keyLast4(key: string): string {
  return normaliseKey(key).slice(-4);
}

/**
 * Normalise a site URL to a bare host.
 *
 * This is what stops one licence quietly occupying several seats:
 * "https://WWW.Shop.com/", "http://shop.com" and "shop.com" are one domain.
 * Port is preserved so localhost:3000 and localhost:4000 stay distinct during
 * development, but "www." is always stripped — no real deployment means those
 * to be different sites.
 */
export function normaliseDomain(raw: string): string {
  let value = raw.trim().toLowerCase();
  value = value.replace(/^[a-z][a-z0-9+.-]*:\/\//, "");
  // Drop path, query and fragment — only the host identifies the install.
  value = value.split("/")[0] ?? "";
  value = value.split("?")[0] ?? "";
  value = value.replace(/^www\./, "");
  return value.replace(/\.$/, "");
}

export function isPlausibleDomain(host: string): boolean {
  if (!host || host.length > 253) return false;
  // Hostname, optionally with a port. Deliberately permissive about TLDs so
  // staging hosts and localhost still work.
  return /^[a-z0-9.-]+(:\d{1,5})?$/.test(host) && !host.startsWith("-");
}

/** Constant-time compare for secrets checked outside the database. */
export function safeEqual(a: string, b: string): boolean {
  const bufA = Buffer.from(a);
  const bufB = Buffer.from(b);
  if (bufA.length !== bufB.length) return false;
  return timingSafeEqual(bufA, bufB);
}
