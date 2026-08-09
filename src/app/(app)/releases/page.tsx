import { db } from "@/lib/db";
import { PageHeader, Table, Td, Badge, EmptyState, when } from "@/components/ui";
import { createRelease, togglePublished } from "./actions";

export const dynamic = "force-dynamic";

export default async function ReleasesPage() {
  const [releases, products] = await Promise.all([
    db.release.findMany({ include: { product: true }, orderBy: { createdAt: "desc" } }),
    db.product.findMany({ orderBy: { name: "asc" } }),
  ]);

  return (
    <>
      <PageHeader
        title="Releases"
        subtitle="Versions offered to installs. Publishing makes a release visible to every site."
      />

      <details className="card mb-5 p-4">
        <summary className="cursor-pointer text-sm font-medium">New release</summary>
        <form action={createRelease} className="mt-4 grid gap-3 sm:grid-cols-2">
          <label className="text-sm">
            <span className="mb-1 block text-ink-500">Product</span>
            <select name="productId" required className="field">
              {products.map((p) => (
                <option key={p.id} value={p.id}>
                  {p.name}
                </option>
              ))}
            </select>
          </label>
          <label className="text-sm">
            <span className="mb-1 block text-ink-500">Version</span>
            <input name="version" required placeholder="1.4.0" className="field" />
          </label>
          <label className="text-sm">
            <span className="mb-1 block text-ink-500">Severity</span>
            <select name="severity" className="field">
              <option value="normal">normal</option>
              <option value="security">security</option>
            </select>
          </label>
          <label className="text-sm">
            <span className="mb-1 block text-ink-500">Minimum upgrade from</span>
            <input name="minUpgradeFrom" placeholder="blank unless this is a checkpoint" className="field" />
          </label>
          <label className="text-sm sm:col-span-2">
            <span className="mb-1 block text-ink-500">Notes</span>
            <textarea name="notes" rows={3} className="field" placeholder="What changed, in the customer's words." />
          </label>
          <label className="text-sm sm:col-span-2">
            <span className="mb-1 block text-ink-500">Package checksum (SHA-256)</span>
            <input name="checksum" required placeholder="sha256-…" className="field" />
          </label>
          <div className="sm:col-span-2">
            <button className="btn-primary">Create as draft</button>
            <p className="mt-2 text-xs text-ink-400">
              Created unpublished. Upload the artefact to <code>packages/</code>, then publish —
              publishing before the file exists offers customers a download that returns an error.
            </p>
          </div>
        </form>
      </details>

      {releases.length === 0 ? (
        <EmptyState title="No releases yet" body="Create one to start offering updates to installs." />
      ) : (
        <Table head={["Version", "Product", "Severity", "Checkpoint", "State", "Created", ""]}>
          {releases.map((r) => (
            <tr key={r.id}>
              <Td mono>{r.version}</Td>
              <Td>{r.product.name}</Td>
              <Td>{r.severity === "security" ? <Badge value="security" /> : "normal"}</Td>
              <Td mono>{r.minUpgradeFrom ?? "—"}</Td>
              <Td>{r.published ? <Badge value="active" /> : <Badge value="unactivated" />}</Td>
              <Td mono>{when(r.createdAt)}</Td>
              <Td>
                <form action={togglePublished}>
                  <input type="hidden" name="id" value={r.id} />
                  <button className="btn-ghost !px-2 !py-1 text-xs">
                    {r.published ? "Unpublish" : "Publish"}
                  </button>
                </form>
              </Td>
            </tr>
          ))}
        </Table>
      )}
    </>
  );
}
