import { randomBytes } from "node:crypto";
import { generateSigningKeypair } from "../src/lib/signing";

// Prints a full set of secrets for a fresh environment.
//
//   npx tsx scripts/generate-keys.ts
//
// The Ed25519 PUBLIC key is the one that ships inside the CMS, so installs can
// verify our responses. The private key never leaves the hub — anyone holding
// it can forge "your licence is valid" for any site.

const { privateKey, publicKey } = generateSigningKeypair();
const oneLine = (pem: string) => pem.trim().replace(/\n/g, "\\n");

console.log("# --- manage.astralab secrets ---");
console.log(`LICENCE_SECRET="${randomBytes(32).toString("base64")}"`);
console.log(`STORE_API_SECRET="${randomBytes(32).toString("base64")}"`);
console.log(`PACKAGE_URL_SECRET="${randomBytes(32).toString("base64")}"`);
console.log(`SIGNING_PRIVATE_KEY="${oneLine(privateKey)}"`);
console.log("");
console.log("# --- bake this into the CMS (public, safe to ship) ---");
console.log(publicKey.trim());
