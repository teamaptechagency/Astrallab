import { NextResponse } from "next/server";
import { revokeCurrentSession } from "@/lib/operator-session";

// POST /api/admin/logout — end this session.
//
// Revokes the session row as well as clearing the cookie, so the token is dead
// everywhere rather than merely hidden from this browser.
export async function POST(request: Request) {
  await revokeCurrentSession();
  return NextResponse.redirect(new URL("/login", request.url), { status: 303 });
}
