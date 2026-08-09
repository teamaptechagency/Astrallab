import { db } from "@/lib/db";

// Sales reporting.
//
// A sale IS a licence here — one purchase, one key — so this reads from the
// Licence table rather than duplicating orders into a second table that could
// drift out of step with the licences actually issued.
//
// Deliberately shared by the page and the CSV export, so an exported figure
// can never disagree with the one on screen. Two implementations of "revenue"
// is how a spreadsheet ends up contradicting the dashboard.

export type RangeKey = "7d" | "30d" | "month" | "last_month" | "year" | "all";

export const RANGES: { key: RangeKey; label: string }[] = [
  { key: "7d", label: "Last 7 days" },
  { key: "30d", label: "Last 30 days" },
  { key: "month", label: "This month" },
  { key: "last_month", label: "Last month" },
  { key: "year", label: "This year" },
  { key: "all", label: "All time" },
];

export function resolveRange(key: string | undefined): { from: Date; to: Date; key: RangeKey } {
  const now = new Date();
  const startOfToday = new Date(now.getFullYear(), now.getMonth(), now.getDate());

  switch (key) {
    case "7d":
      return { from: new Date(startOfToday.getTime() - 6 * 86_400_000), to: now, key: "7d" };
    case "month":
      return { from: new Date(now.getFullYear(), now.getMonth(), 1), to: now, key: "month" };
    case "last_month":
      return {
        from: new Date(now.getFullYear(), now.getMonth() - 1, 1),
        // Day 0 of this month is the last day of the previous one.
        to: new Date(now.getFullYear(), now.getMonth(), 0, 23, 59, 59),
        key: "last_month",
      };
    case "year":
      return { from: new Date(now.getFullYear(), 0, 1), to: now, key: "year" };
    case "all":
      return { from: new Date(0), to: now, key: "all" };
    case "30d":
    default:
      return { from: new Date(startOfToday.getTime() - 29 * 86_400_000), to: now, key: "30d" };
  }
}

export interface SaleRow {
  id: string;
  createdAt: Date;
  orderRef: string;
  customerEmail: string;
  customerName: string | null;
  productName: string;
  productSlug: string;
  amount: number | null;
  currency: string;
  status: string;
  keyLast4: string;
}

export async function getSales(from: Date, to: Date): Promise<SaleRow[]> {
  const licences = await db.licence.findMany({
    where: { createdAt: { gte: from, lte: to } },
    include: { product: { select: { name: true, slug: true } } },
    orderBy: { createdAt: "desc" },
  });

  return licences.map((l) => ({
    id: l.id,
    createdAt: l.createdAt,
    orderRef: l.orderRef,
    customerEmail: l.customerEmail,
    customerName: l.customerName,
    productName: l.product.name,
    productSlug: l.product.slug,
    amount: l.amount,
    currency: l.currency,
    status: l.status,
    keyLast4: l.keyLast4,
  }));
}

/** A revoked licence is a sale that was undone — refund, chargeback or fraud. */
export function isRefunded(sale: SaleRow): boolean {
  return sale.status === "revoked";
}

export interface SalesSummary {
  orders: number;
  refunded: number;
  revenue: number;
  averageOrder: number;
  /** Sales with no recorded amount — revenue is understated by exactly these. */
  missingAmount: number;
}

export function summarise(sales: SaleRow[]): SalesSummary {
  const kept = sales.filter((s) => !isRefunded(s));
  const revenue = kept.reduce((sum, s) => sum + (s.amount ?? 0), 0);
  // Averaged over sales that actually carry a figure, so a few older rows with
  // no amount don't silently drag the average down.
  const withAmount = kept.filter((s) => s.amount !== null);

  return {
    orders: sales.length,
    refunded: sales.length - kept.length,
    revenue,
    averageOrder: withAmount.length ? revenue / withAmount.length : 0,
    missingAmount: kept.length - withAmount.length,
  };
}

export interface Bucket {
  label: string;
  /** ISO date for the tooltip; the label is short enough to fit an axis. */
  iso: string;
  orders: number;
  revenue: number;
}

/**
 * Group sales into buckets for the trend chart.
 *
 * Every bucket in the range is emitted, including empty ones — a chart that
 * skips quiet days compresses time and makes a bad week look like a normal one.
 */
export function bucketise(sales: SaleRow[], from: Date, to: Date): Bucket[] {
  const spanDays = Math.ceil((to.getTime() - from.getTime()) / 86_400_000);
  const byMonth = spanDays > 92;

  const buckets = new Map<string, Bucket>();
  const keyFor = (iso: string) => (byMonth ? iso.slice(0, 7) : iso);

  const cursor = new Date(from);
  if (byMonth) cursor.setDate(1);
  cursor.setHours(0, 0, 0, 0);

  while (cursor <= to) {
    const iso = cursor.toISOString().slice(0, 10);
    buckets.set(keyFor(iso), {
      iso,
      label: byMonth
        ? cursor.toLocaleDateString("en-GB", { month: "short" })
        : String(cursor.getDate()),
      orders: 0,
      revenue: 0,
    });

    if (byMonth) cursor.setMonth(cursor.getMonth() + 1);
    else cursor.setDate(cursor.getDate() + 1);
  }

  for (const sale of sales) {
    if (isRefunded(sale)) continue;
    const bucket = buckets.get(keyFor(sale.createdAt.toISOString().slice(0, 10)));
    if (!bucket) continue;
    bucket.orders += 1;
    bucket.revenue += sale.amount ?? 0;
  }

  return [...buckets.values()];
}

export interface ProductBreakdown {
  name: string;
  orders: number;
  revenue: number;
}

export function byProduct(sales: SaleRow[]): ProductBreakdown[] {
  const map = new Map<string, ProductBreakdown>();
  for (const sale of sales) {
    if (isRefunded(sale)) continue;
    const entry = map.get(sale.productSlug) ?? { name: sale.productName, orders: 0, revenue: 0 };
    entry.orders += 1;
    entry.revenue += sale.amount ?? 0;
    map.set(sale.productSlug, entry);
  }
  return [...map.values()].sort((a, b) => b.revenue - a.revenue);
}

export interface CustomerBreakdown {
  email: string;
  orders: number;
  revenue: number;
}

export function byCustomer(sales: SaleRow[], limit = 10): CustomerBreakdown[] {
  const map = new Map<string, CustomerBreakdown>();
  for (const sale of sales) {
    if (isRefunded(sale)) continue;
    const entry = map.get(sale.customerEmail) ?? {
      email: sale.customerEmail,
      orders: 0,
      revenue: 0,
    };
    entry.orders += 1;
    entry.revenue += sale.amount ?? 0;
    map.set(sale.customerEmail, entry);
  }
  return [...map.values()].sort((a, b) => b.revenue - a.revenue).slice(0, limit);
}
