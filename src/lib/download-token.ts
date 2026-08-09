import { createHmac } from "node:crypto";

// Verification half of signDownloadToken (src/lib/signing.ts).
//
// A token names the release, the licence it was minted for, and an expiry —
// all covered by an HMAC. So a leaked link stops working within the hour and,
// because the licence is embedded, we can see whose link it was.

export interface DownloadClaim {
  releaseId: string;
  licenceId: string;
  expiresAt: number;
}

export type TokenResult =
  | { ok: true; claim: DownloadClaim }
  | { ok: false; reason: "malformed" | "bad_signature" | "expired" };

export function verifyDownloadToken(token: string): TokenResult {
  const secret = process.env.PACKAGE_URL_SECRET ?? process.env.STORE_API_SECRET;
  if (!secret) throw new Error("PACKAGE_URL_SECRET is not set.");

  const parts = token.split(".");
  if (parts.length !== 2) return { ok: false, reason: "malformed" };

  const [payloadB64, mac] = parts as [string, string];

  let payload: string;
  try {
    payload = Buffer.from(payloadB64, "base64url").toString("utf8");
  } catch {
    return { ok: false, reason: "malformed" };
  }

  // Recompute before parsing. Never trust the contents of a token whose
  // signature hasn't been checked.
  const expected = createHmac("sha256", secret).update(payload).digest("base64url");
  if (!constantTimeEqual(expected, mac)) return { ok: false, reason: "bad_signature" };

  const [releaseId, licenceId, expiresRaw] = payload.split(".");
  const expiresAt = Number(expiresRaw);
  if (!releaseId || !licenceId || !Number.isFinite(expiresAt)) {
    return { ok: false, reason: "malformed" };
  }

  if (Date.now() > expiresAt) return { ok: false, reason: "expired" };

  return { ok: true, claim: { releaseId, licenceId, expiresAt } };
}

function constantTimeEqual(a: string, b: string): boolean {
  if (a.length !== b.length) return false;
  let diff = 0;
  for (let i = 0; i < a.length; i++) diff |= a.charCodeAt(i) ^ b.charCodeAt(i);
  return diff === 0;
}

/**
 * Parse a single-range `Range: bytes=start-end` header.
 *
 * Range support is not a nicety here. Customer sites run on shared hosting
 * with 30-second execution limits and modest memory — a 40 MB package cannot
 * be fetched in one PHP request. The installer pulls it in pieces and resumes,
 * which only works if this endpoint honours ranges.
 */
export function parseRange(
  header: string | null,
  size: number,
): { start: number; end: number } | null {
  if (!header) return null;
  const match = /^bytes=(\d*)-(\d*)$/.exec(header.trim());
  if (!match) return null;

  const [, startRaw, endRaw] = match;
  if (!startRaw && !endRaw) return null;

  // "bytes=-500" means the final 500 bytes, not "from 0 to 500".
  if (!startRaw) {
    const length = Number(endRaw);
    if (!length) return null;
    return { start: Math.max(0, size - length), end: size - 1 };
  }

  const start = Number(startRaw);
  const end = endRaw ? Math.min(Number(endRaw), size - 1) : size - 1;
  if (start > end || start >= size) return null;
  return { start, end };
}
