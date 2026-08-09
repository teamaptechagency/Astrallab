import { db } from "@/lib/db";
import { requireOperator } from "@/lib/operator-session";
import { PageHeader, Table, Td, Badge, when } from "@/components/ui";
import { ROLE_LABELS, type Role } from "@/lib/roles";
import { updateProfile, changePassword, endOtherSessions } from "./actions";

export const dynamic = "force-dynamic";

export default async function ProfilePage({
  searchParams,
}: {
  searchParams: Promise<{ saved?: string; error?: string }>;
}) {
  const me = await requireOperator();
  const { saved, error } = await searchParams;

  const sessions = await db.operatorSession.findMany({
    where: { operatorId: me.id, revokedAt: null, expiresAt: { gt: new Date() } },
    orderBy: { lastSeenAt: "desc" },
  });

  return (
    <>
      <PageHeader title="Profile" subtitle={ROLE_LABELS[me.role as Role] ?? me.role} />

      {saved && (
        <p className="mb-4 rounded-lg border border-brand-200 bg-brand-50 px-3 py-2 text-sm text-brand-800 dark:border-brand-900 dark:bg-brand-900/20 dark:text-brand-400">
          Saved.
        </p>
      )}
      {error && (
        <p className="mb-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/30 dark:text-red-400">
          {error}
        </p>
      )}

      <section className="card mb-5 p-5">
        <h2 className="font-medium">Details</h2>
        <form action={updateProfile} className="mt-4 grid max-w-lg gap-3 sm:grid-cols-2">
          <label className="text-sm">
            <span className="mb-1 block text-ink-500">Name</span>
            <input name="name" defaultValue={me.name} required className="field" />
          </label>
          <label className="text-sm">
            <span className="mb-1 block text-ink-500">Email</span>
            <input name="email" type="email" defaultValue={me.email} required className="field" />
          </label>
          <div className="sm:col-span-2">
            <button className="btn-primary">Save</button>
          </div>
        </form>
        <p className="mt-3 text-xs text-ink-400">
          Your role is set by an owner on the Team page, not here.
        </p>
      </section>

      <section className="card mb-5 p-5">
        <h2 className="font-medium">Password</h2>
        <form action={changePassword} className="mt-4 grid max-w-lg gap-3 sm:grid-cols-2">
          <label className="text-sm sm:col-span-2">
            <span className="mb-1 block text-ink-500">Current password</span>
            <input name="current" type="password" required autoComplete="current-password" className="field" />
          </label>
          <label className="text-sm sm:col-span-2">
            <span className="mb-1 block text-ink-500">New password</span>
            <input name="next" type="password" required autoComplete="new-password" className="field" />
            <span className="mt-1 block text-xs text-ink-400">
              At least 10 characters. Changing it signs out your other devices.
            </span>
          </label>
          <div className="sm:col-span-2">
            <button className="btn-primary">Change password</button>
          </div>
        </form>
      </section>

      <section className="card p-5">
        <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
          <h2 className="font-medium">Signed-in devices</h2>
          {sessions.length > 1 && (
            <form action={endOtherSessions}>
              <button className="btn-ghost text-xs">Sign out other devices</button>
            </form>
          )}
        </div>
        <Table head={["Device", "IP", "Started", "Last seen"]}>
          {sessions.map((s) => (
            <tr key={s.id}>
              <Td>
                <span className="block max-w-xs truncate text-xs">{s.userAgent ?? "unknown"}</span>
              </Td>
              <Td mono>{s.ip ?? "—"}</Td>
              <Td mono>{when(s.createdAt)}</Td>
              <Td mono>{when(s.lastSeenAt)}</Td>
            </tr>
          ))}
        </Table>
        <p className="mt-3 text-xs text-ink-400">
          Sessions are stored server-side, so signing one out ends it immediately rather than
          waiting for it to expire.
        </p>
      </section>

      <div className="mt-5 lg:hidden">
        <form action="/api/admin/logout" method="post">
          <button className="btn-ghost w-full">
            Sign out <Badge value={me.role} />
          </button>
        </form>
      </div>
    </>
  );
}
