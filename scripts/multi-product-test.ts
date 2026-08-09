import { createHmac } from "node:crypto";

// Proves a second product needs no code: issue, activate and heartbeat against
// a product that was added through the console, not the seed.
//
//   npx tsx --env-file=.env scripts/multi-product-test.ts

const BASE = process.env.SMOKE_BASE_URL ?? "http://localhost:3200";
const PRODUCT = "astralab-portfolio";

let passed = 0;
let failed = 0;
const check = (name: string, ok: boolean, detail?: unknown) => {
  if (ok) passed++;
  else failed++;
  console.log(`${ok ? "PASS" : "FAIL"}  ${name}${!ok && detail !== undefined ? ` -> ${JSON.stringify(detail)}` : ""}`);
};

async function post(path: string, payload: unknown, sign = false) {
  const body = JSON.stringify(payload);
  const headers: Record<string, string> = { "content-type": "application/json" };
  if (sign) {
    headers["x-astralab-signature"] = createHmac("sha256", process.env.STORE_API_SECRET!)
      .update(body)
      .digest("hex");
  }
  const res = await fetch(`${BASE}${path}`, { method: "POST", headers, body });
  return { status: res.status, json: await res.json() };
}

async function main() {
  const orderRef = `portfolio_${Date.now()}`;

  const issued = await post(
    "/api/v1/licences",
    {
      productSlug: PRODUCT,
      orderRef,
      customerEmail: "designer@example.com",
      customerName: "Test Designer",
      amount: 1800,
      currency: "BDT",
    },
    true,
  );
  check("licence issued for the new product", issued.status === 201, issued.json);
  const key = issued.json.licenceKey as string;

  const act = await post("/api/v1/activate", {
    licenceKey: key,
    domain: "portfolio.example.com",
    productSlug: PRODUCT,
  });
  check("activates against the new product", act.json.ok === true, act.json);

  // Products must be isolated: a portfolio key must not activate the CMS, or
  // one purchase would unlock everything sold.
  const crossed = await post("/api/v1/activate", {
    licenceKey: key,
    domain: "other.example.com",
    productSlug: "astralab-cms",
  });
  check("a portfolio key cannot activate the CMS", crossed.status === 404, crossed.status);

  const hb = await post("/api/v1/heartbeat", {
    licenceKey: key,
    domain: "portfolio.example.com",
    productSlug: PRODUCT,
    version: "1.0.0",
  });
  check("heartbeat works for the new product", hb.json.ok === true, hb.json);
  check(
    "no updates offered — this product has no releases yet",
    hb.json.updateAvailable === false,
    hb.json.upgradePath,
  );

  console.log(`\n${passed}/${passed + failed} passed`);
  process.exitCode = failed ? 1 : 0;
}

main().catch((e) => {
  console.error(e);
  process.exitCode = 1;
});
