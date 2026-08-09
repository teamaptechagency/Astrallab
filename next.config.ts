import type { NextConfig } from "next";

const nextConfig: NextConfig = {
  reactStrictMode: true,
  // A production build overwrites whatever is in the dev server's output
  // directory, leaving it serving chunk files that no longer exist —
  // "Cannot read properties of undefined (reading 'call')" in webpack.js.
  // Verification builds set NEXT_DIST_DIR so they land elsewhere and a running
  // `npm run dev` is never disturbed.
  distDir: process.env.NEXT_DIST_DIR || ".next",
};

export default nextConfig;
