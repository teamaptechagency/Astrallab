import { PageHeader, CardLink } from "@/components/ui";
import { requireOperator } from "@/lib/operator-session";
import { visibleRoutes } from "@/lib/roles";

const DESTINATIONS = [
  { href: "/products", title: "Products", body: "What Astra Lab sells, and their licences." },
  { href: "/shop-data", title: "Shop data", body: "Catalogues synced from customer shops." },
  { href: "/leads", title: "Leads", body: "Enquiries captured on customer shops." },
  { href: "/sales", title: "Sales", body: "Orders, revenue trend and export." },
  { href: "/finance", title: "Finance", body: "Earnings, expenses and net position." },
  { href: "/team", title: "Team", body: "Who can sign in, and what they may do." },
  { href: "/api-config", title: "API config", body: "Endpoints, secrets and the CMS public key." },
  { href: "/settings", title: "Settings", body: "Ayojon integration and hub switches." },
  { href: "/profile", title: "Profile", body: "Your details, password and devices." },
];

// Mobile-only overflow. The bottom bar holds four destinations plus this —
// past five, tab targets get too small to hit reliably on a phone.
export default async function MorePage() {
  const me = await requireOperator();
  const allowed = visibleRoutes(me.role);

  return (
    <>
      <PageHeader title="More" subtitle={`Signed in as ${me.name} · ${me.role}`} />

      <div className="grid gap-3 sm:grid-cols-2">
        {DESTINATIONS.filter((d) => allowed.includes(d.href)).map((d) => (
          <CardLink key={d.href} {...d} />
        ))}
      </div>

      <form action="/api/admin/logout" method="post" className="mt-5">
        <button className="btn-ghost w-full">Sign out</button>
      </form>
    </>
  );
}
