import { NextResponse } from "next/server";
import { z } from "zod";
import { db } from "@/lib/db";
import { hashLicenceKey, normaliseDomain, isPlausibleDomain } from "@/lib/licence";
import { signEnvelope, signDownloadToken } from "@/lib/signing";

// POST /api/v1/activate — bind a licence to one production domain.
//
// This is the gate the whole distribution model rests on: the installer holds
// no application code, and only a successful activation yields a download
// token for the core package.
//
// Unauthenticated by design — the licence key IS the credential. Every failure
// answers with the same shape so the endpoint can't be used to enumerate which
// keys exist.

export const dynamic = "force-dynamic";

const activateSchema = z.object({
  licenceKey: z.string().min(8),
  domain: z.string().min(1),
  productSlug: z.string().min(1),
  version: z.string().optional(),
  phpVersion: z.string().optional(),
});

// Statuses that can never activate, with the reason the customer sees. A
// blocked licence must say *why* — "invalid" on a suspended licence sends the
// customer to support with no idea what happened.
const BLOCKED: Record<string, string> = {
  suspended: "This licence is suspended. Contact support to resolve it.",
  revoked: "This licence has been revoked and can no longer be activated.",
};

function clientIp(request: Request): string | null {
  return request.headers.get("x-forwarded-for")?.split(",")[0]?.trim() ?? null;
}

export async function POST(request: Request) {
  const parsed = activateSchema.safeParse(await request.json().catch(() => null));
  if (!parsed.success) {
    return NextResponse.json({ ok: false, error: "invalid_request" }, { status: 422 });
  }
  const input = parsed.data;

  const domain = normaliseDomain(input.domain);
  if (!isPlausibleDomain(domain)) {
    return NextResponse.json({ ok: false, error: "invalid_domain" }, { status: 422 });
  }

  const licence = await db.licence.findUnique({
    where: { keyHash: hashLicenceKey(input.licenceKey) },
    include: {
      product: true,
      activations: { where: { releasedAt: null } },
    },
  });

  if (!licence || licence.product.slug !== input.productSlug) {
    return NextResponse.json({ ok: false, error: "invalid_licence" }, { status: 404 });
  }

  const blocked = BLOCKED[licence.status];
  if (blocked) {
    await db.licenceEvent.create({
      data: { licenceId: licence.id, kind: "blocked", domain, detail: licence.status, ip: clientIp(request) },
    });
    return NextResponse.json({ ok: false, error: licence.status, message: blocked }, { status: 403 });
  }

  const alreadyHere = licence.activations.find((a) => a.domain === domain);
  const seatsUsed = licence.activations.length;

  // Re-activating the domain already bound is not an error — installers get
  // re-run after a failed install, and that must not consume the only seat or
  // strand the customer.
  if (!alreadyHere && seatsUsed >= licence.seatLimit) {
    const other = licence.activations[0];
    await db.licenceEvent.create({
      data: { licenceId: licence.id, kind: "blocked", domain, detail: "seat_limit", ip: clientIp(request) },
    });
    return NextResponse.json(
      {
        ok: false,
        error: "seat_limit_reached",
        // Show only enough of the bound domain for the owner to recognise it.
        // Printing it in full would turn this endpoint into a way of asking
        // "where is this licence installed?" with nothing but a key.
        message: `This licence is already active on another domain (…${other?.domain.slice(-12) ?? ""}). Deactivate it there first, or request a transfer.`,
      },
      { status: 409 },
    );
  }

  const activation = alreadyHere
    ? await db.activation.update({
        where: { id: alreadyHere.id },
        data: {
          lastSeenAt: new Date(),
          lastVersion: input.version ?? alreadyHere.lastVersion,
          phpVersion: input.phpVersion ?? alreadyHere.phpVersion,
        },
      })
    : await db.activation.create({
        data: {
          licenceId: licence.id,
          domain,
          lastVersion: input.version ?? null,
          phpVersion: input.phpVersion ?? null,
        },
      });

  if (licence.status !== "active") {
    await db.licence.update({ where: { id: licence.id }, data: { status: "active" } });
  }

  await db.licenceEvent.create({
    data: {
      licenceId: licence.id,
      kind: "activated",
      domain,
      detail: alreadyHere ? "re-activation" : "first activation",
      ip: clientIp(request),
    },
  });

  // Newest published release — what a fresh install should download.
  const releases = await db.release.findMany({
    where: { productId: licence.productId, published: true, channel: "stable" },
    orderBy: { createdAt: "desc" },
  });
  const latest = releases[0];

  return NextResponse.json({
    ok: true,
    // Signed so the install can cache this and keep running if we're
    // unreachable later, without being able to forge or replay it.
    validation: signEnvelope({
      licenceId: licence.id,
      product: licence.product.slug,
      domain,
      status: "active",
      seatLimit: licence.seatLimit,
      activationId: activation.id,
    }),
    download: latest
      ? {
          version: latest.version,
          checksum: latest.checksum,
          sizeBytes: latest.sizeBytes,
          token: signDownloadToken(latest.id, licence.id),
        }
      : null,
  });
}
