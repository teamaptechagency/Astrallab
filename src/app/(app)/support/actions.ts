"use server";

import { revalidatePath } from "next/cache";
import { db } from "@/lib/db";
import { requireOperator } from "@/lib/require-operator";

const STATUSES = new Set(["open", "investigating", "resolved", "closed"]);
const SEVERITIES = new Set(["low", "normal", "high", "critical"]);

export async function updateReport(formData: FormData) {
  await requireOperator();

  const id = String(formData.get("id") ?? "");
  if (!id) return;

  const status = String(formData.get("status") ?? "");
  const severity = String(formData.get("severity") ?? "");
  const fixedIn = String(formData.get("fixedIn") ?? "").trim();

  await db.report.update({
    where: { id },
    data: {
      ...(STATUSES.has(status) ? { status } : {}),
      ...(SEVERITIES.has(severity) ? { severity } : {}),
      fixedIn: fixedIn || null,
    },
  });

  revalidatePath("/support");
  revalidatePath("/");
}
