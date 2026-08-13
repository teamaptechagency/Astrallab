import { createHmac, randomBytes } from "node:crypto";
import { cookies, headers } from "next/headers";
import { cache } from "react";
import { redirect } from "next/navigation";
import { NextResponse } from "next/server";
import { db } from "@/lib/db";
import { can, type Permission } from "@/lib/roles";

// Operator sessions.
//
// Same discipline as the licence keys this system already handles: the cookie
// holds a high-entropy random token, and only its HMAC is stored. A dump of
// OperatorSession yields nothing presentable as a cookie.
//
// Database-backed rather than a self-contained token so sessions are
// revocable — "sign out everywhere", deactivating someone, or a lost laptop
// all take effect on the very next request.

export const OPERATOR_COOKIE = "astralab_session";
const SESSION_TTL_MS = 12 * 60 * 60_000;
const TOUCH_INTERVAL_MS = 15 * 60_000;

function secret(): string {
  const value = process.env.ADMIN_SESSION_SECRET ?? process.env.STORE_API_SECRET;
  if (!value) throw new Error("ADMIN_SESSION_SECRET is not set.");
  return value;
}

function hashToken(token: string): string {
  return createHmac("sha256", secret()).update(token).digest("hex");
}

export async function createOperatorSession(operatorId: string): Promise<void> {
  const token = randomBytes(32).toString("base64url");
  const expiresAt = new Date(Date.now() + SESSION_TTL_MS);
  const h = await headers();

  await db.operatorSession.create({
    data: {
      tokenHash: hashToken(token),
      operatorId,
      expiresAt,
      userAgent: h.get("user-agent")?.slice(0, 300) ?? null,
      ip: h.get("x-forwarded-for")?.split(",")[0]?.trim() ?? null,
    },
  });

  await db.operator.update({ where: { id: operatorId }, data: { lastLoginAt: new Date() } });

  const store = await cookies();
  store.set(OPERATOR_COOKIE, token, {
    httpOnly: true,
    sameSite: "lax",
    secure: process.env.NODE_ENV === "production",
    path: "/",
    expires: expiresAt,
  });
}

export interface CurrentOperator {
  id: string;
  name: string;
  email: string;
  role: string;
  /** True while signed in with a password somebody else set. */
  mustChangePassword: boolean;
}

/**
 * The signed-in operator, or null.
 *
 * cache() dedupes per request — the layout, the nav and the page each ask, and
 * without it that is three round-trips for one answer.
 */
export const getOperator = cache(async (): Promise<CurrentOperator | null> => {
  const store = await cookies();
  const token = store.get(OPERATOR_COOKIE)?.value;
  if (!token) return null;

  const session = await db.operatorSession.findUnique({
    where: { tokenHash: hashToken(token) },
    include: { operator: true },
  });

  if (!session || session.revokedAt) return null;
  if (session.expiresAt.getTime() <= Date.now()) return null;
  // A deactivated account must lose access immediately, without needing
  // someone to remember to also kill their sessions.
  if (!session.operator.active) return null;

  if (Date.now() - session.lastSeenAt.getTime() > TOUCH_INTERVAL_MS) {
    await db.operatorSession
      .update({ where: { id: session.id }, data: { lastSeenAt: new Date() } })
      .catch(() => {});
  }

  const { id, name, email, role, mustChangePassword } = session.operator;
  return { id, name, email, role, mustChangePassword };
});

export async function requireOperator(): Promise<CurrentOperator> {
  const operator = await getOperator();
  if (!operator) redirect("/login");

  // A temporary password opens exactly one door: the screen that replaces it.
  // Enforced here rather than in the layout, because a check in the layout
  // guards what is rendered and not what a server action will do — and the
  // actions are the part that matter.
  if (operator.mustChangePassword) redirect("/change-password");

  return operator;
}

/**
 * Guard a server action by permission.
 *
 * Actions are POST endpoints; hiding a button does not stop anyone aiming at
 * one. Every action that changes something calls this, not the UI.
 */
export async function requirePermission(permission: Permission): Promise<CurrentOperator> {
  const operator = await requireOperator();
  if (!can(operator.role, permission)) redirect("/?denied=1");
  return operator;
}

/** Same check for operator API routes, which need a status rather than HTML. */
export async function requireApiPermission(
  permission: Permission,
): Promise<{ operator: CurrentOperator } | { denied: NextResponse }> {
  const operator = await getOperator();
  if (!operator) {
    return { denied: NextResponse.json({ error: "unauthorised" }, { status: 401 }) };
  }
  if (!can(operator.role, permission)) {
    return { denied: NextResponse.json({ error: "forbidden" }, { status: 403 }) };
  }
  return { operator };
}

export async function revokeCurrentSession(): Promise<void> {
  const store = await cookies();
  const token = store.get(OPERATOR_COOKIE)?.value;
  if (token) {
    await db.operatorSession
      .updateMany({ where: { tokenHash: hashToken(token), revokedAt: null }, data: { revokedAt: new Date() } })
      .catch(() => {});
  }
  store.delete(OPERATOR_COOKIE);
}

export async function revokeAllSessions(operatorId: string): Promise<void> {
  await db.operatorSession.updateMany({
    where: { operatorId, revokedAt: null },
    data: { revokedAt: new Date() },
  });
}
