import { db } from "@/lib/db";
import { PageHeader, Table, Td, Badge, EmptyState, when } from "@/components/ui";
import { updateReport } from "./actions";

export const dynamic = "force-dynamic";

export default async function SupportPage({
  searchParams,
}: {
  searchParams: Promise<{ status?: string }>;
}) {
  const { status } = await searchParams;

  const reports = await db.report.findMany({
    where: status ? { status } : { status: { in: ["open", "investigating"] } },
    include: { licence: { select: { keyLast4: true, customerEmail: true } } },
    orderBy: [{ severity: "desc" }, { createdAt: "desc" }],
    take: 100,
  });

  return (
    <>
      <PageHeader
        title="Support"
        subtitle="Bug reports and feedback submitted from inside customer installs"
      />

      <form className="mb-4 flex gap-2">
        <select name="status" defaultValue={status ?? ""} className="field max-w-[12rem]">
          <option value="">Open + investigating</option>
          {["open", "investigating", "resolved", "closed"].map((s) => (
            <option key={s} value={s}>
              {s}
            </option>
          ))}
        </select>
        <button className="btn-primary">Filter</button>
      </form>

      {reports.length === 0 ? (
        <EmptyState
          title="Nothing to answer"
          body="Reports arrive here when a customer submits one from their CMS."
          hint="Each one carries the licence, domain, CMS version and PHP version automatically — the three things you would otherwise spend two replies asking for."
        />
      ) : (
        <div className="space-y-3">
          {reports.map((r) => (
            <article key={r.id} className="card p-4">
              <div className="flex flex-wrap items-start justify-between gap-2">
                <div className="min-w-0">
                  <h2 className="font-medium">{r.subject}</h2>
                  <p className="mt-0.5 text-xs text-ink-400">
                    {r.licence ? `••••${r.licence.keyLast4} · ${r.licence.customerEmail}` : "unlinked"}
                    {r.domain ? ` · ${r.domain}` : ""} · {when(r.createdAt)}
                  </p>
                </div>
                <div className="flex flex-wrap gap-1.5">
                  <Badge value={r.kind} />
                  <Badge value={r.severity} />
                  <Badge value={r.status} />
                </div>
              </div>

              <p className="mt-3 whitespace-pre-wrap text-sm text-ink-600 dark:text-ink-300">{r.body}</p>

              <p className="mt-3 text-xs text-ink-400">
                CMS {r.cmsVersion ?? "unknown"} · PHP {r.phpVersion ?? "unknown"}
                {r.fixedIn ? ` · fixed in ${r.fixedIn}` : ""}
              </p>

              <form action={updateReport} className="mt-3 flex flex-wrap items-end gap-2">
                <input type="hidden" name="id" value={r.id} />
                <label className="text-xs">
                  <span className="mb-1 block text-ink-500">Status</span>
                  <select name="status" defaultValue={r.status} className="field !py-1.5 text-sm">
                    {["open", "investigating", "resolved", "closed"].map((s) => (
                      <option key={s}>{s}</option>
                    ))}
                  </select>
                </label>
                <label className="text-xs">
                  <span className="mb-1 block text-ink-500">Severity</span>
                  <select name="severity" defaultValue={r.severity} className="field !py-1.5 text-sm">
                    {["low", "normal", "high", "critical"].map((s) => (
                      <option key={s}>{s}</option>
                    ))}
                  </select>
                </label>
                <label className="text-xs">
                  <span className="mb-1 block text-ink-500">Fixed in</span>
                  <input
                    name="fixedIn"
                    defaultValue={r.fixedIn ?? ""}
                    placeholder="1.4.0"
                    className="field !py-1.5 max-w-[8rem] text-sm"
                  />
                </label>
                <button className="btn-ghost">Save</button>
              </form>
            </article>
          ))}
        </div>
      )}
    </>
  );
}
