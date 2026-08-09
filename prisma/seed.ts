import { PrismaClient } from "@prisma/client";

// Seeds the product being sold plus a small release history, so the upgrade
// path has something real to compute against. 1.2.0 is marked as a checkpoint
// (minUpgradeFrom) — an install on 1.0.0 must stop there before continuing,
// which is exactly the case that breaks sites if it isn't modelled.

const db = new PrismaClient();

async function main() {
  const product = await db.product.upsert({
    where: { slug: "astralab-cms" },
    update: {},
    create: { slug: "astralab-cms", name: "Astra Lab E-commerce CMS" },
  });

  const releases = [
    { version: "1.0.0", notes: "First public release.", severity: "normal", minUpgradeFrom: null },
    { version: "1.1.0", notes: "Coupon rules and shipping zones.", severity: "normal", minUpgradeFrom: null },
    {
      version: "1.2.0",
      notes: "Order schema rebuilt. Must be applied before any later version.",
      severity: "normal",
      minUpgradeFrom: "1.1.0",
    },
    { version: "1.2.1", notes: "Fixes checkout total rounding.", severity: "normal", minUpgradeFrom: null },
    {
      version: "1.3.0",
      notes: "Patches an authentication bypass in the admin session check.",
      severity: "security",
      minUpgradeFrom: null,
    },
  ];

  for (const r of releases) {
    await db.release.upsert({
      where: { productId_version: { productId: product.id, version: r.version } },
      update: {},
      create: {
        productId: product.id,
        version: r.version,
        notes: r.notes,
        severity: r.severity,
        minUpgradeFrom: r.minUpgradeFrom,
        published: true,
        // Placeholder artefacts — real ones are uploaded by the release
        // manager. Checksums are what the installer verifies before extracting.
        packageUrl: `https://packages.astralab.com/cms/${r.version}.zip`,
        checksum: `sha256-placeholder-${r.version}`,
        sizeBytes: 24_000_000,
      },
    });
  }

  console.log(`Seeded ${product.name} with ${releases.length} releases.`);
}

main()
  .catch((e) => {
    console.error(e);
    process.exitCode = 1;
  })
  .finally(() => db.$disconnect());
