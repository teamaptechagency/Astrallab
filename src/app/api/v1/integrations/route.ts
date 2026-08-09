import { NextResponse } from "next/server";
import { z } from "zod";
import { db } from "@/lib/db";
import { hashLicenceKey, normaliseDomain } from "@/lib/licence";
import { getAyojonState } from "@/lib/settings";
import { signEnvelope } from "@/lib/signing";

// POST /api/v1/integrations — what should this install's Integrations page show?
//
// The connection space for Ayojon. Nothing connects yet, and deliberately so:
// Ayojon has no API to connect to. What exists now is the *channel* — every
// install already asks this question on a schedule, so the day Ayojon is ready
// it becomes available everywhere by flipping one setting in the hub, with no
// update rolled out to any customer site.
//
// Building this later would mean shipping a CMS update to every install in the
// field just to teach it that Ayojon exists — and installs that never update
// would never learn.

export const dynamic = "force-dynamic";

const schema = z.object({
  licenceKey: z.string().min(8),
  domain: z.string().min(1),
});

export async function POST(request: Request) {
  const parsed = schema.safeParse(await request.json().catch(() => null));
  if (!parsed.success) {
    return NextResponse.json({ ok: false, error: "invalid_request" }, { status: 422 });
  }

  const domain = normaliseDomain(parsed.data.domain);

  const licence = await db.licence.findUnique({
    where: { keyHash: hashLicenceKey(parsed.data.licenceKey) },
    include: { activations: { where: { releasedAt: null } } },
  });

  if (!licence) {
    return NextResponse.json({ ok: false, error: "invalid_licence" }, { status: 404 });
  }
  if (licence.status === "revoked" || licence.status === "suspended") {
    return NextResponse.json({ ok: false, error: licence.status }, { status: 403 });
  }
  if (!licence.activations.some((a) => a.domain === domain)) {
    return NextResponse.json({ ok: false, error: "domain_not_active" }, { status: 409 });
  }

  const ayojon = await getAyojonState();

  // Whether this specific install has already linked. Always false today —
  // the field exists so the CMS can be written against its final shape now,
  // rather than needing a change when linking goes live.
  const link = await db.ayojonLink.findFirst({
    where: { licenceId: licence.id, revokedAt: null },
    select: { ayojonUserId: true, scopes: true, connectedAt: true },
  });

  return NextResponse.json({
    ok: true,
    integrations: {
      ayojon: {
        status: ayojon.status,
        message: ayojon.message,
        connectUrl: ayojon.connectUrl,
        connected: Boolean(link),
        // Scopes the user granted, once this means anything.
        scopes: link ? link.scopes.split(",").filter(Boolean) : [],
        connectedAt: link?.connectedAt ?? null,
      },
    },
    // Signed so an install can cache the answer and keep rendering its
    // Integrations page while the hub is unreachable.
    validation: signEnvelope({ licenceId: licence.id, domain }),
  });
}
