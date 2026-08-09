import { Sidebar, MobileTabs, MobileHeader } from "@/components/nav";

// The authenticated shell: sidebar on desktop, app-style bottom tabs on mobile.
export default function AppLayout({ children }: { children: React.ReactNode }) {
  return (
    <>
      <div className="flex min-h-screen">
        <Sidebar />
        <div className="flex min-w-0 flex-1 flex-col">
          <MobileHeader />
          {/* Bottom padding clears the fixed mobile tab bar. */}
          <main className="flex-1 px-4 py-5 pb-24 sm:px-6 lg:px-8 lg:py-8 lg:pb-8">
            <div className="mx-auto w-full max-w-6xl">{children}</div>
          </main>
        </div>
      </div>
      <MobileTabs />
    </>
  );
}
