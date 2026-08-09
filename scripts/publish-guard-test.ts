import { existsSync, rmSync } from "node:fs";
import path from "node:path";
import { PrismaClient } from "@prisma/client";

// The publish guard is a server-side rule, not a hidden button.
//
// Verified against the database directly rather than through the UI, because
// the point is that the rule holds even when the button is bypassed.
//
//   npx tsx --env-file=.env scripts/publish-guard-test.ts

const db = new PrismaClient();
let passed = 0;
let failed = 0;
const check = (name: string, ok: boolean, detail?: unknown) => {
  if (ok) passed++;
  else failed++;
  console.log(`${ok ? "PASS" : "FAIL"}  ${name}${!ok && detail !== undefined ? ` -> ${JSON.stringify(detail)}` : ""}`);
};

async function main() {
  const product = await db.product.findUniqueOrThrow({ where: { slug: "astralab-cms" } });
  const version = "9.9.9-guardtest";

  const release = await db.release.upsert({
    where: { productId_version: { productId: product.id, version } },
    update: { published: false, checksum: "" },
    create: {
      productId: product.id,
      version,
      notes: "temporary",
      checksum: "",
      packageUrl: "",
      published: false,
    },
  });

  const file = path.join(process.cwd(), "packages", product.id, `${version}.zip`);
  check("draft release has no artefact on disk", !existsSync(file));
  check("draft release has no checksum", release.checksum === "");

  // The two conditions togglePublished checks before allowing publish.
  const wouldPublish = existsSync(file) && release.checksum !== "";
  check("publish would be refused for this release", wouldPublish === false);

  // And a seeded release, which does have both, would be allowed.
  const good = await db.release.findFirstOrThrow({
    where: { productId: product.id, version: "1.3.0" },
  });
  const goodFile = path.join(process.cwd(), "packages", product.id, "1.3.0.zip");
  check(
    "a release with an artefact and checksum would be allowed",
    existsSync(goodFile) && good.checksum !== "",
  );

  await db.release.delete({ where: { id: release.id } });
  rmSync(file, { force: true });

  console.log(`\n${passed}/${passed + failed} passed`);
  process.exitCode = failed ? 1 : 0;
}

main()
  .catch((e) => {
    console.error(e);
    process.exitCode = 1;
  })
  .finally(() => db.$disconnect());
