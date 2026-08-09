import Link from "next/link";
import { db } from "@/lib/db";
import { PageHeader, Table, Td, Badge, EmptyState, money } from "@/components/ui";
import { createProduct, toggleProductActive } from "./actions";

export const dynamic = "force-dynamic";

// What Astra Lab sells. Adding a second product — a portfolio builder, a
// premium theme, anything — is a row here plus the same slug on the matching
// WooCommerce product. No code changes: licensing, activation, updates and
// downloads are all already scoped by product.

export default async function ProductsPage() {
  const products = await db.product.findMany({
    include: {
      _count: { select: { licences: true, releases: true } },
      licences: { select: { amount: true, status: true } },
    },
    orderBy: [{ active: "desc" }, { createdAt: "asc" }],
  });

  return (
    <>
      <PageHeader title="Products" subtitle="What Astra Lab sells" />

      <details className="card mb-5 p-4">
        <summary className="cursor-pointer text-sm font-medium">Add a product</summary>
        <form action={createProduct} className="mt-4 grid gap-3 sm:grid-cols-2">
          <label className="text-sm">
            <span className="mb-1 block text-ink-500">Name</span>
            <input name="name" required placeholder="Astra Lab Portfolio" className="field" />
          </label>
          <label className="text-sm">
            <span className="mb-1 block text-ink-500">Slug</span>
            <input
              name="slug"
              required
              placeholder="astralab-portfolio"
              // The dash must be escaped: browsers compile `pattern` with the
              // `v` flag, where a bare `-` inside a character class is a
              // syntax error — and an invalid pattern is ignored entirely,
              // so this validated nothing at all.
              pattern="[a-z0-9\-]+"
              title="Lowercase letters, numbers and hyphens only."
              className="field"
            />
          </label>
          <label className="text-sm sm:col-span-2">
            <span className="mb-1 block text-ink-500">Description</span>
            <input name="description" placeholder="Internal note — not shown to customers." className="field" />
          </label>
          <div className="sm:col-span-2">
            <button className="btn-primary">Add product</button>
            <p className="mt-2 text-xs text-ink-400">
              Then set the same slug on the matching WooCommerce product, and publish a release.
              Nothing else is needed — licensing, activation and updates are already scoped by
              product.
            </p>
          </div>
        </form>
      </details>

      {products.length === 0 ? (
        <EmptyState title="No products" body="Add the first thing you sell to start issuing licences for it." />
      ) : (
        <Table head={["Product", "Slug", "Licences", "Releases", "Revenue", "State", ""]}>
          {products.map((p) => {
            const revenue = p.licences
              .filter((l) => l.status !== "revoked")
              .reduce((sum, l) => sum + (l.amount ?? 0), 0);

            return (
              <tr key={p.id}>
                <Td>
                  <span className="block">{p.name}</span>
                  {p.description && <span className="text-xs text-ink-400">{p.description}</span>}
                </Td>
                <Td mono>{p.slug}</Td>
                <Td mono>{p._count.licences}</Td>
                <Td mono>
                  <Link href="/releases" className="text-brand-600 hover:underline">
                    {p._count.releases}
                  </Link>
                </Td>
                <Td mono>{money(revenue)}</Td>
                <Td>{p.active ? <Badge value="active" /> : <Badge value="closed" />}</Td>
                <Td>
                  <form action={toggleProductActive}>
                    <input type="hidden" name="id" value={p.id} />
                    <button className="btn-ghost !px-2 !py-1 text-xs">
                      {p.active ? "Retire" : "Reactivate"}
                    </button>
                  </form>
                </Td>
              </tr>
            );
          })}
        </Table>
      )}

      <div className="card mt-5 p-4 text-sm text-ink-500">
        <p className="font-medium text-ink-700 dark:text-ink-200">Adding a product later</p>
        <ol className="mt-2 list-decimal space-y-1 pl-5">
          <li>Add it here with a slug that will never change.</li>
          <li>Set that same slug on the WooCommerce product, so a sale issues the right licence.</li>
          <li>Create a release, upload the artefact, then publish it.</li>
        </ol>
        <p className="mt-3 text-xs text-ink-400">
          Retiring a product stops new sales making sense but leaves existing installs working —
          they keep activating and updating. Products are never deleted, because licences reference
          them permanently.
        </p>
      </div>
    </>
  );
}
