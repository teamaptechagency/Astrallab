import { PageHeader, CardLink } from "@/components/ui";

// Mobile-only overflow. The bottom bar holds four destinations plus this —
// past five, tab targets get too small to hit reliably on a phone.
export default function MorePage() {
  return (
    <>
      <PageHeader title="More" subtitle="Everything not on the tab bar" />

      <div className="grid gap-3 sm:grid-cols-2">
        <CardLink href="/products" title="Products" body="What Astra Lab sells, and their licences." />
        <CardLink href="/shop-data" title="Shop data" body="Catalogues synced from customer shops." />
        <CardLink href="/leads" title="Leads" body="Enquiries captured on customer shops." />
        <CardLink href="/finance" title="Finance" body="Earnings, expenses and net position." />
        <CardLink href="/settings" title="Settings" body="Ayojon integration and hub switches." />
      </div>

      <form action="/api/admin/logout" method="post" className="mt-5">
        <button className="btn-ghost w-full">Sign out</button>
      </form>
    </>
  );
}
