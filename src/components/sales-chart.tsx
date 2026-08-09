import type { Bucket } from "@/lib/sales";
import { money } from "@/components/ui";

// Revenue trend, drawn as plain SVG.
//
// No charting library: this is one bar series, and pulling in a dependency for
// it would cost more bundle than the whole page. It also stays a server
// component this way, so the data never ships to the browser twice.

export function SalesChart({ buckets }: { buckets: Bucket[] }) {
  const peak = Math.max(...buckets.map((b) => b.revenue), 0);

  if (peak === 0) {
    return (
      <div className="card grid h-48 place-items-center text-sm text-ink-400">
        No revenue recorded in this period.
      </div>
    );
  }

  // Label every bar when there are few, otherwise roughly eight, so an axis of
  // 30 days does not turn into unreadable overlapping text.
  const labelEvery = Math.max(1, Math.ceil(buckets.length / 8));

  return (
    <div className="card p-4">
      <div className="flex h-40 items-end gap-[3px]">
        {buckets.map((b, i) => (
          <div key={b.iso} className="group relative flex h-full flex-1 flex-col justify-end">
            <div
              // A hairline for empty buckets rather than nothing, so a quiet
              // day reads as "zero" instead of as missing data.
              style={{ height: b.revenue === 0 ? "2px" : `${Math.max(4, (b.revenue / peak) * 100)}%` }}
              className={
                b.revenue === 0
                  ? "w-full rounded-sm bg-ink-200 dark:bg-ink-800"
                  : "w-full rounded-sm bg-brand-500 transition group-hover:bg-brand-600"
              }
            />
            <span
              className="pointer-events-none absolute bottom-full left-1/2 z-10 mb-1 hidden -translate-x-1/2 whitespace-nowrap rounded-md bg-ink-900 px-2 py-1 text-[11px] text-white group-hover:block dark:bg-ink-100 dark:text-ink-900"
            >
              {b.iso} · {money(b.revenue)} · {b.orders} order{b.orders === 1 ? "" : "s"}
            </span>
            {i % labelEvery === 0 && (
              <span className="mt-1 block text-center text-[10px] text-ink-400">{b.label}</span>
            )}
          </div>
        ))}
      </div>
      <p className="mt-3 text-xs text-ink-400">Peak {money(peak)}. Hover a bar for detail.</p>
    </div>
  );
}
