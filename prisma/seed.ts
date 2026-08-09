import { createHash } from "node:crypto";
import { mkdir, writeFile } from "node:fs/promises";
import path from "node:path";
import { PrismaClient } from "@prisma/client";

// Seeds the product being sold plus a small release history, and writes a
// placeholder artefact for each one.
//
// The artefacts matter: a published release with no file on disk offers every
// install an update that fails to download. The console now refuses to publish
// in that state, so the seed must not create it either — otherwise seeded data
// would be a shape the application itself forbids.
//
// 1.2.0 is a checkpoint (minUpgradeFrom): an install on 1.0.0 must stop there
// before continuing, which is the case that breaks sites when it isn't modelled.

const db = new PrismaClient();
const PACKAGE_DIR = path.join(process.cwd(), "packages");

const RELEASES = [
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

async function main() {
  const summary = "Self-hosted online store. Install on ordinary shared hosting in minutes.";

  const product = await db.product.upsert({
    where: { slug: "astralab-cms" },
    // Applied on re-seed too, so a row created before a field existed picks it
    // up rather than staying blank forever.
    update: { summary },
    create: { slug: "astralab-cms", name: "Astra Lab E-commerce CMS", summary },
  });

  const dir = path.join(PACKAGE_DIR, product.id);
  await mkdir(dir, { recursive: true });

  for (const r of RELEASES) {
    // Stand-in for a real build artefact. Deterministic, so re-running the
    // seed does not churn checksums, and large enough that a ranged download
    // actually spans several chunks.
    const contents = Buffer.alloc(64 * 1024, `astralab ${product.slug} ${r.version}\n`);
    await writeFile(path.join(dir, `${r.version}.zip`), contents);
    const checksum = `sha256-${createHash("sha256").update(contents).digest("hex")}`;

    await db.release.upsert({
      where: { productId_version: { productId: product.id, version: r.version } },
      update: { checksum, sizeBytes: contents.length, published: true },
      create: {
        productId: product.id,
        version: r.version,
        notes: r.notes,
        severity: r.severity,
        minUpgradeFrom: r.minUpgradeFrom,
        published: true,
        packageUrl: `packages/${product.id}/${r.version}.zip`,
        checksum,
        sizeBytes: contents.length,
      },
    });
  }

  console.log(`Seeded ${product.name} with ${RELEASES.length} releases and placeholder artefacts.`);
}

main()
  .catch((e) => {
    console.error(e);
    process.exitCode = 1;
  })
  .finally(() => db.$disconnect());
