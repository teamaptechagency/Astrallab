import { db } from "@/lib/db";
import { PageHeader, Table, Td, EmptyState, StatCard, money, when } from "@/components/ui";

export const dynamic = "force-dynamic";

import { requirePermission } from "@/lib/operator-session";

export default async function ShopDataPage({
  searchParams,
}: {
  searchParams: Promise<{ q?: string }>;
}) {
  await requirePermission("shopdata.view");
  const { q } = await searchParams;

  const [products, total, shops] = await Promise.all([
    db.syncedProduct.findMany({
      where: q ? { name: { contains: q } } : {},
      include: { licence: { select: { keyLast4: true } } },
      orderBy: { syncedAt: "desc" },
      take: 100,
    }),
    db.syncedProduct.count(),
    db.syncedProduct.groupBy({ by: ["licenceId"], _count: true }),
  ]);

  return (
    <>
      <PageHeader
        title="Shop data"
        subtitle="Product catalogues pushed up from customer shops"
      />

      <div className="mb-5 grid grid-cols-2 gap-3 lg:grid-cols-4">
        <StatCard label="Products" value={total} hint="across all shops" />
        <StatCard label="Shops syncing" value={shops.length} />
      </div>

      <form className="mb-4 flex gap-2">
        <input name="q" defaultValue={q ?? ""} placeholder="Search product name" className="field max-w-xs" />
        <button className="btn-primary">Search</button>
      </form>

      {products.length === 0 ? (
        <EmptyState
          title="No products synced yet"
          body="Installs push their catalogue to the hub on a schedule. Nothing appears until a customer site is live and syncing."
          hint="Product data is the shop owner's own — usually already public on their storefront. Leads are treated separately and stricter, because those belong to their customers."
        />
      ) : (
        <Table head={["Product", "Shop", "Price", "Stock", "Synced"]}>
          {products.map((p) => (
            <tr key={p.id}>
              <Td>
                <span className="block">{p.name}</span>
                {p.category && <span className="text-xs text-ink-400">{p.category}</span>}
              </Td>
              <Td mono>{p.domain}</Td>
              <Td mono>{p.price === null ? "—" : money(p.price, p.currency)}</Td>
              <Td mono>{p.stock ?? "—"}</Td>
              <Td mono>{when(p.syncedAt)}</Td>
            </tr>
          ))}
        </Table>
      )}
    </>
  );
}
