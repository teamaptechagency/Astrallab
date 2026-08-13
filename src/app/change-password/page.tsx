import { redirect } from "next/navigation";
import { db } from "@/lib/db";
import { hashPassword, passwordProblem, verifyPassword } from "@/lib/password";
import { getOperator, OPERATOR_COOKIE } from "@/lib/operator-session";
import { cookies } from "next/headers";
import { AuthCard, AuthField, AuthError } from "@/components/auth-card";

export const dynamic = "force-dynamic";

/**
 * Replacing a password somebody else set.
 *
 * This is the only page reachable while mustChangePassword is true —
 * requireOperator sends every other one here — so it deliberately does not call
 * requireOperator itself, or it would redirect to itself forever.
 *
 * The point of the whole flow: a temporary password read out on a call or sent
 * over WhatsApp stops working the moment it has been used once. Without this it
 * lives in that chat for as long as the account does.
 */
async function change(formData: FormData) {
  "use server";

  const operator = await getOperator();
  if (!operator) redirect("/login");

  const current = String(formData.get("current") ?? "");
  const next = String(formData.get("password") ?? "");
  const again = String(formData.get("confirm") ?? "");

  const row = await db.operator.findUnique({ where: { id: operator.id } });
  if (!row) redirect("/login");

  // The temporary password is still required. Otherwise anyone who found an
  // unlocked laptop mid-handover could take the account without knowing it.
  if (!(await verifyPassword(current, row.passwordHash))) {
    redirect("/change-password?error=current");
  }

  if (next !== again) redirect("/change-password?error=match");

  const problem = passwordProblem(next);
  if (problem) redirect(`/change-password?error=weak`);

  // Refusing to let somebody "change" it back to the one they were handed.
  if (await verifyPassword(next, row.passwordHash)) {
    redirect("/change-password?error=same");
  }

  await db.operator.update({
    where: { id: operator.id },
    data: { passwordHash: await hashPassword(next), mustChangePassword: false },
  });

  // Every session for this operator ends, including this one. If the temporary
  // password reached somebody it was not meant for, this is the moment they are
  // put out — and signing in once more with the new password is a small price
  // for being certain of that.
  await db.operatorSession.updateMany({
    where: { operatorId: operator.id, revokedAt: null },
    data: { revokedAt: new Date() },
  });

  const store = await cookies();
  store.delete(OPERATOR_COOKIE);

  redirect("/login?changed=1");
}

const MESSAGES: Record<string, string> = {
  current: "That is not your current password.",
  match: "The two new passwords do not match.",
  weak: "Use at least 10 characters, and not digits alone.",
  same: "Choose something other than the password you were given.",
};

export default async function ChangePasswordPage({
  searchParams,
}: {
  searchParams: Promise<{ error?: string }>;
}) {
  const operator = await getOperator();
  if (!operator) redirect("/login");

  // Somebody who has already chosen their own password has no business here.
  if (!operator.mustChangePassword) redirect("/");

  const { error } = await searchParams;

  return (
    <AuthCard
      title="Choose your own password"
      subtitle={`Signed in as ${operator.email}`}
      action={change}
      submit="Set it"
    >
      <p className="text-sm text-neutral-500">
        The password you signed in with was set by somebody else. It stops
        working as soon as you replace it here.
      </p>

      {error ? <AuthError>{MESSAGES[error] ?? "That did not work."}</AuthError> : null}

      <AuthField
        label="The password you were given"
        name="current"
        type="password"
        autoComplete="current-password"
        autoFocus
      />
      <AuthField
        label="New password"
        name="password"
        type="password"
        autoComplete="new-password"
        hint="At least 10 characters. A phrase you will remember beats a short one you will not."
      />
      <AuthField
        label="New password again"
        name="confirm"
        type="password"
        autoComplete="new-password"
      />
    </AuthCard>
  );
}
