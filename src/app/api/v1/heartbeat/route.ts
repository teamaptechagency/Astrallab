import { NextResponse } from "next/server";
import { z } from "zod";
import { db } from "@/lib/db";
import { hashLicenceKey, normaliseDomain } from "@/lib/licence";
import { signEnvelope, signDownloadToken } from "@/lib/signing";
import { upgradePath, compareVersions } from "@/lib/version";

// POST /api/v1/heartbeat — the install checks in.
//
// This is the only channel through which an update ever reaches a customer.
// We cannot push: their sites sit on shared hosting with no fixed address and
// no inbound access. So each install calls us on a schedule and we answer with
// whatever it needs to know.
//
// The response carries the ordered upgrade path rather than just "latest", so
// an install a year behind applies migrations in sequence instead of leaping
// several majors and half-migrating its database.

export const dynamic = "force-dynamic";

const heartbeatSchema = z.object({
  licenceKey: z.string().min(8),
  domain: z.string().min(1),
  productSlug: z.string().min(1),
  version: z.string().min(1),
  phpVersion: z.string().optional(),
});

// How soon the install should check again. A security release pulls every
// site in faster; without this a critical patch propagates only as fast as the
// slowest routine poll.
const CHECK_INTERVAL_NORMAL_S = 24 * 60 * 60;
const CHECK_INTERVAL_SECURITY_S = 60 * 60;

export async function POST(request: Request) {
  const parsed = heartbeatSchema.safeParse(await request.json().catch(() => null));
  if (!parsed.success) {
    return NextResponse.json({ ok: false, error: "invalid_request" }, { status: 422 });
  }
  const input = parsed.data;
  const domain = normaliseDomain(input.domain);

  const licence = await db.licence.findUnique({
    where: { keyHash: hashLicenceKey(input.licenceKey) },
    include: { product: true, activations: { where: { releasedAt: null } } },
  });

  if (!licence || licence.product.slug !== input.productSlug) {
    return NextResponse.json({ ok: false, error: "invalid_licence" }, { status: 404 });
  }

  const activation = licence.activations.find((a) => a.domain === domain);
  if (!activation) {
    // Running on a domain this licence no longer holds — typically a site
    // cloned to staging, or an old domain left online after a transfer. Not an
    // error to shout about; the install shows a notice and keeps serving.
    return NextResponse.json(
      { ok: false, error: "domain_not_active", message: "This domain is not activated for this licence." },
      { status: 409 },
    );
  }

  if (licence.status === "suspended" || licence.status === "revoked") {
    return NextResponse.json(
      { ok: false, error: licence.status, message: licence.statusNote ?? undefined },
      { status: 403 },
    );
  }

  await db.activation.update({
    where: { id: activation.id },
    data: {
      lastSeenAt: new Date(),
      lastVersion: input.version,
      phpVersion: input.phpVersion ?? activation.phpVersion,
    },
  });

  const releases = await db.release.findMany({
    where: { productId: licence.productId, published: true, channel: "stable" },
    orderBy: { createdAt: "asc" },
  });

  const path = upgradePath(input.version, releases);
  const anySecurity = path.some((r) => r.severity === "security");
  const newest = releases.length
    ? releases.reduce((a, b) => (compareVersions(a.version, b.version) >= 0 ? a : b))
    : null;

  return NextResponse.json({
    ok: true,
    validation: signEnvelope({
      licenceId: licence.id,
      product: licence.product.slug,
      domain,
      status: licence.status,
    }),
    currentVersion: input.version,
    latestVersion: newest?.version ?? input.version,
    updateAvailable: path.length > 0,
    // Apply in this order, one at a time.
    upgradePath: path.map((r) => ({
      version: r.version,
      severity: r.severity,
      notes: r.notes,
      checksum: r.checksum,
      sizeBytes: r.sizeBytes,
      token: signDownloadToken(r.id, licence.id),
    })),
    nextCheckInSeconds: anySecurity ? CHECK_INTERVAL_SECURITY_S : CHECK_INTERVAL_NORMAL_S,
  });
}
