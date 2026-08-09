"use server";

import { revalidatePath } from "next/cache";
import { db } from "@/lib/db";
import { requireOperator } from "@/lib/require-operator";
import { parseVersion } from "@/lib/version";

export async function createRelease(formData: FormData) {
  await requireOperator();

  const productId = String(formData.get("productId") ?? "");
  const version = String(formData.get("version") ?? "").trim();
  const checksum = String(formData.get("checksum") ?? "").trim();

  // A version that doesn't parse breaks the upgrade path for every install —
  // comparison treats it as lowest, so it would be offered forever.
  if (!productId || !checksum || !parseVersion(version)) return;

  const minUpgradeFrom = String(formData.get("minUpgradeFrom") ?? "").trim();

  await db.release.create({
    data: {
      productId,
      version,
      checksum,
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
  const release = await db.release.findUnique({ where: { id }, select: { published: true } });
  if (!release) return;

  await db.release.update({ where: { id }, data: { published: !release.published } });
  revalidatePath("/releases");
}
