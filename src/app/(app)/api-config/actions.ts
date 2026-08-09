"use server";

import { revalidatePath } from "next/cache";
import { requirePermission } from "@/lib/operator-session";
import { setSetting, SETTINGS } from "@/lib/settings";

/**
 * Record that the store secret was rotated in the environment.
 *
 * Deliberately only a note — it does not generate or change anything. A button
 * that minted a new secret here would invalidate the WooCommerce plugin's copy
 * the instant it was pressed, and every order placed before someone pasted the
 * new value into WordPress would fail to receive a licence. The safe sequence
 * is: change the env, restart, update the plugin, then record it here.
 */
export async function rotateStoreSecret() {
  await requirePermission("apiconfig.rotate");
  await setSetting(SETTINGS.STORE_SECRET_ROTATED_AT, new Date().toISOString().slice(0, 16).replace("T", " "));
  revalidatePath("/api-config");
}
