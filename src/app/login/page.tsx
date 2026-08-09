import { redirect } from "next/navigation";
import { cookies } from "next/headers";
import { checkPassword, issueAdminCookie, isValidAdminCookie, ADMIN_COOKIE } from "@/lib/admin-auth";

export const dynamic = "force-dynamic";

async function signIn(formData: FormData) {
  "use server";

  const submitted = String(formData.get("password") ?? "");

  if (!(await checkPassword(submitted))) {
    // No detail about why. "Wrong password" and "no password configured" look
    // identical from outside.
    redirect("/login?error=1");
  }

  const { value, expires } = await issueAdminCookie();
  const store = await cookies();
  store.set(ADMIN_COOKIE, value, {
    httpOnly: true,
    sameSite: "lax",
    secure: process.env.NODE_ENV === "production",
    path: "/",
    expires,
  });

  redirect("/");
}

export default async function LoginPage({
  searchParams,
}: {
  searchParams: Promise<{ error?: string }>;
}) {
  const store = await cookies();
  if (await isValidAdminCookie(store.get(ADMIN_COOKIE)?.value)) redirect("/");
  const { error } = await searchParams;

  return (
    <main
      style={{
        minHeight: "100vh",
        display: "grid",
        placeItems: "center",
        padding: 24,
      }}
    >
      <form
        action={signIn}
        style={{
          width: "100%",
          maxWidth: 360,
          border: "1px solid #1e2740",
          borderRadius: 12,
          padding: 28,
        }}
      >
        <h1 style={{ fontSize: 18, fontWeight: 600, margin: 0 }}>manage.astralab</h1>
        <p style={{ color: "#8b93a7", fontSize: 14, marginTop: 6 }}>Operator console</p>

        <label
          htmlFor="password"
          style={{ display: "block", marginTop: 22, fontSize: 13, color: "#b9c0d4" }}
        >
          Password
        </label>
        <input
          id="password"
          name="password"
          type="password"
          autoFocus
          autoComplete="current-password"
          style={{
            width: "100%",
            boxSizing: "border-box",
            marginTop: 6,
            padding: "10px 12px",
            borderRadius: 8,
            border: "1px solid #2a3354",
            background: "#0f1526",
            color: "#e6e9f2",
            fontSize: 15,
          }}
        />

        {error && (
          <p style={{ color: "#ff6b6b", fontSize: 13, marginTop: 10, marginBottom: 0 }}>
            Incorrect password.
          </p>
        )}

        <button
          type="submit"
          style={{
            width: "100%",
            marginTop: 18,
            padding: "10px 12px",
            borderRadius: 8,
            border: "none",
            background: "#3ddc97",
            color: "#06241a",
            fontSize: 15,
            fontWeight: 600,
            cursor: "pointer",
          }}
        >
          Sign in
        </button>
      </form>
    </main>
  );
}
