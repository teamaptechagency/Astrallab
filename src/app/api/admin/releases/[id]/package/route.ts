import { createHash } from "node:crypto";
import { createWriteStream } from "node:fs";
import { mkdir, rename, rm, stat } from "node:fs/promises";
import { pipeline } from "node:stream/promises";
import { Readable } from "node:stream";
import path from "node:path";
import { NextResponse } from "next/server";
import { db } from "@/lib/db";
import { requireOperatorApi } from "@/lib/require-operator";

// POST /api/admin/releases/<id>/package — upload a release artefact.
//
// A route handler rather than a server action, because packages run to tens of
// megabytes: the body is streamed straight to disk and hashed on the way past,
// so nothing ever holds the whole file in memory. A server action would buffer
// it and fall over on exactly the releases that matter.

export const dynamic = "force-dynamic";
// Streaming means the handler outlives the default budget on a slow upload.
export const maxDuration = 300;

const PACKAGE_DIR = path.join(process.cwd(), "packages");
const MAX_BYTES = 200 * 1024 * 1024;

export async function POST(request: Request, ctx: { params: Promise<{ id: string }> }) {
  const denied = await requireOperatorApi();
  if (denied) return denied;

  const { id } = await ctx.params;

  const release = await db.release.findUnique({ where: { id }, include: { product: true } });
  if (!release) {
    return NextResponse.json({ error: "unknown_release" }, { status: 404 });
  }
  if (!request.body) {
    return NextResponse.json({ error: "empty_body" }, { status: 400 });
  }

  const declared = Number(request.headers.get("content-length") ?? 0);
  if (declared > MAX_BYTES) {
    return NextResponse.json({ error: "too_large" }, { status: 413 });
  }

  const dir = path.join(PACKAGE_DIR, release.productId);
  await mkdir(dir, { recursive: true });

  const finalPath = path.join(dir, `${release.version}.zip`);
  // Write to a temporary name and rename only once the whole upload landed.
  // A half-written file at the real path would be served to customers as a
  // valid package and fail checksum verification on their server instead.
  const tempPath = `${finalPath}.uploading`;

  const hash = createHash("sha256");
  let bytes = 0;

  const source = Readable.fromWeb(request.body as import("stream/web").ReadableStream)
    .on("data", (chunk: Buffer) => {
      bytes += chunk.length;
      hash.update(chunk);
    });

  try {
    await pipeline(source, createWriteStream(tempPath));
  } catch {
    await rm(tempPath, { force: true });
    return NextResponse.json({ error: "upload_failed" }, { status: 500 });
  }

  if (bytes === 0) {
    await rm(tempPath, { force: true });
    return NextResponse.json({ error: "empty_body" }, { status: 400 });
  }
  if (bytes > MAX_BYTES) {
    await rm(tempPath, { force: true });
    return NextResponse.json({ error: "too_large" }, { status: 413 });
  }

  await rename(tempPath, finalPath);
  const written = await stat(finalPath);

  // The checksum is computed here rather than typed in by an operator. Asking
  // a human to paste a SHA-256 invites a wrong one, and a wrong checksum means
  // every install refuses a package that is actually fine.
  const checksum = `sha256-${hash.digest("hex")}`;

  await db.release.update({
    where: { id },
    data: { checksum, sizeBytes: written.size, packageUrl: `packages/${release.productId}/${release.version}.zip` },
  });

  return NextResponse.json({
    ok: true,
    version: release.version,
    sizeBytes: written.size,
    checksum,
  });
}
