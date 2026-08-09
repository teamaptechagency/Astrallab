"use server";

import { existsSync } from "node:fs";
import path from "node:path";
import { revalidatePath } from "next/cache";
import { db } from "@/lib/db";
import { requireOperator } from "@/lib/require-operator";
import { parseVersion } from "@/lib/version";

export async function createRelease(formData: FormData) {
  await requireOperator();

  const productId = String(formData.get("productId") ?? "");
  const version = String(formData.get("version") ?? "").trim();

  // A version that doesn't parse breaks the upgrade path for every install —
  // comparison treats it as lowest, so it would be offered forever.
  if (!productId || !parseVersion(version)) return;

  const minUpgradeFrom = String(formData.get("minUpgradeFrom") ?? "").trim();

  await db.release.create({
    data: {
      productId,
      version,
      // Filled in from the file itself when the artefact is uploaded. Until
      // then it is a placeholder, and publishing is blocked anyway.
      checksum: "",
      severity: String(formData.get("severity") ?? "normal"),
      notes: String(formData.get("notes") ?? ""),
      minUpgradeFrom: minUpgradeFrom && parseVersion(minUpgradeFrom) ? minUpgradeFrom : null,
      packageUrl: `packages/${productId}/${version}.zip`,
      // Always a draft. Publishing is a separate, deliberate action, because a
      // published release with no artefact hands every customer a broken update.
      published: false,
    },
  });

  revalidatePath("/releases");
}

export async function togglePublished(formData: FormData) {
  await requireOperator();

  const id = String(formData.get("id") ?? "");
  const release = await db.release.findUnique({
    where: { id },
    select: { published: true, productId: true, version: true, checksum: true },
  });
  if (!release) return;

  // Publishing without an artefact would advertise an update to every install
  // and then hand each of them a 503. The UI hides the button, but the button
  // is not the guard — this is.
  if (!release.published) {
    const file = path.join(process.cwd(), "packages", release.productId, `${release.version}.zip`);
    if (!existsSync(file) || !release.checksum) return;
  }

  await db.release.update({ where: { id }, data: { published: !release.published } });
  revalidatePath("/releases");
  revalidatePath("/api/public/products");
}
