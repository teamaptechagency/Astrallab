"use server";

import { revalidatePath } from "next/cache";
import { db } from "@/lib/db";
import { requirePermission } from "@/lib/operator-session";

const ALLOWED = new Set(["active", "suspended", "revoked"]);

/**
 * Change a licence's status by hand.
 *
 * Every change is written to the licence's event log rather than only
 * mutating the row, because "who revoked this and when" is exactly the
 * question asked after a customer complains their site stopped working.
 */
export async function setLicenceStatus(formData: FormData) {
  const id = String(formData.get("id") ?? "");
  const status = String(formData.get("status") ?? "");
  if (!id || !ALLOWED.has(status)) return;

  // Revoking is terminal and takes a customer's storefront down for good;
  // suspending is reversible. Support handles payment disputes daily and needs
  // the second without ever being one misclick from the first.
  const me = await requirePermission(status === "revoked" ? "licences.revoke" : "licences.suspend");

  const licence = await db.licence.findUnique({ where: { id }, select: { status: true } });
  if (!licence) return;

  await db.licence.update({ where: { id }, data: { status } });
  await db.licenceEvent.create({
    data: {
      licenceId: id,
      kind: "status_changed",
      detail: `${licence.status} → ${status}`,
      // Previously this said "(operator)" and meant nothing, because a single
      // shared password could not tell anyone apart.
      actor: `${me.name} <${me.email}>`,
    },
  });

  revalidatePath("/licences");
  revalidatePath("/");
}
