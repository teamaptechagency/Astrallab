import { redirect } from "next/navigation";
import { db } from "@/lib/db";
import { verifyPassword } from "@/lib/password";
import { createOperatorSession, getOperator } from "@/lib/operator-session";
import { AuthCard, AuthField, AuthError } from "@/components/auth-card";

export const dynamic = "force-dynamic";

async function signIn(formData: FormData) {
  "use server";

  const email = String(formData.get("email") ?? "").trim().toLowerCase();
  const password = String(formData.get("password") ?? "");

  const operator = await db.operator.findUnique({ where: { email } });

  // One message for every failure — unknown email, wrong password, or
  // deactivated. Distinguishing them tells an attacker which addresses are
  // real, which is half the work of getting in.
  const ok = operator?.active ? await verifyPassword(password, operator.passwordHash) : false;

  if (!ok || !operator) {
    // Spend comparable time on the miss so response timing does not reveal
    // whether the address exists.
    if (!operator) await verifyPassword(password, "scrypt$AAAA$AAAA");
    redirect("/login?error=1");
  }

  await createOperatorSession(operator.id);
  redirect("/");
}

export default async function LoginPage({
  searchParams,
}: {
  searchParams: Promise<{ error?: string }>;
}) {
  if (await getOperator()) redirect("/");

  // A fresh install has nobody to sign in as.
  if ((await db.operator.count()) === 0) redirect("/setup");

  const { error } = await searchParams;

  return (
    <AuthCard title="manage.astralab" subtitle="Operator console" action={signIn} submit="Sign in">
      <AuthField label="Email" name="email" type="email" autoComplete="username" autoFocus />
      <AuthField label="Password" name="password" type="password" autoComplete="current-password" />
      {error && <AuthError>Incorrect email or password.</AuthError>}
    </AuthCard>
  );
}
