"use client";

import { useState } from "react";

// A value with a copy button. These get pasted into WooCommerce settings and
// into CMS source, where a hand-retyped character is a bug that surfaces much
// later as "signature invalid".

export function CopyBox({
  label,
  value,
  multiline,
}: {
  label: string;
  value: string;
  multiline?: boolean;
}) {
  const [copied, setCopied] = useState(false);

  async function copy() {
    try {
      await navigator.clipboard.writeText(value);
      setCopied(true);
      setTimeout(() => setCopied(false), 1500);
    } catch {
      // Clipboard access is refused outside a secure context; the value is
      // selectable on screen either way, so this is not worth an error state.
    }
  }

  return (
    <div>
      <div className="mb-1 flex items-center justify-between gap-2">
        <span className="text-xs text-ink-500">{label}</span>
        <button type="button" onClick={copy} className="btn-ghost !px-2 !py-0.5 text-[11px]">
          {copied ? "Copied" : "Copy"}
        </button>
      </div>
      <code
        className={`block w-full overflow-x-auto rounded-lg border border-ink-200 bg-ink-50 px-3 py-2 text-xs dark:border-ink-800 dark:bg-ink-950 ${
          multiline ? "whitespace-pre" : "whitespace-nowrap"
        }`}
      >
        {value}
      </code>
    </div>
  );
}
