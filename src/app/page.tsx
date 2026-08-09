import { db } from "@/lib/db";
import { getAyojonState } from "@/lib/settings";

// Operator console. Read-only for now — enough to see whether the system is
// behaving (who activated, who was blocked, what versions are in the wild)
// before any of it is wired to a real store.

export const dynamic = "force-dynamic";

const STATUS_COLOUR: Record<string, string> = {
  unactivated: "#8b93a7",
  active: "#3ddc97",
  deactivated: "#f4b942",
  suspended: "#f4b942",
  revoked: "#ff6b6b",
};

export default async function ConsolePage() {
  const ayojon = await getAyojonState();
  const [licences, releases, events] = await Promise.all([
    db.licence.findMany({
      include: { product: true, activations: { where: { releasedAt: null } } },
      orderBy: { createdAt: "desc" },
      take: 50,
    }),
    db.release.findMany({ include: { product: true }, orderBy: { createdAt: "desc" }, take: 20 }),
    db.licenceEvent.findMany({ orderBy: { createdAt: "desc" }, take: 25 }),
  ]);

  const active = licences.filter((l) => l.status === "active").length;

  return (
    <main style={{ maxWidth: 1100, margin: "0 auto", padding: "40px 24px 80px" }}>
      <h1 style={{ fontSize: 24, fontWeight: 600, margin: 0 }}>manage.astralab</h1>
      <p style={{ color: "#8b93a7", marginTop: 6 }}>
        Licence, activation and update hub · {licences.length} licences · {active} active
      </p>

      <p
        style={{
          marginTop: 14,
          padding: "10px 14px",
          border: "1px solid #1e2740",
          borderRadius: 8,
          color: "#8b93a7",
          fontSize: 14,
        }}
      >
        Ayojon integration:{" "}
        <strong style={{ color: ayojon.status === "available" ? "#3ddc97" : "#f4b942" }}>
          {ayojon.status.replace("_", " ")}
        </strong>{" "}
        — every install reads this from the hub, so switching it here reaches all of them without
        an update.
      </p>

      <Section title="Licences">
        <Table head={["Key", "Product", "Customer", "Status", "Domains"]}>
          {licences.map((l) => (
            <tr key={l.id}>
              <Td mono>••••{l.keyLast4}</Td>
              <Td>{l.product.name}</Td>
              <Td>{l.customerEmail}</Td>
              <Td>
                <span style={{ color: STATUS_COLOUR[l.status] ?? "#e6e9f2" }}>{l.status}</span>
              </Td>
              <Td mono>{l.activations.map((a) => a.domain).join(", ") || "—"}</Td>
            </tr>
          ))}
          {licences.length === 0 && <EmptyRow span={5} text="No licences issued yet." />}
        </Table>
      </Section>

      <Section title="Releases">
        <Table head={["Version", "Product", "Severity", "Min upgrade from", "Published"]}>
          {releases.map((r) => (
            <tr key={r.id}>
              <Td mono>{r.version}</Td>
              <Td>{r.product.name}</Td>
              <Td>
                <span style={{ color: r.severity === "security" ? "#ff6b6b" : "#8b93a7" }}>
                  {r.severity}
                </span>
              </Td>
              <Td mono>{r.minUpgradeFrom ?? "—"}</Td>
              <Td>{r.published ? "yes" : "draft"}</Td>
            </tr>
          ))}
          {releases.length === 0 && <EmptyRow span={5} text="No releases published yet." />}
        </Table>
      </Section>

      <Section title="Recent activity">
        <Table head={["When", "Event", "Domain", "Detail"]}>
          {events.map((e) => (
            <tr key={e.id}>
              <Td mono>{e.createdAt.toISOString().slice(0, 19).replace("T", " ")}</Td>
              <Td>{e.kind}</Td>
              <Td mono>{e.domain ?? "—"}</Td>
              <Td>{e.detail ?? "—"}</Td>
            </tr>
          ))}
          {events.length === 0 && <EmptyRow span={4} text="Nothing has happened yet." />}
        </Table>
      </Section>
    </main>
  );
}

function Section({ title, children }: { title: string; children: React.ReactNode }) {
  return (
    <section style={{ marginTop: 36 }}>
      <h2 style={{ fontSize: 15, fontWeight: 600, color: "#b9c0d4", margin: "0 0 10px" }}>{title}</h2>
      {children}
    </section>
  );
}

function Table({ head, children }: { head: string[]; children: React.ReactNode }) {
  return (
    <div style={{ overflowX: "auto", border: "1px solid #1e2740", borderRadius: 10 }}>
      <table style={{ width: "100%", borderCollapse: "collapse", fontSize: 14 }}>
        <thead>
          <tr>
            {head.map((h) => (
              <th
                key={h}
                style={{
                  textAlign: "left",
                  padding: "10px 14px",
                  color: "#8b93a7",
                  fontWeight: 500,
                  borderBottom: "1px solid #1e2740",
                  whiteSpace: "nowrap",
                }}
              >
                {h}
              </th>
            ))}
          </tr>
        </thead>
        <tbody>{children}</tbody>
      </table>
    </div>
  );
}

function Td({ children, mono }: { children: React.ReactNode; mono?: boolean }) {
  return (
    <td
      style={{
        padding: "10px 14px",
        borderBottom: "1px solid #161d31",
        fontFamily: mono ? "ui-monospace, SFMono-Regular, Menlo, monospace" : undefined,
        whiteSpace: "nowrap",
      }}
    >
      {children}
    </td>
  );
}

function EmptyRow({ span, text }: { span: number; text: string }) {
  return (
    <tr>
      <td colSpan={span} style={{ padding: "14px", color: "#8b93a7" }}>
        {text}
      </td>
    </tr>
  );
}
