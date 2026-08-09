import Link from "next/link";

// Shared presentation pieces. Every page is built from these so the CMS reads
// as one product rather than eight separately-styled screens.

export function PageHeader({
  title,
  subtitle,
  action,
}: {
  title: string;
  subtitle?: string;
  action?: React.ReactNode;
}) {
  return (
    <div className="mb-6 flex flex-wrap items-end justify-between gap-3">
      <div>
        <h1 className="text-xl font-semibold tracking-tight">{title}</h1>
        {subtitle && <p className="mt-1 text-sm text-ink-500">{subtitle}</p>}
      </div>
      {action}
    </div>
  );
}

export function StatCard({
  label,
  value,
  hint,
  tone = "default",
}: {
  label: string;
  value: string | number;
  hint?: string;
  tone?: "default" | "positive" | "warning" | "danger";
}) {
  const toneClass = {
    default: "text-ink-900 dark:text-ink-100",
    positive: "text-brand-600 dark:text-brand-400",
    warning: "text-amber-600 dark:text-amber-400",
    danger: "text-red-600 dark:text-red-400",
  }[tone];

  return (
    <div className="card p-4">
      <p className="text-xs font-medium uppercase tracking-wide text-ink-400">{label}</p>
      <p className={`tabular mt-1.5 text-2xl font-semibold ${toneClass}`}>{value}</p>
      {hint && <p className="mt-1 text-xs text-ink-400">{hint}</p>}
    </div>
  );
}

const BADGE_TONES: Record<string, string> = {
  active: "bg-brand-50 text-brand-700 dark:bg-brand-900/30 dark:text-brand-400",
  unactivated: "bg-ink-100 text-ink-600 dark:bg-ink-800 dark:text-ink-300",
  deactivated: "bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400",
  suspended: "bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400",
  revoked: "bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-400",
  open: "bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400",
  investigating: "bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400",
  resolved: "bg-brand-50 text-brand-700 dark:bg-brand-900/30 dark:text-brand-400",
  closed: "bg-ink-100 text-ink-600 dark:bg-ink-800 dark:text-ink-300",
  critical: "bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-400",
  high: "bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400",
  security: "bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-400",
  income: "bg-brand-50 text-brand-700 dark:bg-brand-900/30 dark:text-brand-400",
  expense: "bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400",
};

export function Badge({ value }: { value: string }) {
  const tone = BADGE_TONES[value] ?? "bg-ink-100 text-ink-600 dark:bg-ink-800 dark:text-ink-300";
  return (
    <span className={`inline-flex rounded-full px-2 py-0.5 text-xs font-medium ${tone}`}>
      {value.replace(/_/g, " ")}
    </span>
  );
}

/**
 * Tables scroll inside their own container rather than pushing the page wide —
 * on a phone, a licence table with six columns would otherwise make the whole
 * layout scroll sideways.
 */
export function Table({ head, children }: { head: string[]; children: React.ReactNode }) {
  return (
    <div className="card overflow-x-auto">
      <table className="w-full min-w-[560px] text-sm">
        <thead>
          <tr className="border-b border-ink-200 dark:border-ink-800">
            {head.map((h) => (
              <th
                key={h}
                className="whitespace-nowrap px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-ink-400"
              >
                {h}
              </th>
            ))}
          </tr>
        </thead>
        <tbody className="divide-y divide-ink-100 dark:divide-ink-800">{children}</tbody>
      </table>
    </div>
  );
}

export function Td({ children, mono }: { children: React.ReactNode; mono?: boolean }) {
  return (
    <td className={`whitespace-nowrap px-4 py-3 ${mono ? "tabular font-mono text-[13px]" : ""}`}>
      {children}
    </td>
  );
}

export function EmptyState({
  title,
  body,
  hint,
}: {
  title: string;
  body: string;
  hint?: string;
}) {
  return (
    <div className="card px-6 py-12 text-center">
      <p className="font-medium">{title}</p>
      <p className="mx-auto mt-1.5 max-w-md text-sm text-ink-500">{body}</p>
      {hint && <p className="mx-auto mt-3 max-w-md text-xs text-ink-400">{hint}</p>}
    </div>
  );
}

export function CardLink({
  href,
  title,
  body,
}: {
  href: string;
  title: string;
  body: string;
}) {
  return (
    <Link href={href} className="card block p-4 transition hover:border-brand-400">
      <p className="font-medium">{title}</p>
      <p className="mt-1 text-sm text-ink-500">{body}</p>
    </Link>
  );
}

export function money(amount: number, currency = "BDT"): string {
  return `${currency} ${amount.toLocaleString("en-US", { maximumFractionDigits: 0 })}`;
}

export function when(date: Date): string {
  return date.toISOString().slice(0, 16).replace("T", " ");
}
