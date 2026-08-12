import { readdir, readFile, writeFile, stat } from "node:fs/promises";
import { deflateRawSync, crc32 } from "node:zlib";
import path from "node:path";

// Builds the upload for Hostinger.
//
//   node website/build-zip.mjs
//
// Two things this does that doing it by hand does not.
//
// It excludes the development files. serve.mjs, the markdown and this script
// are tools, and a site that ships its own README is a site that publishes it.
//
// And it writes the archive itself, entry by entry, rather than calling
// PowerShell's Compress-Archive — which stores paths with backslashes.
// Windows unpacks those; Linux does not. Extracted in public_html through
// hPanel, an archive made that way produces a single file named
// "assets\site.js" instead of an assets folder, and the site comes up with no
// stylesheet at all. The ZIP format has always required forward slashes; it is
// Compress-Archive that is wrong, and it is wrong silently.
//
// The files sit at the archive root, not inside a folder, so extracting in
// public_html puts index.html at public_html/index.html rather than putting
// the whole site at astralab.com/website/.

const ROOT = import.meta.dirname;
const OUT = path.join(ROOT, "astralab-website.zip");

// A deny-list on purpose: a new page or image should reach the upload without
// anyone remembering to add it here, whereas a new dev tool is worth a thought.
const DEV_ONLY = new Set([
  "serve.mjs",
  "build-zip.mjs",
  "README.md",
  "DEPLOY.md",
  "astralab-website.zip",
  ".build",
]);

/** Every shippable file, as archive-relative paths with forward slashes. */
async function collect(dir, prefix = "") {
  const found = [];

  for (const entry of await readdir(dir, { withFileTypes: true })) {
    if (prefix === "" && DEV_ONLY.has(entry.name)) continue;

    const name = prefix ? `${prefix}/${entry.name}` : entry.name;

    if (entry.isDirectory()) found.push(...(await collect(path.join(dir, entry.name), name)));
    else found.push(name);
  }

  return found.sort();
}

/** MS-DOS packed date and time, which is what a ZIP entry carries. */
function dosStamp(date) {
  const time =
    (date.getHours() << 11) | (date.getMinutes() << 5) | Math.floor(date.getSeconds() / 2);
  const day =
    ((date.getFullYear() - 1980) << 9) | ((date.getMonth() + 1) << 5) | date.getDate();

  return { time, day };
}

async function main() {
  const names = await collect(ROOT);
  const locals = [];
  const central = [];
  let offset = 0;

  for (const name of names) {
    const body = await readFile(path.join(ROOT, name));
    const { mtime } = await stat(path.join(ROOT, name));
    const { time, day } = dosStamp(mtime);

    const deflated = deflateRawSync(body, { level: 9 });
    // A file that deflates larger than it started — a small PNG, say — is
    // stored as-is. Method 0 is stored, 8 is deflate.
    const useDeflate = deflated.length < body.length;
    const data = useDeflate ? deflated : body;
    const method = useDeflate ? 8 : 0;

    const nameBytes = Buffer.from(name, "utf8");
    const sum = crc32(body);

    const local = Buffer.alloc(30);
    local.writeUInt32LE(0x04034b50, 0); // local file header
    local.writeUInt16LE(20, 4); // version needed
    local.writeUInt16LE(0x0800, 6); // UTF-8 names
    local.writeUInt16LE(method, 8);
    local.writeUInt16LE(time, 10);
    local.writeUInt16LE(day, 12);
    local.writeUInt32LE(sum, 14);
    local.writeUInt32LE(data.length, 18);
    local.writeUInt32LE(body.length, 22);
    local.writeUInt16LE(nameBytes.length, 26);
    local.writeUInt16LE(0, 28); // no extra field

    locals.push(local, nameBytes, data);

    const dir = Buffer.alloc(46);
    dir.writeUInt32LE(0x02014b50, 0); // central directory header
    dir.writeUInt16LE(20, 4); // version made by
    dir.writeUInt16LE(20, 6); // version needed
    dir.writeUInt16LE(0x0800, 8);
    dir.writeUInt16LE(method, 10);
    dir.writeUInt16LE(time, 12);
    dir.writeUInt16LE(day, 14);
    dir.writeUInt32LE(sum, 16);
    dir.writeUInt32LE(data.length, 20);
    dir.writeUInt32LE(body.length, 24);
    dir.writeUInt16LE(nameBytes.length, 28);
    dir.writeUInt32LE(0o644 << 16, 38); // unix permissions, so extraction is readable
    dir.writeUInt32LE(offset, 42);

    central.push(dir, nameBytes);

    offset += local.length + nameBytes.length + data.length;
  }

  const centralBytes = Buffer.concat(central);

  const end = Buffer.alloc(22);
  end.writeUInt32LE(0x06054b50, 0); // end of central directory
  end.writeUInt16LE(names.length, 8);
  end.writeUInt16LE(names.length, 10);
  end.writeUInt32LE(centralBytes.length, 12);
  end.writeUInt32LE(offset, 16);

  await writeFile(OUT, Buffer.concat([...locals, centralBytes, end]));

  // Read it back rather than trusting it. A missing .htaccess is invisible
  // until the redirects quietly do nothing on the live domain, and a backslash
  // in a path is invisible until the site comes up unstyled.
  const { execFile } = await import("node:child_process");
  const { promisify } = await import("node:util");
  const { stdout } = await promisify(execFile)("powershell", [
    "-NoProfile",
    "-Command",
    `Add-Type -A System.IO.Compression.FileSystem; ` +
      `[IO.Compression.ZipFile]::OpenRead('${OUT}').Entries | ForEach-Object { $_.FullName }`,
  ]);

  const inside = stdout.trim().split(/\r?\n/).filter(Boolean);

  if (!inside.includes(".htaccess")) {
    throw new Error("The archive has no .htaccess. None of the redirects will work.");
  }

  const wrong = inside.filter((n) => n.includes("\\"));
  if (wrong.length) throw new Error(`Backslashes in: ${wrong.join(", ")}`);

  const size = (await stat(OUT)).size;
  console.log(`astralab-website.zip — ${inside.length} files, ${(size / 1024).toFixed(0)} KB\n`);
  for (const name of inside.sort()) console.log(`  ${name}`);
  console.log(`\nUpload to public_html and Extract. Left out: ${[...DEV_ONLY].join(", ")}`);
}

main().catch((error) => {
  console.error(error.message);
  process.exit(1);
});
