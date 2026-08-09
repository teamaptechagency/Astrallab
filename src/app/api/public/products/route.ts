import { NextResponse } from "next/server";
import { db } from "@/lib/db";
import { compareVersions } from "@/lib/version";

// GET /api/public/products — the catalogue astralab.com renders from.
//
// Public and unauthenticated by design: this is shop-window data. What it must
// never expose is the business behind it — no licence counts, no revenue, no
// customer emails, no internal notes. Only `summary` is customer-facing;
// `description` stays in the console.
//
// Price is deliberately absent. WooCommerce owns pricing, and having two
// systems each believe they know the price is how a storefront ends up
// advertising one number and charging another.

export const dynamic = "force-dynamic";

// Any origin may read it — it is public data, and astralab.com is a different
// origin from manage.astralab.com, so without this the browser blocks the
// storefront's own fetch.
const CORS = {
  "Access-Control-Allow-Origin": "*",
  "Access-Control-Allow-Methods": "GET, OPTIONS",
  // Cached briefly: a catalogue changes rarely, and this endpoint sits in
  // front of every storefront page load.
  "Cache-Control": "public, max-age=60, s-maxage=300",
};

export async function OPTIONS() {
  return new NextResponse(null, { status: 204, headers: CORS });
}

export async function GET() {
  const products = await db.product.findMany({
    where: { active: true },
    include: {
      releases: {
        where: { published: true, channel: "stable" },
        select: { version: true, notes: true, createdAt: true, sizeBytes: true },
      },
    },
    orderBy: { createdAt: "asc" },
  });

  const catalogue = products.map((p) => {
    const latest = p.releases.length
      ? p.releases.reduce((a, b) => (compareVersions(a.version, b.version) >= 0 ? a : b))
      : null;

    return {
      slug: p.slug,
      name: p.name,
      summary: p.summary,
      latestVersion: latest?.version ?? null,
      releasedAt: latest?.createdAt ?? null,
      releaseNotes: latest?.notes ?? null,
      downloadSizeBytes: latest?.sizeBytes ?? null,
      // A product with nothing published cannot be installed even if someone
      // buys it, so the storefront should not offer it for sale yet.
      available: latest !== null,
    };
  });

  return NextResponse.json({ products: catalogue }, { headers: CORS });
}
