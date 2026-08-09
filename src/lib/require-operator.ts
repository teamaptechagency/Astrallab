import { cookies } from "next/headers";
import { redirect } from "next/navigation";
import { NextResponse } from "next/server";
import { isValidAdminCookie, ADMIN_COOKIE } from "@/lib/admin-auth";

/**
 * Guard for operator server actions.
 *
 * Middleware already redirects unauthenticated page requests, but a server
 * action is a POST to an endpoint — it must verify for itself rather than
 * assuming the page that rendered the form was authorised. Without this,
 * anyone who knows an action's id could revoke licences.
 */
export async function requireOperator(): Promise<void> {
  const store = await cookies();
  if (!(await isValidAdminCookie(store.get(ADMIN_COOKIE)?.value))) {
    redirect("/login");
  }
}

/**
 * Same check for operator API routes.
 *
 * Returns a 401 response to send back, or null when the caller is authorised —
 * a redirect to an HTML login page is useless to a fetch() and would surface
 * as a confusing parse error rather than "you are not signed in".
 *
 * Note that middleware skips /api entirely, so these routes are unprotected
 * unless they call this.
 */
export async function requireOperatorApi(): Promise<NextResponse | null> {
  const store = await cookies();
  if (!(await isValidAdminCookie(store.get(ADMIN_COOKIE)?.value))) {
    return NextResponse.json({ error: "unauthorised" }, { status: 401 });
  }
  return null;
}
