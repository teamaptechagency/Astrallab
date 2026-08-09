import Link from "next/link";
import { requirePermission } from "@/lib/operator-session";
import { PageHeader, StatCard, Table, Td, Badge, EmptyState, money, when } from "@/components/ui";
import { SalesChart } from "@/components/sales-chart";
import {
  RANGES,
  resolveRange,
  getSales,
  summarise,
  bucketise,
  byProduct,
  byCustomer,
  isRefunded,
} from "@/lib/sales";

export const dynamic = "force-dynamic";

export default async function SalesPage({
  searchParams,
}: {
  searchParams: Promise<{ range?: string }>;
}) {
  await requirePermission("sales.view");

  const { range } = await searchParams;
  const { from, to, key } = resolveRange(range);

  const sales = await getSales(from, to);
  const summary = summarise(sales);
  const products = byProduct(sales);
  const customers = byCustomer(sales);

  return (
    <>
      <PageHeader
        title="Sales"
        subtitle="Every purchase, where it came from and what it earned"
        action={
          <a href={`/api/admin/sales.csv?range=${key}`} className="btn-ghost text-xs">
            Export CSV
          </a>
        }
      />

      <div className="mb-5 flex flex-wrap gap-1.5">
        {RANGES.map((r) => (
          <Link
            key={r.key}
            href={`/sales?range=${r.key}`}
            className={
              r.key === key
                ? "rounded-lg bg-brand-600 px-3 py-1.5 text-xs font-medium text-white"
                : "rounded-lg border border-ink-200 px-3 py-1.5 text-xs text-ink-600 hover:bg-ink-50 dark:border-ink-700 dark:text-ink-300 dark:hover:bg-ink-800"
            }
          >
            {r.label}
          </Link>
        ))}
      </div>

      <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <StatCard label="Revenue" value={money(summary.revenue)} hint="excludes refunded" tone="positive" />
        <StatCard label="Orders" value={summary.orders} hint={`${summary.refunded} refunded`} />
        <StatCard label="Average order" value={money(summary.averageOrder)} />
        <StatCard
          label="Refunded"
          value={summary.refunded}
          tone={summary.refunded > 0 ? "warning" : "default"}
        />
      </div>

      {summary.missingAmount > 0 && (
        <p className="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-400">
          {summary.missingAmount} order{summary.missingAmount === 1 ? "" : "s"} in this period carry no
          recorded amount, so revenue is understated by exactly that much. The store only sends the
          order total if the licence plugin is current.
        </p>
      )}

      <h2 className="mb-2 mt-6 text-sm font-medium text-ink-500">Revenue over time</h2>
      <SalesChart buckets={bucketise(sales, from, to)} />

      <div className="mt-6 grid gap-4 lg:grid-cols-2">
        <div>
          <h2 className="mb-2 text-sm font-medium text-ink-500">By product</h2>
          {products.length === 0 ? (
            <EmptyState title="Nothing sold yet" body="Product totals appear once orders come in." />
          ) : (
            <Table head={["Product", "Orders", "Revenue"]}>
              {products.map((p) => (
                <tr key={p.name}>
                  <Td>{p.name}</Td>
                  <Td mono>{p.orders}</Td>
                  <Td mono>{money(p.revenue)}</Td>
                </tr>
              ))}
            </Table>
          )}
        </div>

        <div>
          <h2 className="mb-2 text-sm font-medium text-ink-500">Top customers</h2>
          {customers.length === 0 ? (
            <EmptyState title="No customers yet" body="Repeat buyers rise to the top here." />
          ) : (
            <Table head={["Customer", "Orders", "Revenue"]}>
              {customers.map((c) => (
                <tr key={c.email}>
                  <Td>{c.email}</Td>
                  <Td mono>{c.orders}</Td>
                  <Td mono>{money(c.revenue)}</Td>
                </tr>
              ))}
            </Table>
          )}
        </div>
      </div>

      <h2 className="mb-2 mt-6 text-sm font-medium text-ink-500">Orders</h2>
      {sales.length === 0 ? (
        <EmptyState
          title="No sales in this period"
          body="Every paid order the store reports appears here, with what it charged."
        />
      ) : (
        <Table head={["Date", "Order", "Product", "Customer", "Amount", "Status"]}>
          {sales.map((s) => (
            <tr key={s.id} className={isRefunded(s) ? "opacity-60" : undefined}>
              <Td mono>{when(s.createdAt)}</Td>
              <Td mono>{s.orderRef}</Td>
              <Td>{s.productName}</Td>
              <Td>
                <span className="block">{s.customerEmail}</span>
                <span className="text-xs text-ink-400">••••{s.keyLast4}</span>
              </Td>
              <Td mono>
                {s.amount === null ? (
                  <span className="text-amber-600">not recorded</span>
                ) : (
                  money(s.amount, s.currency)
                )}
              </Td>
              <Td>
                <Badge value={s.status} />
              </Td>
            </tr>
          ))}
        </Table>
      )}

      <p className="mt-4 text-xs text-ink-400">
        A sale is a licence — one purchase, one key — so these figures come from the licences
        actually issued rather than a separate orders table that could drift out of step. Revoked
        licences are treated as refunded and excluded from revenue.
      </p>
    </>
  );
}
