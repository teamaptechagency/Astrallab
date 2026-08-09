// Operator authentication for the console.
//
// One shared password from the environment, not user accounts — this console
// is for the two or three people who run Astra Lab, and inventing a user table
// for that would be ceremony without benefit. If the team grows past that,
// replace this wholesale rather than bolting roles onto it.
//
// The cookie holds no secret: it is an expiry timestamp plus an HMAC over it.
// Nothing an attacker could read from the cookie helps them mint another.
//
// Built on Web Crypto rather than node:crypto because middleware runs on the
// Edge runtime, where node:crypto does not exist. Web Crypto is available in
// both, so one implementation covers the middleware check and the sign-in
// action without a second, subtly different copy.

export const ADMIN_COOKIE = "astralab_admin";

const SESSION_TTL_MS = 12 * 60 * 60_000;

function secret(): string {
  const value = process.env.ADMIN_SESSION_SECRET ?? process.env.STORE_API_SECRET;
  if (!value) throw new Error("ADMIN_SESSION_SECRET is not set.");
  return value;
}

async function hmac(message: string): Promise<string> {
  const key = await crypto.subtle.importKey(
    "raw",
    new TextEncoder().encode(secret()),
    { name: "HMAC", hash: "SHA-256" },
    false,
    ["sign"],
  );
  const sig = await crypto.subtle.sign("HMAC", key, new TextEncoder().encode(message));
  return base64url(new Uint8Array(sig));
}

function base64url(bytes: Uint8Array): string {
  let binary = "";
  for (const b of bytes) binary += String.fromCharCode(b);
  return btoa(binary).replace(/\+/g, "-").replace(/\//g, "_").replace(/=+$/, "");
}

/** Length-independent compare, so timing reveals nothing about the expected value. */
function constantTimeEqual(a: string, b: string): boolean {
  if (a.length !== b.length) return false;
  let diff = 0;
  for (let i = 0; i < a.length; i++) diff |= a.charCodeAt(i) ^ b.charCodeAt(i);
  return diff === 0;
}

export async function issueAdminCookie(): Promise<{ value: string; expires: Date }> {
  const expiresAt = Date.now() + SESSION_TTL_MS;
  const mac = await hmac(String(expiresAt));
  return { value: `${expiresAt}.${mac}`, expires: new Date(expiresAt) };
}

export async function isValidAdminCookie(raw: string | undefined): Promise<boolean> {
  if (!raw) return false;
  const [expiresRaw, mac] = raw.split(".");
  if (!expiresRaw || !mac) return false;

  const expiresAt = Number(expiresRaw);
  if (!Number.isFinite(expiresAt) || Date.now() > expiresAt) return false;

  return constantTimeEqual(await hmac(String(expiresAt)), mac);
}

/**
 * Check a submitted password against ADMIN_PASSWORD.
 *
 * Compared as HMACs rather than raw strings so the comparison is both
 * constant-time and length-independent — comparing the plaintext directly
 * would leak the password's length through timing.
 *
 * An unset password fails closed: an unconfigured console must be unreachable,
 * never open.
 */
export async function checkPassword(submitted: string): Promise<boolean> {
  const expected = process.env.ADMIN_PASSWORD;
  if (!expected) return false;
  const [a, b] = await Promise.all([hmac(`pw:${expected}`), hmac(`pw:${submitted}`)]);
  return constantTimeEqual(a, b);
}
