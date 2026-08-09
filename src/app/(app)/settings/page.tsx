import { getSetting, SETTINGS } from "@/lib/settings";
import { PageHeader } from "@/components/ui";
import { saveAyojonSettings } from "./actions";

export const dynamic = "force-dynamic";

import { requirePermission } from "@/lib/operator-session";

export default async function SettingsPage() {
  await requirePermission("settings.manage");

  const [status, connectUrl, message] = await Promise.all([
    getSetting(SETTINGS.AYOJON_STATUS),
    getSetting(SETTINGS.AYOJON_CONNECT_URL),
    getSetting(SETTINGS.AYOJON_MESSAGE),
  ]);

  return (
    <>
      <PageHeader title="Settings" subtitle="Switches every install reads from the hub" />

      <section className="card p-5">
        <h2 className="font-medium">Ayojon integration</h2>
        <p className="mt-1 max-w-2xl text-sm text-ink-500">
          Every install asks the hub what to show on its Integrations page. Changing this reaches
          all of them on their next check — no update is shipped to any customer site.
        </p>

        <form action={saveAyojonSettings} className="mt-4 grid max-w-2xl gap-3">
          <label className="text-sm">
            <span className="mb-1 block text-ink-500">Status</span>
            <select name="status" defaultValue={status} className="field">
              <option value="coming_soon">coming soon — teased, not connectable</option>
              <option value="available">available — connect flow is live</option>
              <option value="disabled">disabled — hidden entirely</option>
            </select>
          </label>

          <label className="text-sm">
            <span className="mb-1 block text-ink-500">Connect URL</span>
            <input
              name="connectUrl"
              defaultValue={connectUrl}
              placeholder="https://ayojone.com/connect"
              className="field"
            />
            <span className="mt-1 block text-xs text-ink-400">
              Required for &ldquo;available&rdquo;. Without it the hub falls back to coming soon — a
              connect button that goes nowhere is worse than an honest &ldquo;not yet&rdquo;.
            </span>
          </label>

          <label className="text-sm">
            <span className="mb-1 block text-ink-500">Message shown to customers</span>
            <textarea name="message" defaultValue={message} rows={2} className="field" />
          </label>

          <div>
            <button className="btn-primary">Save</button>
          </div>
        </form>
      </section>
    </>
  );
}
