import { db } from "@/lib/db";
import { PageHeader, StatCard, Table, Td, Badge, EmptyState, money, when } from "@/components/ui";
import { addTransaction, deleteTransaction } from "./actions";

export const dynamic = "force-dynamic";

const CATEGORIES = ["hosting", "salary", "marketing", "refund", "tools", "other"];

import { requirePermission } from "@/lib/operator-session";

export default async function FinancePage() {
  // Hiding the nav link is presentation; this is the access control. A URL
  // typed directly must be refused just as firmly.
  await requirePermission("finance.view");

  const yearStart = new Date(new Date().getFullYear(), 0, 1);

  const [licences, transactions] = await Promise.all([
    db.licence.findMany({
      where: { createdAt: { gte: yearStart } },
      select: { amount: true, currency: true, createdAt: true, status: true },
    }),
    db.transaction.findMany({ orderBy: { occurredAt: "desc" }, take: 100 }),
  ]);

  // Sales revenue is derived from licences, never stored as transactions —
  // one source of truth, so the two can never drift apart. Revoked licences
  // are excluded: a refunded sale is not revenue.
  const salesRevenue = licences
    .filter((l) => l.status !== "revoked")
    .reduce((sum, l) => sum + (l.amount ?? 0), 0);

  const otherIncome = transactions
    .filter((t) => t.kind === "income")
    .reduce((sum, t) => sum + t.amount, 0);
  const expenses = transactions
    .filter((t) => t.kind === "expense")
    .reduce((sum, t) => sum + t.amount, 0);

  const net = salesRevenue + otherIncome - expenses;

  return (
    <>
      <PageHeader title="Finance" subtitle={`Earnings and expenses since ${yearStart.getFullYear()}`} />

      <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <StatCard label="Licence sales" value={money(salesRevenue)} hint="excludes revoked" tone="positive" />
        <StatCard label="Other income" value={money(otherIncome)} />
        <StatCard label="Expenses" value={money(expenses)} tone="warning" />
        <StatCard label="Net" value={money(net)} tone={net >= 0 ? "positive" : "danger"} />
      </div>

      <p className="mt-3 text-xs text-ink-400">
        Licence sales come from what each order actually charged, so past months are not rewritten
        when the price changes. Only money the hub cannot know by itself is recorded below.
      </p>

      <details className="card my-5 p-4">
        <summary className="cursor-pointer text-sm font-medium">Record a transaction</summary>
        <form action={addTransaction} className="mt-4 grid gap-3 sm:grid-cols-4">
          <label className="text-sm">
            <span className="mb-1 block text-ink-500">Type</span>
            <select name="kind" className="field">
              <option value="expense">expense</option>
              <option value="income">income</option>
            </select>
          </label>
          <label className="text-sm">
            <span className="mb-1 block text-ink-500">Amount</span>
            <input name="amount" type="number" step="0.01" min="0" required className="field" />
          </label>
          <label className="text-sm">
            <span className="mb-1 block text-ink-500">Category</span>
            <select name="category" className="field">
              {CATEGORIES.map((c) => (
                <option key={c}>{c}</option>
              ))}
            </select>
          </label>
          <label className="text-sm">
            <span className="mb-1 block text-ink-500">Date</span>
            <input name="occurredAt" type="date" required className="field" />
          </label>
          <label className="text-sm sm:col-span-3">
            <span className="mb-1 block text-ink-500">Note</span>
            <input name="note" placeholder="What was this for?" className="field" />
          </label>
          <div className="flex items-end">
            <button className="btn-primary w-full">Add</button>
          </div>
        </form>
      </details>

      {transactions.length === 0 ? (
        <EmptyState
          title="No transactions recorded"
          body="Add hosting bills, contractor payments, refunds and ad spend here. Licence sales are counted automatically."
        />
      ) : (
        <Table head={["Date", "Type", "Category", "Amount", "Note", ""]}>
          {transactions.map((t) => (
            <tr key={t.id}>
              <Td mono>{when(t.occurredAt).slice(0, 10)}</Td>
              <Td>
                <Badge value={t.kind} />
              </Td>
              <Td>{t.category}</Td>
              <Td mono>{money(t.amount, t.currency)}</Td>
              <Td>{t.note ?? "—"}</Td>
              <Td>
                <form action={deleteTransaction}>
                  <input type="hidden" name="id" value={t.id} />
                  <button className="btn-danger !px-2 !py-1 text-xs">Delete</button>
                </form>
              </Td>
            </tr>
          ))}
        </Table>
      )}
    </>
  );
}
