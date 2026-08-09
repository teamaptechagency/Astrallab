import { NextResponse } from "next/server";
import { z } from "zod";
import { db } from "@/lib/db";
import { generateLicenceKey, hashLicenceKey, keyLast4 } from "@/lib/licence";
import { verifyStoreSignature } from "@/lib/signing";

// POST /api/v1/licences — issue a licence for a paid order.
//
// Called server-to-server by the WooCommerce store, authenticated with an
// HMAC over the raw body (X-Astralab-Signature).
//
// Idempotent on (orderSource, orderRef). WooCommerce retries webhooks and can
// deliver the same event several times; without that guarantee a customer ends
// up with three licences for one purchase and support has to work out which is
// real. A repeat call returns the SAME licence — but not the key, because the
// key exists in plaintext exactly once, in the first response.

export const dynamic = "force-dynamic";

const issueSchema = z.object({
  productSlug: z.string().min(1),
  orderRef: z.string().min(1),
  orderSource: z.string().default("woocommerce"),
  customerEmail: z.string().email(),
  customerName: z.string().optional(),
  seatLimit: z.number().int().min(1).max(100).optional(),
});

export async function POST(request: Request) {
  const raw = await request.text();

  if (!verifyStoreSignature(raw, request.headers.get("x-astralab-signature"))) {
    return NextResponse.json({ error: "invalid_signature" }, { status: 401 });
  }

  let body: unknown;
  try {
    body = JSON.parse(raw);
  } catch {
    return NextResponse.json({ error: "invalid_json" }, { status: 400 });
  }

  const parsed = issueSchema.safeParse(body);
  if (!parsed.success) {
    return NextResponse.json(
      { error: "invalid_request", issues: parsed.error.flatten().fieldErrors },
      { status: 422 },
    );
  }
  const input = parsed.data;

  const product = await db.product.findUnique({ where: { slug: input.productSlug } });
  if (!product) {
    return NextResponse.json({ error: "unknown_product" }, { status: 404 });
  }

  const existing = await db.licence.findUnique({
    where: {
      orderSource_orderRef: { orderSource: input.orderSource, orderRef: input.orderRef },
    },
  });
  if (existing) {
    // Duplicate delivery. Acknowledge with 200 so the store stops retrying,
    // but never re-issue and never return a key we no longer hold.
    return NextResponse.json({
      duplicate: true,
      licenceId: existing.id,
      keyLast4: existing.keyLast4,
      status: existing.status,
    });
  }

  const key = generateLicenceKey();

  const licence = await db.licence.create({
    data: {
      keyHash: hashLicenceKey(key),
      keyLast4: keyLast4(key),
      productId: product.id,
      orderRef: input.orderRef,
      orderSource: input.orderSource,
      customerEmail: input.customerEmail,
      customerName: input.customerName ?? null,
      ...(input.seatLimit ? { seatLimit: input.seatLimit } : {}),
      events: {
        create: { kind: "issued", detail: `order ${input.orderRef}` },
      },
    },
  });

  // The only moment the plaintext key is ever available. The store must show
  // and email it now — we cannot reproduce it later.
  return NextResponse.json(
    {
      duplicate: false,
      licenceId: licence.id,
      licenceKey: key,
      keyLast4: licence.keyLast4,
      status: licence.status,
      product: product.slug,
    },
    { status: 201 },
  );
}
