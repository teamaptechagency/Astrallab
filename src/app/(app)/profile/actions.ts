"use server";

import { redirect } from "next/navigation";
import { revalidatePath } from "next/cache";
import { db } from "@/lib/db";
import { requireOperator, revokeAllSessions, createOperatorSession } from "@/lib/operator-session";
import { hashPassword, verifyPassword, passwordProblem } from "@/lib/password";

export async function updateProfile(formData: FormData) {
  const me = await requireOperator();

  const name = String(formData.get("name") ?? "").trim();
  const email = String(formData.get("email") ?? "").trim().toLowerCase();
  if (!name || !email.includes("@")) redirect("/profile?error=Enter a name and a valid email.");

  const clash = await db.operator.findUnique({ where: { email }, select: { id: true } });
  if (clash && clash.id !== me.id) redirect("/profile?error=That email is already in use.");

  await db.operator.update({ where: { id: me.id }, data: { name, email } });
  revalidatePath("/profile");
  redirect("/profile?saved=1");
}

export async function changePassword(formData: FormData) {
  const me = await requireOperator();

  const current = String(formData.get("current") ?? "");
  const next = String(formData.get("next") ?? "");

  const operator = await db.operator.findUniqueOrThrow({ where: { id: me.id } });

  // Requiring the current password is what stops a borrowed unlocked laptop
  // becoming a permanent takeover of the account.
  if (!(await verifyPassword(current, operator.passwordHash))) {
    redirect("/profile?error=Current password is incorrect.");
  }

  const problem = passwordProblem(next);
  if (problem) redirect(`/profile?error=${encodeURIComponent(problem)}`);

  await db.operator.update({
    where: { id: me.id },
    data: { passwordHash: await hashPassword(next) },
  });

  // A password change should end every existing session — that is usually the
  // whole point of changing it. Then re-issue one for this device so the
  // person doing it is not immediately logged out.
  await revokeAllSessions(me.id);
  await createOperatorSession(me.id);

  revalidatePath("/profile");
  redirect("/profile?saved=1");
}

export async function endOtherSessions() {
  const me = await requireOperator();
  await revokeAllSessions(me.id);
  await createOperatorSession(me.id);
  revalidatePath("/profile");
  redirect("/profile?saved=1");
}
