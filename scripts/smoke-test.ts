import { createHmac } from "node:crypto";

// End-to-end exercise of the licence lifecycle against a running server.
//
//   npx tsx --env-file=.env scripts/smoke-test.ts
//
// Deliberately talks over real HTTP rather than calling the route handlers
// directly, so signature verification, status codes and JSON shapes are all
// covered the way a real WooCommerce store and a real CMS install would hit them.

const BASE = process.env.SMOKE_BASE_URL ?? "http://localhost:3200";
const PRODUCT = "astralab-cms";

let passed = 0;
let failed = 0;

function check(name: string, ok: boolean, detail?: unknown) {
  if (ok) {
    passed++;
    console.log(`PASS  ${name}`);
  } else {
    failed++;
    console.log(`FAIL  ${name}${detail === undefined ? "" : ` -> ${JSON.stringify(detail)}`}`);
  }
}

async function issue(orderRef: string) {
  const body = JSON.stringify({
    productSlug: PRODUCT,
    orderRef,
    customerEmail: "shop@example.com",
    customerName: "Test Shop",
  });
  const signature = createHmac("sha256", process.env.STORE_API_SECRET!).update(body).digest("hex");
  const res = await fetch(`${BASE}/api/v1/licences`, {
    method: "POST",
    headers: { "content-type": "application/json", "x-astralab-signature": signature },
    body,
  });
  return { status: res.status, json: await res.json() };
}

async function post(path: string, payload: unknown) {
  const res = await fetch(`${BASE}${path}`, {
    method: "POST",
    headers: { "content-type": "application/json" },
    body: JSON.stringify(payload),
  });
  return { status: res.status, json: await res.json() };
}

async function main() {
  const orderRef = `wc_${Date.now()}`;

  // --- issuing -------------------------------------------------------------
  const unsigned = await fetch(`${BASE}/api/v1/licences`, {
    method: "POST",
    headers: { "content-type": "application/json" },
    body: JSON.stringify({ productSlug: PRODUCT, orderRef, customerEmail: "a@b.com" }),
  });
  check("unsigned issue request is rejected", unsigned.status === 401, unsigned.status);

  const issued = await issue(orderRef);
  check("licence issued", issued.status === 201 && !!issued.json.licenceKey, issued.json);
  const key = issued.json.licenceKey as string;

  const repeat = await issue(orderRef);
  check("duplicate webhook does not mint a second licence", repeat.json.duplicate === true, repeat.json);
  check("duplicate response withholds the key", repeat.json.licenceKey === undefined, repeat.json);

  // --- activation ----------------------------------------------------------
  const bad = await post("/api/v1/activate", {
    licenceKey: "ASTRA-0000-0000-0000-0000",
    domain: "shop.com",
    productSlug: PRODUCT,
  });
  check("unknown key cannot activate", bad.status === 404, bad.status);

  const act = await post("/api/v1/activate", {
    licenceKey: key,
    domain: "https://WWW.MyShop.com/",
    productSlug: PRODUCT,
    version: "1.0.0",
  });
  check("activation succeeds", act.status === 200 && act.json.ok === true, act.json);
  check("domain is normalised", act.json.validation?.data?.domain === "myshop.com", act.json.validation?.data);
  check("activation returns a signed envelope", typeof act.json.validation?.signature === "string");
  check("activation returns a download token", typeof act.json.download?.token === "string");

  const reAct = await post("/api/v1/activate", {
    licenceKey: key,
    domain: "myshop.com",
    productSlug: PRODUCT,
  });
  check("re-running the installer on the same domain is allowed", reAct.json.ok === true, reAct.json);

  const second = await post("/api/v1/activate", {
    licenceKey: key,
    domain: "othershop.com",
    productSlug: PRODUCT,
  });
  check("second domain is refused", second.status === 409, second.json);

  // --- updates -------------------------------------------------------------
  const hb = await post("/api/v1/heartbeat", {
    licenceKey: key,
    domain: "myshop.com",
    productSlug: PRODUCT,
    version: "1.0.0",
    phpVersion: "8.2.10",
  });
  check("heartbeat accepted", hb.status === 200 && hb.json.ok === true, hb.json);
  const path = (hb.json.upgradePath ?? []).map((r: { version: string }) => r.version);
  check(
    "upgrade path stops at the 1.2.0 checkpoint",
    JSON.stringify(path) === JSON.stringify(["1.1.0", "1.2.0", "1.2.1", "1.3.0"]),
    path,
  );
  check("security release shortens the check interval", hb.json.nextCheckInSeconds === 3600, hb.json.nextCheckInSeconds);

  const upToDate = await post("/api/v1/heartbeat", {
    licenceKey: key,
    domain: "myshop.com",
    productSlug: PRODUCT,
    version: "1.3.0",
  });
  check("current install is offered no updates", upToDate.json.updateAvailable === false, upToDate.json);

  const wrongDomain = await post("/api/v1/heartbeat", {
    licenceKey: key,
    domain: "staging.myshop.com",
    productSlug: PRODUCT,
    version: "1.3.0",
  });
  check("heartbeat from an unbound domain is refused", wrongDomain.status === 409, wrongDomain.status);

  // --- transfer ------------------------------------------------------------
  const deact = await post("/api/v1/deactivate", { licenceKey: key, domain: "myshop.com" });
  check("deactivation succeeds", deact.json.ok === true, deact.json);

  const deactAgain = await post("/api/v1/deactivate", { licenceKey: key, domain: "myshop.com" });
  check("deactivation is idempotent", deactAgain.json.alreadyReleased === true, deactAgain.json);

  const moved = await post("/api/v1/activate", {
    licenceKey: key,
    domain: "newshop.com",
    productSlug: PRODUCT,
  });
  check("licence can move to a new domain after release", moved.json.ok === true, moved.json);

  // --- package download ----------------------------------------------------
  const token = moved.json.download?.token as string | undefined;
  check("activation on the new domain returns a download token", typeof token === "string");

  const forged = await fetch(`${BASE}/api/v1/download?token=${encodeURIComponent("aaaa.bbbb")}`);
  check("forged download token is rejected", forged.status === 403, forged.status);

  const noToken = await fetch(`${BASE}/api/v1/download`);
  check("download without a token is rejected", noToken.status === 400, noToken.status);

  if (token) {
    const dl = await fetch(`${BASE}/api/v1/download?token=${encodeURIComponent(token)}`);
    // The seeded releases have no uploaded artefact, so 503 is the correct,
    // honest answer here — it proves the token verified and the licence passed
    // every check, and only the file itself is missing.
    check(
      "valid token passes every check and reaches the package stage",
      dl.status === 503 || dl.status === 200,
      dl.status,
    );
  }

  // --- integrations (Ayojon connection space) ------------------------------
  const integ = await post("/api/v1/integrations", { licenceKey: key, domain: "newshop.com" });
  check("integrations endpoint answers", integ.status === 200 && integ.json.ok === true, integ.json);
  check(
    "Ayojon is advertised as coming soon, not connectable",
    integ.json.integrations?.ayojon?.status === "coming_soon" &&
      integ.json.integrations?.ayojon?.connectUrl === null,
    integ.json.integrations?.ayojon,
  );
  check("no Ayojon link exists yet", integ.json.integrations?.ayojon?.connected === false);

  const integWrongDomain = await post("/api/v1/integrations", {
    licenceKey: key,
    domain: "someone-else.com",
  });
  check("integrations refuses an unbound domain", integWrongDomain.status === 409, integWrongDomain.status);

  // --- console is not public ----------------------------------------------
  const console_ = await fetch(`${BASE}/`, { redirect: "manual" });
  check(
    "operator console redirects anonymous visitors to login",
    console_.status === 307 || console_.status === 302,
    console_.status,
  );

  console.log(`\n${passed}/${passed + failed} passed`);
  process.exitCode = failed ? 1 : 0;
}

main().catch((e) => {
  console.error(e);
  process.exitCode = 1;
});
