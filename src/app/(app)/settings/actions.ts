"use server";

import { revalidatePath } from "next/cache";
import { setSetting, SETTINGS } from "@/lib/settings";
import { requireOperator } from "@/lib/require-operator";

const STATUSES = new Set(["coming_soon", "available", "disabled"]);

export async function saveAyojonSettings(formData: FormData) {
  await requireOperator();

  const status = String(formData.get("status") ?? "");
  if (!STATUSES.has(status)) return;

  await Promise.all([
    setSetting(SETTINGS.AYOJON_STATUS, status),
    setSetting(SETTINGS.AYOJON_CONNECT_URL, String(formData.get("connectUrl") ?? "").trim()),
    setSetting(SETTINGS.AYOJON_MESSAGE, String(formData.get("message") ?? "").trim()),
  ]);

  revalidatePath("/settings");
  revalidatePath("/");
}
