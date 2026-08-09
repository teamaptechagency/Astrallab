"use server";

import { revalidatePath } from "next/cache";
import { db } from "@/lib/db";
import { requireOperator } from "@/lib/require-operator";

const ALLOWED = new Set(["active", "suspended", "revoked"]);

/**
 * Change a licence's status by hand.
 *
 * Every change is written to the licence's event log rather than only
 * mutating the row, because "who revoked this and when" is exactly the
 * question asked after a customer complains their site stopped working.
 */
export async function setLicenceStatus(formData: FormData) {
  await requireOperator();

  const id = String(formData.get("id") ?? "");
  const status = String(formData.get("status") ?? "");
  if (!id || !ALLOWED.has(status)) return;

  const licence = await db.licence.findUnique({ where: { id }, select: { status: true } });
  if (!licence) return;

  await db.licence.update({ where: { id }, data: { status } });
  await db.licenceEvent.create({
    data: {
      licenceId: id,
      kind: "status_changed",
      detail: `${licence.status} → ${status} (operator)`,
    },
  });

  revalidatePath("/licences");
  revalidatePath("/");
}
