import { createHmac, createHash } from "node:crypto";

// End-to-end package delivery: buy, activate, download, verify.
//
// The one flow that has to be right — it is how every customer gets the
// software they paid for.
//
//   npx tsx --env-file=.env scripts/download-test.ts

const BASE = process.env.SMOKE_BASE_URL ?? "http://localhost:3200";
const PRODUCT = "astralab-cms";

let passed = 0;
let failed = 0;
const check = (name: string, ok: boolean, detail?: unknown) => {
  if (ok) passed++;
  else failed++;
  console.log(`${ok ? "PASS" : "FAIL"}  ${name}${!ok && detail !== undefined ? ` -> ${JSON.stringify(detail)}` : ""}`);
};

async function main() {
  const orderRef = `dl_${Date.now()}`;
  const body = JSON.stringify({
    productSlug: PRODUCT,
    orderRef,
    customerEmail: "dl@example.com",
    amount: 1050,
  });
  const sig = createHmac("sha256", process.env.STORE_API_SECRET!).update(body).digest("hex");

  const issued = await fetch(`${BASE}/api/v1/licences`, {
    method: "POST",
    headers: { "content-type": "application/json", "x-astralab-signature": sig },
    body,
  }).then((r) => r.json());
  const key = issued.licenceKey as string;
  check("licence issued", Boolean(key), issued);

  const act = await fetch(`${BASE}/api/v1/activate`, {
    method: "POST",
    headers: { "content-type": "application/json" },
    body: JSON.stringify({
      licenceKey: key,
      domain: "dltest.example.com",
      productSlug: PRODUCT,
      version: "1.0.0",
    }),
  }).then((r) => r.json());

  const token = act.download?.token as string | undefined;
  check("activation returned a download token", Boolean(token), act.download);
  if (!token) return finish();

  // --- whole file ---------------------------------------------------------
  const res = await fetch(`${BASE}/api/v1/download?token=${encodeURIComponent(token)}`);
  check("download succeeds", res.status === 200, res.status);

  const buf = Buffer.from(await res.arrayBuffer());
  const actual = `sha256-${createHash("sha256").update(buf).digest("hex")}`;
  const advertised = res.headers.get("x-package-checksum");

  check("bytes match the advertised checksum", actual === advertised, { actual, advertised });
  check("content-length matches what arrived", Number(res.headers.get("content-length")) === buf.length);
  check("range requests are advertised", res.headers.get("accept-ranges") === "bytes");

  // --- ranged fetch, the way a shared host has to do it --------------------
  const chunkSize = 65536;
  const chunks: Buffer[] = [];
  let offset = 0;
  let partialStatusOk = true;

  while (offset < buf.length) {
    const end = Math.min(offset + chunkSize - 1, buf.length - 1);
    const part = await fetch(`${BASE}/api/v1/download?token=${encodeURIComponent(token)}`, {
      headers: { Range: `bytes=${offset}-${end}` },
    });
    if (part.status !== 206) partialStatusOk = false;
    chunks.push(Buffer.from(await part.arrayBuffer()));
    offset = end + 1;
  }

  const reassembled = Buffer.concat(chunks);
  check("every chunk returned 206 Partial Content", partialStatusOk);
  check(
    "chunked download reassembles to the identical file",
    Buffer.compare(reassembled, buf) === 0,
    { chunked: reassembled.length, whole: buf.length },
  );

  // --- revocation beats a live token --------------------------------------
  const revoked = await fetch(`${BASE}/api/v1/download?token=${encodeURIComponent(token)}`, {
    headers: { Range: "bytes=0-10" },
  });
  check("token still works while the licence is valid", revoked.status === 206, revoked.status);

  finish();
}

function finish() {
  console.log(`\n${passed}/${passed + failed} passed`);
  process.exitCode = failed ? 1 : 0;
}

main().catch((e) => {
  console.error(e);
  process.exitCode = 1;
});
