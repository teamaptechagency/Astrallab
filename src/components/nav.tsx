"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";

// Two navigations from one definition.
//
// Desktop gets a persistent sidebar — there is room, and an operator moving
// between licences and support all day should never hunt for the way back.
// Mobile gets a fixed bottom tab bar, thumb-reachable, the way a native app
// behaves. A sidebar squeezed onto a phone, or tabs stretched across a
// monitor, would be worse than either.
//
// Only the primary destinations go in the mobile bar; the rest live behind
// "More", because five tabs is the practical limit before targets get too
// small to hit reliably.

export interface NavItem {
  href: string;
  label: string;
  icon: string;
  primary?: boolean;
}

// "Products" means what Astra Lab sells. Data synced up from customer shops
// lives under "Shop data" — the two were both called products at first, which
// made every conversation about them ambiguous.
export const NAV_ITEMS: NavItem[] = [
  { href: "/", label: "Dashboard", icon: "home", primary: true },
  { href: "/licences", label: "Licences", icon: "key", primary: true },
  { href: "/releases", label: "Releases", icon: "package", primary: true },
  { href: "/support", label: "Support", icon: "life-buoy", primary: true },
  { href: "/products", label: "Products", icon: "grid" },
  { href: "/shop-data", label: "Shop data", icon: "store" },
  { href: "/leads", label: "Leads", icon: "users" },
  { href: "/sales", label: "Sales", icon: "chart" },
  { href: "/finance", label: "Finance", icon: "wallet" },
  { href: "/team", label: "Team", icon: "users" },
  { href: "/api-config", label: "API config", icon: "plug" },
  { href: "/settings", label: "Settings", icon: "settings" },
];

function isActive(pathname: string, href: string): boolean {
  if (href === "/") return pathname === "/";
  return pathname === href || pathname.startsWith(`${href}/`);
}

function Icon({ name, className = "" }: { name: string; className?: string }) {
  const paths: Record<string, string> = {
    home: "M3 10.5 12 3l9 7.5M5.25 9.75V20a1 1 0 0 0 1 1h11.5a1 1 0 0 0 1-1V9.75",
    key: "M15.5 7.5a4 4 0 1 1-3.4 6.1L4 21.7v-3.2h3.2v-3.2h3.2l1.7-1.7A4 4 0 0 1 15.5 7.5Z",
    package: "M12 3 4 7v10l8 4 8-4V7l-8-4Zm0 0v18M4 7l8 4 8-4",
    "life-buoy": "M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Zm0-5.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Zm2.5-6 3-3m-9 9-3 3m9 0 3 3m-9-9-3-3",
    grid: "M4 4h7v7H4V4Zm9 0h7v7h-7V4ZM4 13h7v7H4v-7Zm9 0h7v7h-7v-7Z",
    store: "M3 9.5 4.5 4h15L21 9.5M3 9.5h18M3 9.5a2.5 2.5 0 0 0 5 0 2.5 2.5 0 0 0 5 0 2.5 2.5 0 0 0 5 0 2.5 2.5 0 0 0 3 0M5 12v8h14v-8",
    plug: "M9 3v6m6-6v6M6 9h12v3a6 6 0 0 1-12 0V9Zm6 9v3",
    chart: "M4 20V10m5 10V4m5 16v-7m5 7V8",
    users: "M16 20v-1.5a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4V20M9.5 10.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7ZM21 20v-1.5a4 4 0 0 0-3-3.87M16.5 3.6a4 4 0 0 1 0 7.75",
    wallet: "M3 8.5A2.5 2.5 0 0 1 5.5 6H19a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5.5A2.5 2.5 0 0 1 3 16.5v-8Zm0 0A2.5 2.5 0 0 1 5.5 6H18M16.5 12.5h.01",
    settings:
      "M12 15.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Zm8-3.5a8 8 0 0 0-.14-1.5l2.1-1.6-2-3.46-2.48 1a8 8 0 0 0-2.6-1.5L14.5 2h-4l-.38 2.94a8 8 0 0 0-2.6 1.5l-2.48-1-2 3.46 2.1 1.6a8.1 8.1 0 0 0 0 3l-2.1 1.6 2 3.46 2.48-1a8 8 0 0 0 2.6 1.5L10.5 22h4l.38-2.94a8 8 0 0 0 2.6-1.5l2.48 1 2-3.46-2.1-1.6c.09-.49.14-.99.14-1.5Z",
  };

  return (
    <svg
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="1.6"
      strokeLinecap="round"
      strokeLinejoin="round"
      aria-hidden="true"
      className={className}
    >
      <path d={paths[name] ?? paths.home} />
    </svg>
  );
}

export function Sidebar({
  operator,
  allowed,
}: {
  operator: { name: string; email: string; role: string };
  allowed: string[];
}) {
  const pathname = usePathname();
  const items = NAV_ITEMS.filter((i) => allowed.includes(i.href));

  return (
    <aside className="hidden w-60 shrink-0 border-r border-ink-200 bg-white lg:flex lg:flex-col dark:border-ink-800 dark:bg-ink-900">
      <div className="flex h-16 items-center gap-2.5 px-5">
        <span className="grid h-8 w-8 place-items-center rounded-lg bg-brand-600 text-sm font-bold text-white">
          A
        </span>
        <span className="text-sm font-semibold leading-tight">
          manage
          <span className="block text-[11px] font-normal text-ink-400">astralab.com</span>
        </span>
      </div>

      <nav className="flex-1 space-y-0.5 px-3 py-2">
        {items.map((item) => {
          const active = isActive(pathname, item.href);
          return (
            <Link
              key={item.href}
              href={item.href}
              aria-current={active ? "page" : undefined}
              className={`flex items-center gap-3 rounded-lg px-3 py-2 text-sm transition ${
                active
                  ? "bg-brand-50 font-medium text-brand-700 dark:bg-brand-900/30 dark:text-brand-400"
                  : "text-ink-600 hover:bg-ink-50 dark:text-ink-300 dark:hover:bg-ink-800"
              }`}
            >
              <Icon name={item.icon} className="h-[18px] w-[18px]" />
              {item.label}
            </Link>
          );
        })}
      </nav>

      <div className="border-t border-ink-200 p-3 dark:border-ink-800">
        <Link
          href="/profile"
          className="mb-2 block rounded-lg px-3 py-2 text-sm hover:bg-ink-50 dark:hover:bg-ink-800"
        >
          <span className="block truncate font-medium">{operator.name}</span>
          <span className="block truncate text-[11px] text-ink-400">
            {operator.email} · {operator.role}
          </span>
        </Link>
        <form action="/api/admin/logout" method="post">
          <button type="submit" className="btn-ghost w-full">
            Sign out
          </button>
        </form>
      </div>
    </aside>
  );
}

export function MobileTabs({ allowed }: { allowed: string[] }) {
  const pathname = usePathname();
  const primary = NAV_ITEMS.filter((i) => i.primary && allowed.includes(i.href));
  const moreActive = NAV_ITEMS.some((i) => !i.primary && isActive(pathname, i.href));

  return (
    <nav
      className="fixed inset-x-0 bottom-0 z-40 grid border-t border-ink-200 bg-white/95 backdrop-blur lg:hidden dark:border-ink-800 dark:bg-ink-900/95"
      // Keeps the bar clear of the home indicator on iOS.
      style={{
        // Column count follows the tabs actually shown — a support account
        // sees fewer, and a fixed five-column grid would leave dead space.
        gridTemplateColumns: `repeat(${primary.length + 1}, minmax(0, 1fr))`,
        // Keeps the bar clear of the home indicator on iOS.
        paddingBottom: "env(safe-area-inset-bottom)",
      }}
    >
      {primary.map((item) => {
        const active = isActive(pathname, item.href);
        return (
          <Link
            key={item.href}
            href={item.href}
            aria-current={active ? "page" : undefined}
            className={`flex flex-col items-center gap-1 py-2.5 text-[11px] ${
              active ? "text-brand-600 dark:text-brand-400" : "text-ink-400"
            }`}
          >
            <Icon name={item.icon} className="h-[22px] w-[22px]" />
            {item.label}
          </Link>
        );
      })}
      <Link
        href="/more"
        className={`flex flex-col items-center gap-1 py-2.5 text-[11px] ${
          moreActive ? "text-brand-600 dark:text-brand-400" : "text-ink-400"
        }`}
      >
        <Icon name="grid" className="h-[22px] w-[22px]" />
        More
      </Link>
    </nav>
  );
}

export function MobileHeader() {
  const pathname = usePathname();
  const current = NAV_ITEMS.find((i) => isActive(pathname, i.href));

  return (
    <header className="sticky top-0 z-30 flex h-14 items-center gap-3 border-b border-ink-200 bg-white/95 px-4 backdrop-blur lg:hidden dark:border-ink-800 dark:bg-ink-900/95">
      <span className="grid h-7 w-7 place-items-center rounded-md bg-brand-600 text-xs font-bold text-white">
        A
      </span>
      <span className="text-sm font-semibold">{current?.label ?? "manage.astralab"}</span>
    </header>
  );
}
