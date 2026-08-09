"use server";

import { revalidatePath } from "next/cache";
import { db } from "@/lib/db";
import { requireOperator } from "@/lib/require-operator";

export async function addTransaction(formData: FormData) {
  await requireOperator();

  const amount = Number(formData.get("amount"));
  const occurredRaw = String(formData.get("occurredAt") ?? "");
  const occurredAt = new Date(occurredRaw);

  // A NaN amount or an unparseable date would poison every total on the page,
  // and silently — the figures would just be wrong.
  if (!Number.isFinite(amount) || amount <= 0 || Number.isNaN(occurredAt.getTime())) return;

  const kind = String(formData.get("kind") ?? "expense");

  await db.transaction.create({
    data: {
      kind: kind === "income" ? "income" : "expense",
      amount,
      category: String(formData.get("category") ?? "other"),
      note: String(formData.get("note") ?? "").trim() || null,
      occurredAt,
    },
  });

  revalidatePath("/finance");
  revalidatePath("/");
}

export async function deleteTransaction(formData: FormData) {
  await requireOperator();

  const id = String(formData.get("id") ?? "");
  if (!id) return;

  await db.transaction.delete({ where: { id } }).catch(() => {});
  revalidatePath("/finance");
  revalidatePath("/");
}
