import { createServer } from "node:http";
import { readFile } from "node:fs/promises";
import { extname, join, normalize } from "node:path";

// Static server for previewing the site locally.
//
// Node's own http module rather than a package: the site has no build step and
// no dependencies, and adding one just to look at it would undo that.
//
// This is a dev tool. In production the site is served by whatever hosts
// astralab.com — WordPress, or any static host.

const ROOT = import.meta.dirname;
const PORT = Number(process.env.PORT ?? 3300);

const TYPES = {
  ".html": "text/html; charset=utf-8",
  ".css": "text/css; charset=utf-8",
  ".js": "text/javascript; charset=utf-8",
  ".svg": "image/svg+xml",
  ".png": "image/png",
  ".jpg": "image/jpeg",
  ".webp": "image/webp",
  ".ico": "image/x-icon",
};

createServer(async (req, res) => {
  const url = new URL(req.url ?? "/", `http://${req.headers.host}`);
  let pathname = decodeURIComponent(url.pathname);
  if (pathname.endsWith("/")) pathname += "index.html";

  // normalize collapses any ../ before it can escape the directory.
  const filePath = join(ROOT, normalize(pathname));
  if (!filePath.startsWith(ROOT)) {
    res.writeHead(403).end("Forbidden");
    return;
  }

  try {
    const body = await readFile(filePath);
    res.writeHead(200, { "Content-Type": TYPES[extname(filePath)] ?? "application/octet-stream" });
    res.end(body);
  } catch {
    res.writeHead(404, { "Content-Type": "text/html; charset=utf-8" });
    res.end("<h1>404</h1><p><a href='/'>Back to astralab.com</a></p>");
  }
}).listen(PORT, () => {
  console.log(`astralab.com preview → http://localhost:${PORT}`);
});
