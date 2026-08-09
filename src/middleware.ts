import { NextResponse } from "next/server";
import type { NextRequest } from "next/server";

const OPERATOR_COOKIE = "astralab_session";

// A cheap gate, not the authorisation check.
//
// Middleware runs on the Edge runtime with no database, so it cannot tell a
// valid session token from a forged one — it only sees whether a cookie is
// present. The real check is getOperator() in the (app) layout, which resolves
// the token against the database and enforces the role on every page. This
// exists so a signed-out visitor gets the login screen instead of a flash of
// layout followed by a redirect.
//
// /api is excluded deliberately: those routes authenticate their own callers —
// an HMAC from the store, a licence key from an install — and none of them
// carry an operator cookie.

export function middleware(request: NextRequest) {
  const { pathname } = request.nextUrl;

  if (pathname.startsWith("/api/")) return NextResponse.next();
  if (pathname === "/login" || pathname === "/setup") return NextResponse.next();

  if (!request.cookies.has(OPERATOR_COOKIE)) {
    const url = request.nextUrl.clone();
    url.pathname = "/login";
    url.search = "";
    return NextResponse.redirect(url);
  }

  return NextResponse.next();
}

export const config = {
  matcher: ["/((?!_next/static|_next/image|favicon.ico).*)"],
};
