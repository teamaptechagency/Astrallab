import { NextResponse } from "next/server";
import { ADMIN_COOKIE } from "@/lib/admin-auth";

// POST /api/admin/logout — clear the operator session.
//
// A route rather than a server action so the sign-out control can be a plain
// form in the sidebar, which renders on every page without making the whole
// navigation a client component.
export async function POST(request: Request) {
  const response = NextResponse.redirect(new URL("/login", request.url), { status: 303 });
  response.cookies.set(ADMIN_COOKIE, "", { path: "/", maxAge: 0 });
  return response;
}
