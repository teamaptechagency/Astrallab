import { readFileSync, existsSync } from "node:fs";
import path from "node:path";
import { requirePermission } from "@/lib/operator-session";
import { can } from "@/lib/roles";
import { PageHeader, Badge } from "@/components/ui";
import { CopyBox } from "@/components/copy-box";
import { rotateStoreSecret } from "./actions";
import { getSetting, SETTINGS } from "@/lib/settings";

export const dynamic = "force-dynamic";

// What the store and every install need in order to talk to this hub.
//
// Secret VALUES are never rendered — only whether each is configured. A page
// that prints live secrets turns one borrowed laptop, one screen-share, or one
// screenshot in a chat into a full compromise.

export default async function ApiConfigPage() {
  const me = await requirePermission("apiconfig.view");

  const publicKeyPath = path.join(process.cwd(), "cms-public-key.pem");
  const publicKey = existsSync(publicKeyPath)
    ? readFileSync(publicKeyPath, "utf8").trim()
    : null;

  const rotatedAt = await getSetting(SETTINGS.STORE_SECRET_ROTATED_AT);

  const secrets = [
    {
      key: "LICENCE_SECRET",
      set: Boolean(process.env.LICENCE_SECRET),
      note: "Hashes licence keys at rest. Changing it makes every issued licence unfindable — never rotate this from a UI.",
      rotatable: false,
    },
    {
      key: "SIGNING_PRIVATE_KEY",
      set: Boolean(process.env.SIGNING_PRIVATE_KEY),
      note: "Signs responses to installs. Rotating invalidates every cached validation in the field and needs a CMS update carrying the new public key.",
      rotatable: false,
    },
    {
      key: "PACKAGE_URL_SECRET",
      set: Boolean(process.env.PACKAGE_URL_SECRET),
      note: "Signs download links. Rotating only breaks links minted in the last hour, so it is comparatively safe — but still an env change.",
      rotatable: false,
    },
    {
      key: "STORE_API_SECRET",
      set: Boolean(process.env.STORE_API_SECRET),
      note: "Shared with the WooCommerce plugin. The one credential that genuinely needs rotating, since it lives on a WordPress site.",
      rotatable: true,
    },
  ];

  const base = process.env.NEXT_PUBLIC_HUB_URL ?? "https://manage.astrallabs.uk";

  return (
    <>
      <PageHeader title="API config" subtitle="What the store and customer installs connect to" />

      <section className="card mb-5 p-5">
        <h2 className="font-medium">Endpoints</h2>
        <p className="mt-1 text-sm text-ink-500">
          Set the hub URL in the WooCommerce plugin to the base below. Installs derive the rest.
        </p>
        <div className="mt-3 space-y-2">
          <CopyBox label="Hub URL (WooCommerce plugin setting)" value={base} />
          <CopyBox label="Public catalogue (astrallabs.uk reads this)" value={`${base}/api/public/products`} />
        </div>

        <dl className="mt-4 grid gap-2 text-sm sm:grid-cols-2">
          {[
            ["POST /api/v1/licences", "store issues a licence on a paid order"],
            ["POST /api/v1/activate", "installer binds a licence to a domain"],
            ["POST /api/v1/heartbeat", "install checks for updates"],
            ["POST /api/v1/deactivate", "customer releases a domain"],
            ["GET /api/v1/download", "package delivery, token-gated"],
            ["POST /api/v1/report", "bug reports from inside the CMS"],
            ["POST /api/v1/sync", "catalogue and lead push"],
            ["POST /api/v1/integrations", "what the Integrations page shows"],
          ].map(([route, purpose]) => (
            <div key={route} className="flex flex-col">
              <dt className="font-mono text-xs text-ink-700 dark:text-ink-200">{route}</dt>
              <dd className="text-xs text-ink-400">{purpose}</dd>
            </div>
          ))}
        </dl>
      </section>

      <section className="card mb-5 p-5">
        <h2 className="font-medium">Secrets</h2>
        <p className="mt-1 text-sm text-ink-500">
          Values are never shown here — only whether they are configured. All live in the
          environment.
        </p>

        <div className="mt-4 space-y-3">
          {secrets.map((s) => (
            <div key={s.key} className="flex flex-wrap items-start justify-between gap-2 border-t border-ink-100 pt-3 dark:border-ink-800">
              <div className="min-w-0">
                <p className="font-mono text-xs">{s.key}</p>
                <p className="mt-0.5 max-w-xl text-xs text-ink-400">{s.note}</p>
              </div>
              <div className="flex items-center gap-2">
                {s.set ? <Badge value="active" /> : <Badge value="revoked" />}
                {s.rotatable && can(me.role, "apiconfig.rotate") && (
                  <form action={rotateStoreSecret}>
                    <button className="btn-ghost !px-2 !py-1 text-xs">Mark rotated</button>
                  </form>
                )}
              </div>
            </div>
          ))}
        </div>

        {rotatedAt && (
          <p className="mt-3 text-xs text-ink-400">
            Store secret last marked rotated: {rotatedAt}
          </p>
        )}

        <p className="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-300">
          Rotating a secret means changing it in the environment and restarting. There is
          deliberately no button that generates a new one here: a rotation that takes effect before
          the other side is updated drops every request in between — paid orders that never receive
          a licence.
        </p>
      </section>

      {publicKey && (
        <section className="card p-5">
          <h2 className="font-medium">CMS public key</h2>
          <p className="mt-1 text-sm text-ink-500">
            Ship this inside the CMS so installs can verify our responses. Public by design — it can
            verify a signature but never produce one.
          </p>
          <div className="mt-3">
            <CopyBox label="Ed25519 public key" value={publicKey} multiline />
          </div>
        </section>
      )}
    </>
  );
}
