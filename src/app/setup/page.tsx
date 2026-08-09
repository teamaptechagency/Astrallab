import { redirect } from "next/navigation";
import { db } from "@/lib/db";
import { hashPassword, passwordProblem } from "@/lib/password";
import { createOperatorSession } from "@/lib/operator-session";
import { AuthCard, AuthField, AuthError } from "@/components/auth-card";

export const dynamic = "force-dynamic";

// First-run: create the owner account.
//
// Only reachable while zero operators exist, and the check is repeated inside
// the action — otherwise this page would be a permanent, unauthenticated route
// for minting owner accounts on a live system.

async function createOwner(formData: FormData) {
  "use server";

  if ((await db.operator.count()) > 0) redirect("/login");

  const name = String(formData.get("name") ?? "").trim();
  const email = String(formData.get("email") ?? "").trim().toLowerCase();
  const password = String(formData.get("password") ?? "");

  if (!name || !email.includes("@")) redirect("/setup?error=details");

  const problem = passwordProblem(password);
  if (problem) redirect(`/setup?error=${encodeURIComponent(problem)}`);

  const operator = await db.operator.create({
    data: { name, email, passwordHash: await hashPassword(password), role: "owner" },
  });

  await createOperatorSession(operator.id);
  redirect("/");
}

export default async function SetupPage({
  searchParams,
}: {
  searchParams: Promise<{ error?: string }>;
}) {
  if ((await db.operator.count()) > 0) redirect("/login");
  const { error } = await searchParams;

  return (
    <AuthCard
      title="manage.astralab"
      subtitle="Create the owner account"
      action={createOwner}
      submit="Create account"
    >
      <AuthField label="Your name" name="name" autoFocus autoComplete="name" />
      <AuthField label="Email" name="email" type="email" autoComplete="username" />
      <AuthField
        label="Password"
        name="password"
        type="password"
        autoComplete="new-password"
        hint="At least 10 characters. Length matters more than symbols."
      />
      {error && (
        <AuthError>{error === "details" ? "Enter a name and a valid email." : error}</AuthError>
      )}
    </AuthCard>
  );
}
