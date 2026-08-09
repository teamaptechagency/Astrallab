import { PrismaClient } from "@prisma/client";

// Removes every transactional row, leaving reference data intact.
//
//   npx tsx --env-file=.env scripts/purge-test-data.ts          (dry run)
//   npx tsx --env-file=.env scripts/purge-test-data.ts --apply
//
// Cleared: licences, activations, events, reports, synced products, leads,
// transactions — everything created by selling and running.
//
// Kept: products, releases, operators, settings — what the business is, not
// what happened to it.
//
// Run this before going live. Test licences left in the database are not
// harmless: they inflate revenue, appear in the sales export an accountant
// reads, and count towards the issuance rate limit that exists to detect a
// compromised store.

const db = new PrismaClient();
const APPLY = process.argv.includes("--apply");

async function main() {
  const counts = {
    licences: await db.licence.count(),
    activations: await db.activation.count(),
    events: await db.licenceEvent.count(),
    reports: await db.report.count(),
    syncedProducts: await db.syncedProduct.count(),
    leads: await db.lead.count(),
    transactions: await db.transaction.count(),
  };

  console.log("Transactional rows present:");
  for (const [name, n] of Object.entries(counts)) console.log(`  ${name}: ${n}`);

  const kept = {
    products: await db.product.count(),
    releases: await db.release.count(),
    operators: await db.operator.count(),
  };
  console.log("\nKept:");
  for (const [name, n] of Object.entries(kept)) console.log(`  ${name}: ${n}`);

  if (!APPLY) {
    console.log("\nDry run. Re-run with --apply to delete.");
    return;
  }

  // Order matters only where cascades don't cover it; deleting licences takes
  // activations, events and AyojonLinks with them.
  await db.transaction.deleteMany({});
  await db.lead.deleteMany({});
  await db.syncedProduct.deleteMany({});
  await db.report.deleteMany({});
  await db.licence.deleteMany({});

  console.log("\nPurged. Products, releases, operators and settings untouched.");
}

main()
  .catch((e) => {
    console.error(e);
    process.exitCode = 1;
  })
  .finally(() => db.$disconnect());
