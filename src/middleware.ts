import { NextResponse } from "next/server";
import type { NextRequest } from "next/server";
import { isValidAdminCookie, ADMIN_COOKIE } from "@/lib/admin-auth";

// Gate the operator console.
//
// The /api/v1 routes are deliberately NOT covered: they authenticate
// themselves, each in the way appropriate to its caller — an HMAC from the
// store, a licence key from an install. Putting an admin cookie check in front
// of them would lock out every customer site.
//
// Everything else is the console, which lists customers, emails and licences,
// and must never be publicly readable.

export async function middleware(request: NextRequest) {
  const { pathname } = request.nextUrl;

  if (pathname.startsWith("/api/")) return NextResponse.next();
  if (pathname === "/login") return NextResponse.next();

  if (!(await isValidAdminCookie(request.cookies.get(ADMIN_COOKIE)?.value))) {
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
