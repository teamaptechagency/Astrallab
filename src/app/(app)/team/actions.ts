"use server";

import { revalidatePath } from "next/cache";
import { db } from "@/lib/db";
import { requirePermission, revokeAllSessions } from "@/lib/operator-session";
import { hashPassword, passwordProblem } from "@/lib/password";
import { isRole } from "@/lib/roles";

/**
 * The last active owner is protected throughout this file.
 *
 * Demote or deactivate them and nobody can manage the team again — recovery
 * means editing the database by hand. Cheap to guard, miserable to undo.
 */
async function isLastOwner(id: string): Promise<boolean> {
  const subject = await db.operator.findUnique({ where: { id }, select: { role: true, active: true } });
  if (!subject || subject.role !== "owner" || !subject.active) return false;
  const owners = await db.operator.count({ where: { role: "owner", active: true } });
  return owners <= 1;
}

export async function addOperator(formData: FormData) {
  await requirePermission("team.manage");

  const name = String(formData.get("name") ?? "").trim();
  const email = String(formData.get("email") ?? "").trim().toLowerCase();
  const role = String(formData.get("role") ?? "support");
  const password = String(formData.get("password") ?? "");

  if (!name || !email.includes("@") || !isRole(role)) return;
  if (passwordProblem(password)) return;

  const existing = await db.operator.findUnique({ where: { email }, select: { id: true } });
  if (existing) return;

  await db.operator.create({
    data: { name, email, role, passwordHash: await hashPassword(password) },
  });

  revalidatePath("/team");
}

export async function setOperatorRole(formData: FormData) {
  await requirePermission("team.manage");

  const id = String(formData.get("id") ?? "");
  const role = String(formData.get("role") ?? "");
  if (!id || !isRole(role)) return;

  if (role !== "owner" && (await isLastOwner(id))) return;

  await db.operator.update({ where: { id }, data: { role } });
  revalidatePath("/team");
}

export async function setOperatorActive(formData: FormData) {
  const me = await requirePermission("team.manage");

  const id = String(formData.get("id") ?? "");
  const active = String(formData.get("active") ?? "") === "1";
  if (!id) return;

  // Locking yourself out is always a mistake, never an intention.
  if (id === me.id) return;
  if (!active && (await isLastOwner(id))) return;

  await db.operator.update({ where: { id }, data: { active } });

  // Deactivating must end their access now, not whenever their session
  // happens to expire.
  if (!active) await revokeAllSessions(id);

  revalidatePath("/team");
}

export async function signOutEverywhere(formData: FormData) {
  await requirePermission("team.manage");

  const id = String(formData.get("id") ?? "");
  if (!id) return;

  await revokeAllSessions(id);
  revalidatePath("/team");
}
