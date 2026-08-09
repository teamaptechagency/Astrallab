import { NextResponse } from "next/server";
import { z } from "zod";
import { db } from "@/lib/db";
import { hashLicenceKey, normaliseDomain } from "@/lib/licence";

// POST /api/v1/sync — an install pushes its catalogue (and optionally leads).
//
// Push, never pull: customer sites sit on shared hosting with no fixed address
// and no inbound access, so the hub could not fetch from them even if it
// wanted to.
//
// Products and leads are treated very differently on purpose. A product
// catalogue belongs to the shop owner, who accepted your terms at purchase,
// and is usually already public on their storefront. A lead is the personal
// data of *their* customer — someone with no relationship to Astra Lab at all.
// So leads require the install to assert explicit consent, and are dropped
// without it rather than quietly stored.

export const dynamic = "force-dynamic";

const productSchema = z.object({
  externalId: z.string().min(1).max(120),
  name: z.string().min(1).max(300),
  price: z.number().nonnegative().optional(),
  currency: z.string().max(8).optional(),
  stock: z.number().int().optional(),
  imageUrl: z.string().url().max(1000).optional(),
  url: z.string().url().max(1000).optional(),
  category: z.string().max(120).optional(),
});

const leadSchema = z.object({
  externalId: z.string().min(1).max(120),
  name: z.string().max(200).optional(),
  phone: z.string().max(40).optional(),
  email: z.string().max(200).optional(),
  source: z.enum(["order", "enquiry", "newsletter", "abandoned_cart"]).default("enquiry"),
  note: z.string().max(2000).optional(),
  capturedAt: z.string().datetime(),
});

const schema = z.object({
  licenceKey: z.string().min(8),
  domain: z.string().min(1),
  // Batches are capped so one install cannot push a million rows in a request
  // and exhaust the hub's memory.
  products: z.array(productSchema).max(500).optional(),
  leads: z.array(leadSchema).max(500).optional(),
  // The install must state that the shop owner turned lead sharing on. Absent
  // or false means leads are discarded, whatever the payload contains.
  leadConsent: z.boolean().default(false),
});

export async function POST(request: Request) {
  const parsed = schema.safeParse(await request.json().catch(() => null));
  if (!parsed.success) {
    return NextResponse.json({ ok: false, error: "invalid_request" }, { status: 422 });
  }
  const input = parsed.data;
  const domain = normaliseDomain(input.domain);

  const licence = await db.licence.findUnique({
    where: { keyHash: hashLicenceKey(input.licenceKey) },
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

  let productsWritten = 0;
  for (const p of input.products ?? []) {
    await db.syncedProduct.upsert({
      where: { licenceId_externalId: { licenceId: licence.id, externalId: p.externalId } },
      create: {
        licenceId: licence.id,
        domain,
        externalId: p.externalId,
        name: p.name,
        price: p.price ?? null,
        currency: p.currency ?? "BDT",
        stock: p.stock ?? null,
        // Images are referenced, not copied — the customer's own server
        // already serves them, and copying thousands of catalogues would cost
        // real storage for no benefit.
        imageUrl: p.imageUrl ?? null,
        url: p.url ?? null,
        category: p.category ?? null,
      },
      update: {
        domain,
        name: p.name,
        price: p.price ?? null,
        stock: p.stock ?? null,
        imageUrl: p.imageUrl ?? null,
        url: p.url ?? null,
        category: p.category ?? null,
        syncedAt: new Date(),
      },
    });
    productsWritten++;
  }

  let leadsWritten = 0;
  const leadsRejected = !input.leadConsent && (input.leads?.length ?? 0) > 0;

  if (input.leadConsent) {
    for (const l of input.leads ?? []) {
      await db.lead.upsert({
        where: { licenceId_externalId: { licenceId: licence.id, externalId: l.externalId } },
        create: {
          licenceId: licence.id,
          domain,
          externalId: l.externalId,
          name: l.name ?? null,
          phone: l.phone ?? null,
          email: l.email ?? null,
          source: l.source,
          note: l.note ?? null,
          capturedAt: new Date(l.capturedAt),
        },
        update: { syncedAt: new Date() },
      });
      leadsWritten++;
    }
  }

  return NextResponse.json({
    ok: true,
    productsWritten,
    leadsWritten,
    // Told plainly rather than silently dropped, so a misconfigured install
    // surfaces the problem instead of appearing to work.
    leadsRejected: leadsRejected ? "lead sharing is not enabled for this install" : null,
  });
}
