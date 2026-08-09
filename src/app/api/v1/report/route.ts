import { NextResponse } from "next/server";
import { z } from "zod";
import { db } from "@/lib/db";
import { hashLicenceKey, normaliseDomain } from "@/lib/licence";

// POST /api/v1/report — a bug report or feedback from inside a customer's CMS.
//
// Submitted with the licence key, so the report arrives already knowing which
// customer, domain and version it came from. Those are the three things
// support otherwise spends two round-trips asking for, on a low-priced product
// where two round-trips can cost more than the sale.

export const dynamic = "force-dynamic";

const schema = z.object({
  licenceKey: z.string().min(8),
  domain: z.string().min(1),
  kind: z.enum(["bug", "feedback", "question"]).default("bug"),
  subject: z.string().min(3).max(200),
  body: z.string().min(1).max(10_000),
  cmsVersion: z.string().max(40).optional(),
  phpVersion: z.string().max(40).optional(),
});

export async function POST(request: Request) {
  const parsed = schema.safeParse(await request.json().catch(() => null));
  if (!parsed.success) {
    return NextResponse.json({ ok: false, error: "invalid_request" }, { status: 422 });
  }
  const input = parsed.data;

  const licence = await db.licence.findUnique({
    where: { keyHash: hashLicenceKey(input.licenceKey) },
    select: { id: true, status: true },
  });

  if (!licence) {
    return NextResponse.json({ ok: false, error: "invalid_licence" }, { status: 404 });
  }

  // A suspended licence can still report problems — being unable to tell you
  // something is broken is exactly the wrong outcome when their site is down
  // and they are trying to sort out a payment dispute. Only revoked is refused.
  if (licence.status === "revoked") {
    return NextResponse.json({ ok: false, error: "revoked" }, { status: 403 });
  }

  const report = await db.report.create({
    data: {
      licenceId: licence.id,
      domain: normaliseDomain(input.domain),
      kind: input.kind,
      subject: input.subject,
      body: input.body,
      cmsVersion: input.cmsVersion ?? null,
      phpVersion: input.phpVersion ?? null,
    },
  });

  // The reference is what the customer quotes when chasing it up.
  return NextResponse.json({ ok: true, reference: report.id.slice(-8).toUpperCase() }, { status: 201 });
}
