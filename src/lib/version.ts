// Semver comparison and upgrade-path calculation.
//
// The path matters more than it looks. An install that sat untouched for a
// year cannot jump straight to the newest release — database migrations run in
// sequence, and skipping intermediate versions is how a site ends up with a
// half-migrated schema. So the hub returns the ordered list of releases to
// apply, and the updater walks it one at a time.

export interface Semver {
  major: number;
  minor: number;
  patch: number;
}

export function parseVersion(value: string): Semver | null {
  const match = /^(\d+)\.(\d+)\.(\d+)/.exec(value.trim());
  if (!match) return null;
  return {
    major: Number(match[1]),
    minor: Number(match[2]),
    patch: Number(match[3]),
  };
}

/** -1 if a < b, 0 if equal, 1 if a > b. Unparseable versions sort lowest. */
export function compareVersions(a: string, b: string): number {
  const pa = parseVersion(a);
  const pb = parseVersion(b);
  if (!pa && !pb) return 0;
  if (!pa) return -1;
  if (!pb) return 1;
  if (pa.major !== pb.major) return pa.major < pb.major ? -1 : 1;
  if (pa.minor !== pb.minor) return pa.minor < pb.minor ? -1 : 1;
  if (pa.patch !== pb.patch) return pa.patch < pb.patch ? -1 : 1;
  return 0;
}

export interface ReleaseLike {
  version: string;
  minUpgradeFrom: string | null;
}

/**
 * Ordered releases an install on `currentVersion` must apply to reach the
 * newest one.
 *
 * A release carrying `minUpgradeFrom` is a checkpoint: anything older than that
 * has to stop there first. Releases below the checkpoint are dropped from the
 * path, so the updater applies the checkpoint and then continues, rather than
 * replaying every patch ever shipped.
 */
export function upgradePath<T extends ReleaseLike>(currentVersion: string, releases: T[]): T[] {
  const newer = releases
    .filter((r) => compareVersions(r.version, currentVersion) > 0)
    .sort((a, b) => compareVersions(a.version, b.version));

  if (newer.length === 0) return [];

  // Walk backwards from the newest, honouring each checkpoint on the way down.
  // Whatever the last checkpoint demands becomes the earliest step we keep.
  let floor = currentVersion;
  for (let i = newer.length - 1; i >= 0; i--) {
    const min = newer[i]!.minUpgradeFrom;
    if (min && compareVersions(floor, min) < 0) floor = min;
  }

  return newer.filter((r) => compareVersions(r.version, floor) >= 0);
}
