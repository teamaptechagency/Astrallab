import { db } from "@/lib/db";
import { PageHeader, Table, Td, Badge, EmptyState, StatCard, when } from "@/components/ui";

export const dynamic = "force-dynamic";

export default async function LeadsPage() {
  const [leads, total, shops] = await Promise.all([
    db.lead.findMany({ orderBy: { capturedAt: "desc" }, take: 100 }),
    db.lead.count(),
    db.lead.groupBy({ by: ["licenceId"], _count: true }),
  ]);

  return (
    <>
      <PageHeader title="Leads" subtitle="Enquiries captured on customer shops" />

      <div className="mb-5 grid grid-cols-2 gap-3 lg:grid-cols-4">
        <StatCard label="Leads" value={total} />
        <StatCard label="Shops sharing" value={shops.length} hint="opted in" />
      </div>

      <div className="mb-5 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-300">
        <p className="font-medium">This is other people&apos;s personal data.</p>
        <p className="mt-1 text-[13px]">
          These are the customers of your customers — people with no relationship to Astra Lab. The
          sync endpoint only accepts leads from installs that explicitly opted in, and it refuses
          them otherwise. Keep it that way: disclose it in your terms, and let shop owners turn it
          off.
        </p>
      </div>

      {leads.length === 0 ? (
        <EmptyState
          title="No leads collected"
          body="Nothing arrives here unless a shop owner has explicitly enabled lead sharing for their install."
        />
      ) : (
        <Table head={["Name", "Contact", "Source", "Shop", "Captured"]}>
          {leads.map((l) => (
            <tr key={l.id}>
              <Td>{l.name ?? "—"}</Td>
              <Td mono>{l.phone ?? l.email ?? "—"}</Td>
              <Td>
                <Badge value={l.source} />
              </Td>
              <Td mono>{l.domain}</Td>
              <Td mono>{when(l.capturedAt)}</Td>
            </tr>
          ))}
        </Table>
      )}
    </>
  );
}
