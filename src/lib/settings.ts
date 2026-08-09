import { db } from "@/lib/db";

// Runtime switches, read from the database rather than the environment, so
// they can change without a deploy.
//
// This is what makes the Ayojon rollout possible at all. Every CMS install
// asks the hub what integrations exist; flipping ayojon.status here turns the
// feature on for every customer at once, with no update shipped to any of
// them. Baking it into the CMS instead would mean an integration launch
// requires every site in the field to update first — which some never will.

export const SETTINGS = {
  AYOJON_STATUS: "ayojon.status",
  AYOJON_CONNECT_URL: "ayojon.connect_url",
  AYOJON_MESSAGE: "ayojon.message",
  STORE_SECRET_ROTATED_AT: "store_secret.rotated_at",
} as const;

/** coming_soon — teased but not usable. available — connect flow is live.
 *  disabled — hidden entirely, for pulling it back if something goes wrong. */
export type AyojonStatus = "coming_soon" | "available" | "disabled";

const DEFAULTS: Record<string, string> = {
  [SETTINGS.AYOJON_STATUS]: "coming_soon",
  [SETTINGS.AYOJON_CONNECT_URL]: "",
  [SETTINGS.AYOJON_MESSAGE]:
    "Ayojon is coming. Connect this store to manage it from the Ayojon mobile app.",
};

export async function getSetting(key: string): Promise<string> {
  const row = await db.setting.findUnique({ where: { key } });
  return row?.value ?? DEFAULTS[key] ?? "";
}

export async function setSetting(key: string, value: string): Promise<void> {
  await db.setting.upsert({ where: { key }, update: { value }, create: { key, value } });
}

export interface IntegrationState {
  status: AyojonStatus;
  connectUrl: string | null;
  message: string;
}

/**
 * What a CMS install should show on its Integrations page.
 *
 * `available` without a connect URL is treated as `coming_soon` — a connect
 * button that goes nowhere is worse than an honest "not yet".
 */
export async function getAyojonState(): Promise<IntegrationState> {
  const [statusRaw, connectUrl, message] = await Promise.all([
    getSetting(SETTINGS.AYOJON_STATUS),
    getSetting(SETTINGS.AYOJON_CONNECT_URL),
    getSetting(SETTINGS.AYOJON_MESSAGE),
  ]);

  let status = (["coming_soon", "available", "disabled"] as const).includes(
    statusRaw as AyojonStatus,
  )
    ? (statusRaw as AyojonStatus)
    : "coming_soon";

  if (status === "available" && !connectUrl) status = "coming_soon";

  return {
    status,
    connectUrl: status === "available" ? connectUrl : null,
    message,
  };
}
