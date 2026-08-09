// Shared chrome for the signed-out screens (login, first-run setup).
//
// Kept out of the (app) layout deliberately: a sign-in page that renders the
// navigation it exists to guard looks broken, and leaks the section names to
// anyone who cannot get in.

export function AuthCard({
  title,
  subtitle,
  action,
  submit,
  children,
}: {
  title: string;
  subtitle: string;
  action: (formData: FormData) => Promise<void>;
  submit: string;
  children: React.ReactNode;
}) {
  return (
    <main className="grid min-h-screen place-items-center px-4 py-10">
      <form action={action} className="card w-full max-w-sm p-7">
        <div className="mb-6 flex items-center gap-2.5">
          <span className="grid h-8 w-8 place-items-center rounded-lg bg-brand-600 text-sm font-bold text-white">
            A
          </span>
          <span className="text-sm font-semibold leading-tight">
            {title}
            <span className="block text-[11px] font-normal text-ink-400">{subtitle}</span>
          </span>
        </div>

        <div className="space-y-3">{children}</div>

        <button type="submit" className="btn-primary mt-5 w-full">
          {submit}
        </button>
      </form>
    </main>
  );
}

export function AuthField({
  label,
  name,
  type = "text",
  autoComplete,
  autoFocus,
  hint,
  defaultValue,
}: {
  label: string;
  name: string;
  type?: string;
  autoComplete?: string;
  autoFocus?: boolean;
  hint?: string;
  defaultValue?: string;
}) {
  return (
    <label className="block text-sm">
      <span className="mb-1 block text-ink-500">{label}</span>
      <input
        name={name}
        type={type}
        required
        autoComplete={autoComplete}
        autoFocus={autoFocus}
        defaultValue={defaultValue}
        className="field"
      />
      {hint && <span className="mt-1 block text-xs text-ink-400">{hint}</span>}
    </label>
  );
}

export function AuthError({ children }: { children: React.ReactNode }) {
  return <p className="mt-1 text-sm text-red-600">{children}</p>;
}
