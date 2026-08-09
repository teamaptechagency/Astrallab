import { existsSync } from "node:fs";
import path from "node:path";
import { db } from "@/lib/db";
import { PageHeader, Table, Td, Badge, EmptyState, when } from "@/components/ui";
import { PackageUpload } from "@/components/package-upload";
import { createRelease, togglePublished } from "./actions";

export const dynamic = "force-dynamic";

export default async function ReleasesPage() {
  const [releases, products] = await Promise.all([
    db.release.findMany({ include: { product: true }, orderBy: { createdAt: "desc" } }),
    db.product.findMany({ where: { active: true }, orderBy: { name: "asc" } }),
  ]);

  // Whether the artefact is actually on disk, checked per row. The database
  // row existing proves nothing — the file is what customers download.
  const packageDir = path.join(process.cwd(), "packages");
  const hasPackage = new Map(
    releases.map((r) => [
      r.id,
      existsSync(path.join(packageDir, r.productId, `${r.version}.zip`)),
    ]),
  );

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
          <div className="sm:col-span-2">
            <button className="btn-primary">Create as draft</button>
            <p className="mt-2 text-xs text-ink-400">
              Created unpublished. Upload the .zip against the row below — the checksum is computed
              from the file itself, so there is nothing to paste and nothing to get wrong. Publishing
              is blocked until a file exists.
            </p>
          </div>
        </form>
      </details>

      {releases.length === 0 ? (
        <EmptyState title="No releases yet" body="Create one to start offering updates to installs." />
      ) : (
        <Table head={["Version", "Product", "Severity", "Checkpoint", "Package", "State", ""]}>
          {releases.map((r) => {
            const ready = hasPackage.get(r.id) ?? false;
            return (
              <tr key={r.id}>
                <Td mono>
                  <span className="block">{r.version}</span>
                  <span className="text-[11px] text-ink-400">{when(r.createdAt)}</span>
                </Td>
                <Td>{r.product.name}</Td>
                <Td>{r.severity === "security" ? <Badge value="security" /> : "normal"}</Td>
                <Td mono>{r.minUpgradeFrom ?? "—"}</Td>
                <Td>
                  <div className="flex items-center gap-2">
                    {ready ? (
                      <span className="text-xs text-ink-500">
                        {(r.sizeBytes / 1_048_576).toFixed(1)} MB
                      </span>
                    ) : (
                      <span className="text-xs text-amber-600">no file</span>
                    )}
                    <PackageUpload releaseId={r.id} hasPackage={ready} />
                  </div>
                </Td>
                <Td>{r.published ? <Badge value="active" /> : <Badge value="unactivated" />}</Td>
                <Td>
                  {r.published ? (
                    <form action={togglePublished}>
                      <input type="hidden" name="id" value={r.id} />
                      <button className="btn-ghost !px-2 !py-1 text-xs">Unpublish</button>
                    </form>
                  ) : ready ? (
                    <form action={togglePublished}>
                      <input type="hidden" name="id" value={r.id} />
                      <button className="btn-ghost !px-2 !py-1 text-xs">Publish</button>
                    </form>
                  ) : (
                    <span
                      className="text-xs text-ink-400"
                      title="Upload the package first — publishing without one offers every install a download that errors."
                    >
                      upload first
                    </span>
                  )}
                </Td>
              </tr>
            );
          })}
        </Table>
      )}
    </>
  );
}
