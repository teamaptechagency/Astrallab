import { db } from "@/lib/db";
import { getAyojonState } from "@/lib/settings";
import { PageHeader, StatCard, Table, Td, Badge, EmptyState, money, when } from "@/components/ui";

export const dynamic = "force-dynamic";

export default async function DashboardPage() {
  const monthStart = new Date();
  monthStart.setDate(1);
  monthStart.setHours(0, 0, 0, 0);

  const [licences, activeInstalls, openReports, monthLicences, expenses, events, ayojon] =
    await Promise.all([
      db.licence.count(),
      db.activation.count({ where: { releasedAt: null } }),
      db.report.count({ where: { status: { in: ["open", "investigating"] } } }),
      db.licence.findMany({
        where: { createdAt: { gte: monthStart } },
        select: { amount: true },
      }),
      db.transaction.aggregate({
        where: { kind: "expense", occurredAt: { gte: monthStart } },
        _sum: { amount: true },
      }),
      db.licenceEvent.findMany({
        orderBy: { createdAt: "desc" },
        take: 12,
        include: { licence: { select: { keyLast4: true } } },
      }),
      getAyojonState(),
    ]);

  // Revenue comes from what each order actually charged, not from a licence
  // count times today's price — otherwise every price change would silently
  // rewrite past months.
  const monthRevenue = monthLicences.reduce((sum, l) => sum + (l.amount ?? 0), 0);
  const monthExpense = expenses._sum.amount ?? 0;
  const missingAmounts = monthLicences.filter((l) => l.amount === null).length;

  return (
    <>
      <PageHeader title="Dashboard" subtitle="Astra Lab at a glance" />

      <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <StatCard label="Licences" value={licences} hint="issued all time" />
        <StatCard label="Active installs" value={activeInstalls} hint="bound to a domain" tone="positive" />
        <StatCard
          label="Open reports"
          value={openReports}
          hint="bugs and feedback"
          tone={openReports > 0 ? "warning" : "default"}
        />
        <StatCard
          label="Net this month"
          value={money(monthRevenue - monthExpense)}
          hint={`${money(monthRevenue)} in · ${money(monthExpense)} out`}
          tone={monthRevenue - monthExpense >= 0 ? "positive" : "danger"}
        />
      </div>

      {missingAmounts > 0 && (
        <p className="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-400">
          {missingAmounts} licence{missingAmounts === 1 ? "" : "s"} this month carry no order amount, so
          revenue is understated. The store sends this only if the plugin is current.
        </p>
      )}

      <div className="mt-6 grid gap-4 lg:grid-cols-3">
        <div className="lg:col-span-2">
          <h2 className="mb-2 text-sm font-medium text-ink-500">Recent activity</h2>
          {events.length === 0 ? (
            <EmptyState
              title="Nothing has happened yet"
              body="Licence activity appears here as installs activate, update and transfer."
            />
          ) : (
            <Table head={["When", "Event", "Licence", "Domain"]}>
              {events.map((e) => (
                <tr key={e.id}>
                  <Td mono>{when(e.createdAt)}</Td>
                  <Td>
                    <Badge value={e.kind} />
                  </Td>
                  <Td mono>••••{e.licence.keyLast4}</Td>
                  <Td mono>{e.domain ?? "—"}</Td>
                </tr>
              ))}
            </Table>
          )}
        </div>

        <div>
          <h2 className="mb-2 text-sm font-medium text-ink-500">Ayojon</h2>
          <div className="card p-4">
            <Badge value={ayojon.status} />
            <p className="mt-3 text-sm text-ink-500">
              Every install reads this from the hub. Switching it in Settings reaches all of them
              without shipping an update.
            </p>
          </div>
        </div>
      </div>
    </>
  );
}
