import { createHmac } from "node:crypto";

// Issues a licence the way the WooCommerce store would, and prints the key.
//
//   npx tsx --env-file=.env scripts/issue-licence.ts <product-slug> [email]
//
// For exercising a real CMS installation against a real hub. The key is shown
// once and cannot be recovered — same as a genuine purchase.
//
// Remember these are real licences: clear them with `npm run db:purge -- --apply`
// before the numbers matter.

const BASE = process.env.SMOKE_BASE_URL ?? "http://localhost:3200";
const slug = process.argv[2] ?? "astralab-cms";
const email = process.argv[3] ?? "manual-test@example.com";

async function main() {
  const secret = process.env.STORE_API_SECRET;
  if (!secret) throw new Error("STORE_API_SECRET is not set — run with --env-file=.env");

  const body = JSON.stringify({
    productSlug: slug,
    orderRef: `manual_${Date.now()}`,
    customerEmail: email,
    amount: 1050,
    currency: "BDT",
  });

  const res = await fetch(`${BASE}/api/v1/licences`, {
    method: "POST",
    headers: {
      "content-type": "application/json",
      "x-astralab-signature": createHmac("sha256", secret).update(body).digest("hex"),
    },
    body,
  });

  const json = await res.json();

  if (!res.ok || !json.licenceKey) {
    console.error(`Failed (${res.status}):`, JSON.stringify(json));
    process.exitCode = 1;
    return;
  }

  console.log(json.licenceKey);
}

main().catch((e) => {
  console.error(e);
  process.exitCode = 1;
});
