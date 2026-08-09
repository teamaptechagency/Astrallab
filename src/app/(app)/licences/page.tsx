import { db } from "@/lib/db";
import { PageHeader, Table, Td, Badge, EmptyState, money, when } from "@/components/ui";
import { setLicenceStatus } from "./actions";

export const dynamic = "force-dynamic";

import { requirePermission } from "@/lib/operator-session";
import { can } from "@/lib/roles";

export default async function LicencesPage({
  searchParams,
}: {
  searchParams: Promise<{ q?: string; status?: string }>;
}) {
  const me = await requirePermission("licences.view");
  const { q, status } = await searchParams;

  const licences = await db.licence.findMany({
    where: {
      ...(status ? { status } : {}),
      ...(q
        ? {
            OR: [
              { customerEmail: { contains: q } },
              { orderRef: { contains: q } },
              // Customers read out the last four characters over the phone —
              // the full key is not stored, so this is the only way to find a
              // licence from what they can actually tell you.
              { keyLast4: { contains: q.toUpperCase() } },
            ],
          }
        : {}),
    },
    include: { product: true, activations: { where: { releasedAt: null } } },
    orderBy: { createdAt: "desc" },
    take: 100,
  });

  return (
    <>
      <PageHeader
        title="Licences"
        subtitle="Every key issued, and where it is installed"
      />

      <form className="mb-4 flex flex-wrap gap-2">
        <input
          name="q"
          defaultValue={q ?? ""}
          placeholder="Search email, order, or last 4 of key"
          className="field max-w-xs"
        />
        <select name="status" defaultValue={status ?? ""} className="field max-w-[10rem]">
          <option value="">All statuses</option>
          {["unactivated", "active", "deactivated", "suspended", "revoked"].map((s) => (
            <option key={s} value={s}>
              {s}
            </option>
          ))}
        </select>
        <button type="submit" className="btn-primary">
          Search
        </button>
      </form>

      {licences.length === 0 ? (
        <EmptyState
          title="No licences match"
          body="Licences appear here the moment the store reports a paid order."
        />
      ) : (
        <Table head={["Key", "Customer", "Status", "Domain", "Paid", "Issued", ""]}>
          {licences.map((l) => (
            <tr key={l.id}>
              <Td mono>••••{l.keyLast4}</Td>
              <Td>
                <span className="block">{l.customerEmail}</span>
                <span className="text-xs text-ink-400">{l.product.name}</span>
              </Td>
              <Td>
                <Badge value={l.status} />
              </Td>
              <Td mono>{l.activations.map((a) => a.domain).join(", ") || "—"}</Td>
              <Td mono>{l.amount === null ? "—" : money(l.amount, l.currency)}</Td>
              <Td mono>{when(l.createdAt)}</Td>
              <Td>
                <div className="flex gap-1.5">
                  {l.status !== "revoked" && l.status !== "suspended" && (
                    <form action={setLicenceStatus}>
                      <input type="hidden" name="id" value={l.id} />
                      <input type="hidden" name="status" value="suspended" />
                      <button className="btn-ghost !px-2 !py-1 text-xs">Suspend</button>
                    </form>
                  )}
                  {l.status === "suspended" && (
                    <form action={setLicenceStatus}>
                      <input type="hidden" name="id" value={l.id} />
                      <input type="hidden" name="status" value="active" />
                      <button className="btn-ghost !px-2 !py-1 text-xs">Restore</button>
                    </form>
                  )}
                  {l.status !== "revoked" && can(me.role, "licences.revoke") && (
                    <form action={setLicenceStatus}>
                      <input type="hidden" name="id" value={l.id} />
                      <input type="hidden" name="status" value="revoked" />
                      <button className="btn-danger !px-2 !py-1 text-xs">Revoke</button>
                    </form>
                  )}
                </div>
              </Td>
            </tr>
          ))}
        </Table>
      )}
    </>
  );
}
