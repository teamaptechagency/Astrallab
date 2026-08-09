// Who may do what.
//
// Roles are shaped around blast radius, not seniority. The question for each
// permission is "what happens to a customer if this is done wrongly?"
//
//   revoking a licence      → their storefront stops working
//   publishing a release    → every install is offered that code
//   reading leads           → someone else's customers' personal data
//   viewing finance         → the company's numbers
//
// Support staff answer tickets all day and should not be one misclick from any
// of those. So support can suspend (reversible, and sometimes genuinely needed
// for a payment dispute) but not revoke, which is terminal.

export const ROLES = ["owner", "developer", "support"] as const;
export type Role = (typeof ROLES)[number];

export const ROLE_LABELS: Record<Role, string> = {
  owner: "Owner — full access, including team and settings",
  developer: "Developer — products, releases and support",
  support: "Support — tickets and licence lookup",
};

export type Permission =
  | "licences.view"
  | "licences.suspend"
  | "licences.revoke"
  | "products.manage"
  | "releases.manage"
  | "support.manage"
  | "shopdata.view"
  | "leads.view"
  | "finance.view"
  | "finance.manage"
  | "settings.manage"
  | "team.manage"
  | "apiconfig.view"
  | "apiconfig.rotate";

const PERMISSIONS: Record<Role, Permission[]> = {
  owner: [
    "licences.view",
    "licences.suspend",
    "licences.revoke",
    "products.manage",
    "releases.manage",
    "support.manage",
    "shopdata.view",
    "leads.view",
    "finance.view",
    "finance.manage",
    "settings.manage",
    "team.manage",
    "apiconfig.view",
    "apiconfig.rotate",
  ],
  developer: [
    "licences.view",
    "licences.suspend",
    "products.manage",
    "releases.manage",
    "support.manage",
    "shopdata.view",
    "apiconfig.view",
  ],
  support: ["licences.view", "licences.suspend", "support.manage"],
};

export function can(role: string, permission: Permission): boolean {
  const list = PERMISSIONS[role as Role];
  return list ? list.includes(permission) : false;
}

export function isRole(value: string): value is Role {
  return (ROLES as readonly string[]).includes(value);
}

/** Which nav destinations a role should even see. */
export function visibleRoutes(role: string): string[] {
  const routes = ["/"];
  if (can(role, "licences.view")) routes.push("/licences");
  if (can(role, "releases.manage")) routes.push("/releases");
  if (can(role, "support.manage")) routes.push("/support");
  if (can(role, "products.manage")) routes.push("/products");
  if (can(role, "shopdata.view")) routes.push("/shop-data");
  if (can(role, "leads.view")) routes.push("/leads");
  if (can(role, "finance.view")) routes.push("/finance");
  if (can(role, "team.manage")) routes.push("/team");
  if (can(role, "apiconfig.view")) routes.push("/api-config");
  if (can(role, "settings.manage")) routes.push("/settings");
  routes.push("/more", "/profile");
  return routes;
}
