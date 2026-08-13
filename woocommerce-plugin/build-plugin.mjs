import { readdir, readFile, writeFile, stat } from "node:fs/promises";
import { deflateRawSync, crc32 } from "node:zlib";
import path from "node:path";

// Builds the installable WordPress plugin.
//
//   node woocommerce-plugin/build-plugin.mjs
//
// WordPress expects a zip with exactly one folder at its root, named after the
// plugin, containing the plugin file. Anything else — files at the root, or a
// second wrapping folder — and "Upload Plugin" rejects it or installs something
// that cannot be activated.
//
// The archive is written here rather than by PowerShell's Compress-Archive,
// which stores paths with backslashes. Windows unpacks those; the Linux server
// running WordPress does not, and the plugin arrives as a single file named
// "astralab-licence\includes\class-astralab-client.php" that PHP will never
// require. The ZIP format has always wanted forward slashes.

const ROOT = import.meta.dirname;
const PLUGIN = "astralab-licence";
const SOURCE = path.join(ROOT, PLUGIN);

/** Everything under the plugin folder, as archive paths. */
async function collect(dir, prefix = PLUGIN) {
  const found = [];

  for (const entry of await readdir(dir, { withFileTypes: true })) {
    const name = `${prefix}/${entry.name}`;

    if (entry.isDirectory()) found.push(...(await collect(path.join(dir, entry.name), name)));
    else found.push(name);
  }

  return found.sort();
}

function dosStamp(date) {
  return {
    time: (date.getHours() << 11) | (date.getMinutes() << 5) | Math.floor(date.getSeconds() / 2),
    day: ((date.getFullYear() - 1980) << 9) | ((date.getMonth() + 1) << 5) | date.getDate(),
  };
}

async function main() {
  // The version in the plugin header is what WordPress shows and what an
  // update check compares against, so the filename is taken from it rather
  // than typed separately and allowed to drift.
  const header = await readFile(path.join(SOURCE, `${PLUGIN}.php`), "utf8");
  const version = header.match(/^\s*\*\s*Version:\s*(.+)$/m)?.[1].trim();

  if (!version) throw new Error("No Version: line in the plugin header — WordPress needs one.");

  const out = path.join(ROOT, `${PLUGIN}-${version}.zip`);
  const names = await collect(SOURCE);
  const locals = [];
  const central = [];
  let offset = 0;

  for (const name of names) {
    const onDisk = path.join(ROOT, name);
    const body = await readFile(onDisk);
    const { time, day } = dosStamp((await stat(onDisk)).mtime);

    const deflated = deflateRawSync(body, { level: 9 });
    const useDeflate = deflated.length < body.length;
    const data = useDeflate ? deflated : body;
    const method = useDeflate ? 8 : 0;

    const nameBytes = Buffer.from(name, "utf8");
    const sum = crc32(body);

    const local = Buffer.alloc(30);
    local.writeUInt32LE(0x04034b50, 0);
    local.writeUInt16LE(20, 4);
    local.writeUInt16LE(0x0800, 6); // UTF-8 names
    local.writeUInt16LE(method, 8);
    local.writeUInt16LE(time, 10);
    local.writeUInt16LE(day, 12);
    local.writeUInt32LE(sum, 14);
    local.writeUInt32LE(data.length, 18);
    local.writeUInt32LE(body.length, 22);
    local.writeUInt16LE(nameBytes.length, 26);
    locals.push(local, nameBytes, data);

    const dir = Buffer.alloc(46);
    dir.writeUInt32LE(0x02014b50, 0);
    dir.writeUInt16LE(20, 4);
    dir.writeUInt16LE(20, 6);
    dir.writeUInt16LE(0x0800, 8);
    dir.writeUInt16LE(method, 10);
    dir.writeUInt16LE(time, 12);
    dir.writeUInt16LE(day, 14);
    dir.writeUInt32LE(sum, 16);
    dir.writeUInt32LE(data.length, 20);
    dir.writeUInt32LE(body.length, 24);
    dir.writeUInt16LE(nameBytes.length, 28);
    dir.writeUInt32LE(0o644 << 16, 38);
    dir.writeUInt32LE(offset, 42);
    central.push(dir, nameBytes);

    offset += local.length + nameBytes.length + data.length;
  }

  const centralBytes = Buffer.concat(central);
  const end = Buffer.alloc(22);
  end.writeUInt32LE(0x06054b50, 0);
  end.writeUInt16LE(names.length, 8);
  end.writeUInt16LE(names.length, 10);
  end.writeUInt32LE(centralBytes.length, 12);
  end.writeUInt32LE(offset, 16);

  await writeFile(out, Buffer.concat([...locals, centralBytes, end]));

  // Checked rather than assumed. A plugin that will not activate is discovered
  // in the WordPress admin, on the store, usually while something else is
  // going wrong.
  const entry = `${PLUGIN}/${PLUGIN}.php`;
  if (!names.includes(entry)) throw new Error(`No ${entry} in the archive — WordPress needs it.`);
  if (names.some((n) => n.includes("\\"))) throw new Error("Backslashes in a path.");
  if (names.some((n) => !n.startsWith(`${PLUGIN}/`))) throw new Error("Something sits outside the plugin folder.");

  const size = (await stat(out)).size;
  console.log(`${path.basename(out)} — ${names.length} files, ${(size / 1024).toFixed(0)} KB\n`);
  for (const n of names) console.log(`  ${n}`);
  console.log(`\nWordPress → Plugins → Add New → Upload Plugin.`);
}

main().catch((error) => {
  console.error(error.message);
  process.exit(1);
});
