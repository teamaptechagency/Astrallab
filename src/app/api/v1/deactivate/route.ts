import { NextResponse } from "next/server";
import { z } from "zod";
import { db } from "@/lib/db";
import { hashLicenceKey, normaliseDomain } from "@/lib/licence";

// POST /api/v1/deactivate — release a domain so the licence can move.
//
// This is the customer's self-service route out of a seat limit: change
// hosting, release the old domain, activate the new one. Without it every
// domain change becomes a support ticket.
//
// The activation row is kept and marked released rather than deleted, so the
// transfer history survives — frequent hopping between domains is the only
// abuse signal this system has.

export const dynamic = "force-dynamic";

const deactivateSchema = z.object({
  licenceKey: z.string().min(8),
  domain: z.string().min(1),
});

export async function POST(request: Request) {
  const parsed = deactivateSchema.safeParse(await request.json().catch(() => null));
  if (!parsed.success) {
    return NextResponse.json({ ok: false, error: "invalid_request" }, { status: 422 });
  }

  const domain = normaliseDomain(parsed.data.domain);

  const licence = await db.licence.findUnique({
    where: { keyHash: hashLicenceKey(parsed.data.licenceKey) },
    include: { activations: { where: { releasedAt: null, domain } } },
  });

  if (!licence) {
    return NextResponse.json({ ok: false, error: "invalid_licence" }, { status: 404 });
  }

  // A revoked licence must not be able to free its seat — otherwise a refunded
  // customer could keep recycling the same key onto new domains.
  if (licence.status === "revoked") {
    return NextResponse.json({ ok: false, error: "revoked" }, { status: 403 });
  }

  const activation = licence.activations[0];
  if (!activation) {
    // Already released, or never bound here. Idempotent: the caller's goal
    // ("this domain is not using the licence") is satisfied either way.
    return NextResponse.json({ ok: true, alreadyReleased: true });
  }

  await db.activation.update({
    where: { id: activation.id },
    data: { releasedAt: new Date() },
  });

  const remaining = await db.activation.count({
    where: { licenceId: licence.id, releasedAt: null },
  });

  await db.licence.update({
    where: { id: licence.id },
    data: { status: remaining > 0 ? "active" : "deactivated" },
  });

  await db.licenceEvent.create({
    data: { licenceId: licence.id, kind: "deactivated", domain },
  });

  return NextResponse.json({ ok: true, alreadyReleased: false, seatsInUse: remaining });
}
