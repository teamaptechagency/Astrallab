import { cookies } from "next/headers";
import { redirect } from "next/navigation";
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
