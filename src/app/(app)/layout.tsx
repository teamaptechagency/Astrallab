import { redirect } from "next/navigation";
import { db } from "@/lib/db";
import { getOperator } from "@/lib/operator-session";
import { Sidebar, MobileTabs, MobileHeader } from "@/components/nav";
import { visibleRoutes } from "@/lib/roles";

// The authenticated shell.
//
// This is where authorisation actually happens — middleware only checked that
// a cookie exists. Resolving the operator here means every page under (app)
// is gated by one check that cannot be forgotten on a new page.
export default async function AppLayout({ children }: { children: React.ReactNode }) {
  const operator = await getOperator();

  if (!operator) {
    // No accounts at all means a fresh install: send them to create the first
    // owner rather than to a login screen nobody can pass.
    const count = await db.operator.count();
    redirect(count === 0 ? "/setup" : "/login");
  }

  const allowed = visibleRoutes(operator.role);

  return (
    <>
      <div className="flex min-h-screen">
        <Sidebar operator={operator} allowed={allowed} />
        <div className="flex min-w-0 flex-1 flex-col">
          <MobileHeader />
          {/* Bottom padding clears the fixed mobile tab bar. */}
          <main className="flex-1 px-4 py-5 pb-24 sm:px-6 lg:px-8 lg:py-8 lg:pb-8">
            <div className="mx-auto w-full max-w-6xl">{children}</div>
          </main>
        </div>
      </div>
      <MobileTabs allowed={allowed} />
    </>
  );
}
