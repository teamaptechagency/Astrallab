import { PrismaClient } from "@prisma/client";

// Shared teardown for the test scripts.
//
// Every test issues real licences through the real API — that is the point,
// since a mocked issue would prove nothing. But a test licence left behind is
// not harmless: it inflates revenue on the Sales page, lands in the CSV an
// accountant reads, and counts towards the issuance rate limit that exists to
// spot a compromised store. So each script removes exactly what it created.

const db = new PrismaClient();

/** Delete licences whose order reference starts with any of these prefixes. */
export async function cleanupByOrderRef(prefixes: string[]): Promise<number> {
  let removed = 0;
  for (const prefix of prefixes) {
    const result = await db.licence.deleteMany({ where: { orderRef: { startsWith: prefix } } });
    removed += result.count;
  }
  await db.$disconnect();
  return removed;
}
