import { db } from "@/lib/db";
import { requirePermission } from "@/lib/operator-session";
import { ROLES, ROLE_LABELS, type Role } from "@/lib/roles";
import { PageHeader, Table, Td, Badge, when } from "@/components/ui";
import { addOperator, setOperatorRole, setOperatorActive, signOutEverywhere } from "./actions";

export const dynamic = "force-dynamic";

export default async function TeamPage() {
  const me = await requirePermission("team.manage");

  const operators = await db.operator.findMany({
    include: { _count: { select: { sessions: { where: { revokedAt: null } } } } },
    orderBy: [{ active: "desc" }, { createdAt: "asc" }],
  });

  const owners = operators.filter((o) => o.active && o.role === "owner").length;

  return (
    <>
      <PageHeader title="Team" subtitle="Who can use this console, and what they may do" />

      <details className="card mb-5 p-4">
        <summary className="cursor-pointer text-sm font-medium">Add someone</summary>
        <form action={addOperator} className="mt-4 grid gap-3 sm:grid-cols-2">
          <label className="text-sm">
            <span className="mb-1 block text-ink-500">Name</span>
            <input name="name" required className="field" />
          </label>
          <label className="text-sm">
            <span className="mb-1 block text-ink-500">Email</span>
            <input name="email" type="email" required className="field" />
          </label>
          <label className="text-sm">
            <span className="mb-1 block text-ink-500">Role</span>
            <select name="role" defaultValue="support" className="field">
              {ROLES.map((r) => (
                <option key={r} value={r}>
                  {r}
                </option>
              ))}
            </select>
          </label>
          <label className="text-sm">
            <span className="mb-1 block text-ink-500">Temporary password</span>
            <input name="password" type="text" required minLength={10} className="field" />
            <span className="mt-1 block text-xs text-ink-400">
              Give it to them directly. They can change it on their profile.
            </span>
          </label>
          <div className="sm:col-span-2">
            <button className="btn-primary">Add</button>
          </div>
        </form>
      </details>

      <Table head={["Name", "Role", "Sessions", "Last login", "State", ""]}>
        {operators.map((o) => {
          // The last active owner must keep their role and their access, or
          // nobody can manage the team again and the console needs a database
          // edit to recover.
          const lastOwner = o.active && o.role === "owner" && owners === 1;
          const isMe = o.id === me.id;

          return (
            <tr key={o.id}>
              <Td>
                <span className="block">
                  {o.name}
                  {isMe && <span className="ml-1 text-xs text-ink-400">(you)</span>}
                </span>
                <span className="text-xs text-ink-400">{o.email}</span>
              </Td>
              <Td>
                <form action={setOperatorRole} className="flex items-center gap-1.5">
                  <input type="hidden" name="id" value={o.id} />
                  <select
                    name="role"
                    defaultValue={o.role}
                    disabled={lastOwner}
                    title={lastOwner ? "The last owner cannot change their own role." : ROLE_LABELS[o.role as Role]}
                    className="field !py-1 !text-xs"
                  >
                    {ROLES.map((r) => (
                      <option key={r}>{r}</option>
                    ))}
                  </select>
                  {!lastOwner && <button className="btn-ghost !px-2 !py-1 text-xs">Save</button>}
                </form>
              </Td>
              <Td mono>{o._count.sessions}</Td>
              <Td mono>{o.lastLoginAt ? when(o.lastLoginAt) : "never"}</Td>
              <Td>{o.active ? <Badge value="active" /> : <Badge value="closed" />}</Td>
              <Td>
                <div className="flex gap-1.5">
                  {o._count.sessions > 0 && (
                    <form action={signOutEverywhere}>
                      <input type="hidden" name="id" value={o.id} />
                      <button className="btn-ghost !px-2 !py-1 text-xs">Sign out</button>
                    </form>
                  )}
                  {!lastOwner && !isMe && (
                    <form action={setOperatorActive}>
                      <input type="hidden" name="id" value={o.id} />
                      <input type="hidden" name="active" value={o.active ? "0" : "1"} />
                      <button className={o.active ? "btn-danger !px-2 !py-1 text-xs" : "btn-ghost !px-2 !py-1 text-xs"}>
                        {o.active ? "Deactivate" : "Reactivate"}
                      </button>
                    </form>
                  )}
                </div>
              </Td>
            </tr>
          );
        })}
      </Table>

      <div className="card mt-5 p-4 text-sm">
        <p className="font-medium">What each role may do</p>
        <ul className="mt-2 space-y-1.5 text-ink-500">
          {ROLES.map((r) => (
            <li key={r}>
              <span className="font-mono text-xs text-ink-700 dark:text-ink-200">{r}</span> —{" "}
              {ROLE_LABELS[r].split("— ")[1]}
            </li>
          ))}
        </ul>
        <p className="mt-3 text-xs text-ink-400">
          Roles are shaped around damage, not seniority. Support can suspend a licence — reversible,
          and sometimes needed during a payment dispute — but not revoke one, which takes a
          customer&apos;s storefront down for good.
        </p>
      </div>
    </>
  );
}
