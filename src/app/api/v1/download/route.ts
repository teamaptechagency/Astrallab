import { createReadStream, statSync } from "node:fs";
import { Readable } from "node:stream";
import path from "node:path";
import { NextResponse } from "next/server";
import { db } from "@/lib/db";
import { verifyDownloadToken, parseRange } from "@/lib/download-token";

// GET /api/v1/download?token=... — serve a release package.
//
// The last gate in the distribution model. The installer holds no application
// code; it reaches here with a token minted by /activate or /heartbeat, and
// only a valid token against a still-valid licence yields the package.
//
// The file is streamed through the hub rather than redirected to. A redirect
// would hand the client the real storage URL, which is the one thing this
// design is trying to avoid — that URL would then be shareable and permanent.
// In production the better answer is a presigned object-storage URL (S3/R2),
// which expires on its own; this local streaming version keeps the prototype
// self-contained and behaves identically from the installer's point of view.

export const dynamic = "force-dynamic";

// Packages live outside the web root. Nothing here is reachable by path.
const PACKAGE_DIR = path.join(process.cwd(), "packages");

export async function GET(request: Request) {
  const token = new URL(request.url).searchParams.get("token");
  if (!token) {
    return NextResponse.json({ error: "missing_token" }, { status: 400 });
  }

  const verified = verifyDownloadToken(token);
  if (!verified.ok) {
    // Same status for every failure. Distinguishing "expired" from "forged"
    // would tell someone probing tokens which of their guesses parsed.
    return NextResponse.json({ error: "invalid_token" }, { status: 403 });
  }

  const { releaseId, licenceId } = verified.claim;

  const [release, licence] = await Promise.all([
    db.release.findUnique({ where: { id: releaseId } }),
    db.licence.findUnique({ where: { id: licenceId } }),
  ]);

  if (!release || !release.published || !licence) {
    return NextResponse.json({ error: "invalid_token" }, { status: 403 });
  }

  // Re-check the licence at download time. A token minted an hour ago must not
  // still work if the licence was revoked five minutes later — this is the
  // whole point of keeping tokens short-lived AND re-validating.
  if (licence.status === "revoked" || licence.status === "suspended") {
    await db.licenceEvent.create({
      data: { licenceId, kind: "blocked", detail: `download while ${licence.status}` },
    });
    return NextResponse.json({ error: licence.status }, { status: 403 });
  }

  const filename = `${release.version}.zip`;
  const filePath = path.join(PACKAGE_DIR, release.productId, filename);

  // Belt and braces: the path is built from database ids, not user input, but
  // a traversal here would serve arbitrary files off the server.
  if (!filePath.startsWith(PACKAGE_DIR)) {
    return NextResponse.json({ error: "invalid_token" }, { status: 403 });
  }

  let size: number;
  try {
    size = statSync(filePath).size;
  } catch {
    // The release row exists but its artefact was never uploaded. That's an
    // operator mistake, not a client one — say so honestly.
    return NextResponse.json(
      { error: "package_unavailable", version: release.version },
      { status: 503 },
    );
  }

  const range = parseRange(request.headers.get("range"), size);

  const headers = new Headers({
    "Content-Type": "application/zip",
    "Content-Disposition": `attachment; filename="${filename}"`,
    // Advertise range support so the installer knows it can chunk.
    "Accept-Ranges": "bytes",
    // The checksum the installer must verify before extracting over a live
    // site — travelling with the file rather than needing a second request.
    "X-Package-Checksum": release.checksum,
    "X-Package-Version": release.version,
    "Cache-Control": "private, no-store",
  });

  if (range) {
    headers.set("Content-Range", `bytes ${range.start}-${range.end}/${size}`);
    headers.set("Content-Length", String(range.end - range.start + 1));
  } else {
    headers.set("Content-Length", String(size));
  }

  // Only log whole-file starts, not every chunk — a ranged download of a 40 MB
  // package would otherwise write hundreds of audit rows for one update.
  if (!range || range.start === 0) {
    await db.licenceEvent.create({
      data: { licenceId, kind: "downloaded", detail: `v${release.version}` },
    });
  }

  const nodeStream = createReadStream(
    filePath,
    range ? { start: range.start, end: range.end } : undefined,
  );

  return new Response(Readable.toWeb(nodeStream) as ReadableStream, {
    status: range ? 206 : 200,
    headers,
  });
}
