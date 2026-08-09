import { requireApiPermission } from "@/lib/operator-session";
import { resolveRange, getSales, isRefunded } from "@/lib/sales";

// GET /api/admin/sales.csv?range=30d — sales export for an accountant.
//
// Built from the same getSales() the page uses, so an exported figure can never
// disagree with the one on screen. Two implementations of "revenue" is how a
// spreadsheet ends up contradicting the dashboard, and then nobody trusts
// either.

export const dynamic = "force-dynamic";

/**
 * Escape a CSV field.
 *
 * The leading-character guard matters: Excel and Sheets treat a value starting
 * =, +, - or @ as a formula. A customer name of "=cmd|..." becomes code
 * execution on whoever opens the file. Prefixing an apostrophe forces it back
 * to text.
 */
function csvField(value: string | number | null): string {
  if (value === null) return "";
  let text = String(value);
  if (/^[=+\-@\t\r]/.test(text)) text = `'${text}`;
  if (/[",\n\r]/.test(text)) text = `"${text.replace(/"/g, '""')}"`;
  return text;
}

export async function GET(request: Request) {
  const auth = await requireApiPermission("sales.view");
  if ("denied" in auth) return auth.denied;

  const range = new URL(request.url).searchParams.get("range") ?? undefined;
  const { from, to, key } = resolveRange(range);
  const sales = await getSales(from, to);

  const header = [
    "date",
    "order_ref",
    "product",
    "product_slug",
    "customer_name",
    "customer_email",
    "licence_last4",
    "amount",
    "currency",
    "status",
    "refunded",
  ];

  const rows = sales.map((s) =>
    [
      s.createdAt.toISOString(),
      s.orderRef,
      s.productName,
      s.productSlug,
      s.customerName,
      s.customerEmail,
      s.keyLast4,
      s.amount,
      s.currency,
      s.status,
      isRefunded(s) ? "yes" : "no",
    ]
      .map(csvField)
      .join(","),
  );

  // CRLF and a BOM: Excel on Windows misreads UTF-8 without one, which turns
  // any non-ASCII customer name into mojibake.
  const body = `﻿${[header.join(","), ...rows].join("\r\n")}\r\n`;

  return new Response(body, {
    headers: {
      "Content-Type": "text/csv; charset=utf-8",
      "Content-Disposition": `attachment; filename="astralab-sales-${key}.csv"`,
      "Cache-Control": "no-store",
    },
  });
}
