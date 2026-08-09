import { randomBytes, scrypt as scryptCb, timingSafeEqual } from "node:crypto";
import { promisify } from "node:util";

const scrypt = promisify(scryptCb) as (
  password: string,
  salt: Buffer,
  keylen: number,
) => Promise<Buffer>;

// Password hashing with scrypt.
//
// scrypt rather than a plain hash because password reuse is universal: a
// leaked table of SHA-256 digests is cracked in minutes, and the same password
// probably opens the operator's email. scrypt is deliberately slow and
// memory-hard, so guessing at scale costs real hardware.
//
// Built into Node, so no dependency. bcrypt or argon2 would be equally fine —
// what matters is that it is not a bare digest.

const SALT_BYTES = 16;
const KEY_BYTES = 64;

/** Stored as `scrypt$<salt>$<derived key>`, both base64url. */
export async function hashPassword(password: string): Promise<string> {
  const salt = randomBytes(SALT_BYTES);
  const derived = await scrypt(password, salt, KEY_BYTES);
  return `scrypt$${salt.toString("base64url")}$${derived.toString("base64url")}`;
}

export async function verifyPassword(password: string, stored: string): Promise<boolean> {
  const [scheme, saltB64, keyB64] = stored.split("$");
  if (scheme !== "scrypt" || !saltB64 || !keyB64) return false;

  const salt = Buffer.from(saltB64, "base64url");
  const expected = Buffer.from(keyB64, "base64url");

  let derived: Buffer;
  try {
    derived = await scrypt(password, salt, expected.length);
  } catch {
    return false;
  }

  // Constant-time: a byte-by-byte early exit leaks how much of the hash
  // matched, one request at a time.
  if (derived.length !== expected.length) return false;
  return timingSafeEqual(derived, expected);
}

/**
 * Minimum bar for a console that can revoke customers' licences.
 * Length beats character classes — a long passphrase is both stronger and
 * likelier to be remembered than a short one with a symbol bolted on.
 */
export function passwordProblem(password: string): string | null {
  if (password.length < 10) return "Use at least 10 characters.";
  if (password.length > 200) return "That password is too long.";
  if (/^\d+$/.test(password)) return "Digits alone are too easy to guess.";
  return null;
}
