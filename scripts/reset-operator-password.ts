import { randomBytes, scrypt as scryptCb } from "node:crypto";
import { promisify } from "node:util";
import { PrismaClient } from "@prisma/client";
import { verifyPassword } from "../src/lib/password";

// Sets a new password on an operator account.
//
//   npx tsx --env-file=.env scripts/reset-operator-password.ts <email> <password>
//
// For the case this console has no other answer to: the operator who ran
// /setup has forgotten what they typed. Passwords are stored as scrypt hashes,
// which cannot be read back by anybody — not by us, and not by whoever ends up
// with a copy of the database. That is the point of them, and it is also why
// the only way back in is to overwrite one.
//
// Deliberately a script and not a page. A "forgot password" screen on a console
// that can revoke every customer's licence is a way in for anyone who reaches
// it; running this needs the server's own filesystem and its .env.
//
// The hash is written with the same scheme as src/lib/password.ts. If that file
// ever changes scheme, this has to change with it — hence the assertion below,
// which fails loudly rather than writing a hash nothing can verify.

const db = new PrismaClient();

const SALT_BYTES = 16;
const KEY_BYTES = 64;

const scrypt = promisify(scryptCb) as (
  password: string,
  salt: Buffer,
  keylen: number,
) => Promise<Buffer>;

async function hashPassword(password: string): Promise<string> {
  const salt = randomBytes(SALT_BYTES);
  const derived = await scrypt(password, salt, KEY_BYTES);
  return `scrypt$${salt.toString("base64url")}$${derived.toString("base64url")}`;
}

async function main() {
  const email = (process.argv[2] ?? "").trim().toLowerCase();
  const password = process.argv[3] ?? "";

  if (!email || !password) {
    console.error("Usage: npx tsx --env-file=.env scripts/reset-operator-password.ts <email> <password>");
    process.exit(1);
  }

  // The same bar /setup holds a new operator to. A recovery path that quietly
  // allows a weaker password than the front door is the weakest link.
  if (password.length < 10) {
    console.error("Use at least 10 characters.");
    process.exit(1);
  }

  if (/^\d+$/.test(password)) {
    console.error("Digits alone are too easy to guess.");
    process.exit(1);
  }

  const operator = await db.operator.findUnique({ where: { email } });

  if (!operator) {
    const all = await db.operator.findMany({ select: { email: true } });
    console.error(`No operator with that address. This database has: ${all.map((o) => o.email).join(", ") || "none"}`);
    process.exit(1);
  }

  const passwordHash = await hashPassword(password);

  // Prove the hash verifies before storing it, so a mismatch with
  // src/lib/password.ts surfaces here rather than as a login that never works.
  if (!(await verifyPassword(password, passwordHash))) {
    console.error("The hash this script produced does not verify against src/lib/password.ts. Nothing was written.");
    process.exit(1);
  }

  // Temporary by default. A password set by somebody else — read out on a call,
  // sent over WhatsApp — should stop working the moment it has been used once,
  // rather than living in that chat for as long as the account does. Whoever
  // signs in with it goes straight to /change-password and can reach nothing
  // else until they have replaced it.
  //
  // --permanent is for when the person running this is the person who will use
  // it, and there is nobody to hand it to.
  const permanent = process.argv.includes("--permanent");

  await db.operator.update({
    where: { email },
    data: { passwordHash, active: true, mustChangePassword: !permanent },
  });

  // Every existing session ends. If the old password was known to somebody it
  // should not have been, changing it while leaving their session open changes
  // nothing at all — the session is the access, not the password.
  const { count } = await db.operatorSession.updateMany({
    where: { operatorId: operator.id, revokedAt: null },
    data: { revokedAt: new Date() },
  });

  console.log(`Password set for ${email}. The account is active.`);

  if (count > 0) {
    console.log(`Signed out ${count} existing ${count === 1 ? "session" : "sessions"}.`);
  }

  console.log(
    permanent
      ? "Permanent — they will not be asked to change it."
      : "Temporary — they must choose their own on first sign-in.",
  );
  console.log("Sign in at http://localhost:3200/login");
}

main()
  .catch((error) => {
    console.error(error instanceof Error ? error.message : error);
    process.exit(1);
  })
  .finally(() => db.$disconnect());
