export type ModuleProfile =
  "content" | "article" | "event" | "property" | "service";
export type ModuleDefinition = {
  label: string;
  singular: string;
  path: string;
  icon: string;
  profile: ModuleProfile;
  description: string;
  scheduling: boolean;
  navigation: boolean;
};
export const moduleIconNames = [
  "FileText",
  "BriefcaseBusiness",
  "CalendarDays",
  "House",
  "MessageSquare",
  "Folder",
] as const;
const reservedIds = [
  "auth",
  "metadata",
  "activity",
  "media",
  "contacts",
  "subscribers",
  "users",
  "public",
  "seo",
  "admin",
  "dashboard",
  "site_admin",
  "content_lock",
  "content_media",
  "migrations",
  "seo_settings",
  "page_metadata",
  "activity_log",
  "rate_limits",
  "password_resets",
  "user_management_lock",
];
const reservedPaths = [
  "about",
  "contact",
  "site-admin",
  "admin",
  "dashboard",
  "api",
];
export function validateModuleRegistry(
  value: unknown,
): Record<string, ModuleDefinition> {
  if (!value || typeof value !== "object" || Array.isArray(value))
    throw new Error("Content modules must be a JSON object.");
  const result: Record<string, ModuleDefinition> = {};
  const paths = new Set<string>();
  for (const [id, raw] of Object.entries(value)) {
    if (!/^[a-z][a-z0-9_]{0,19}$/.test(id) || reservedIds.includes(id))
      throw new Error(`Invalid or reserved content module: ${id}`);
    if (!raw || typeof raw !== "object" || Array.isArray(raw))
      throw new Error(`Invalid module definition: ${id}`);
    const data = raw as Record<string, unknown>;
    for (const key of Object.keys(data))
      if (
        ![
          "label",
          "singular",
          "path",
          "icon",
          "profile",
          "description",
          "scheduling",
          "navigation",
        ].includes(key)
      )
        throw new Error(`Unknown module field ${id}.${key}`);
    for (const key of ["label", "singular", "path"])
      if (
        typeof data[key] !== "string" ||
        !(data[key] as string).trim() ||
        new TextEncoder().encode(data[key] as string).length > 100
      )
        throw new Error(
          `${id}.${key} must be a nonempty string up to 100 bytes.`,
        );
    const path = data.path as string;
    if (
      !/^[a-z][a-z0-9-]*$/.test(path) ||
      reservedPaths.includes(path) ||
      paths.has(path)
    )
      throw new Error(
        `Invalid, reserved, or duplicate public module path: ${path}`,
      );
    paths.add(path);
    const profile = data.profile ?? "content";
    if (
      !["content", "article", "event", "property", "service"].includes(
        String(profile),
      )
    )
      throw new Error(`Invalid profile for ${id}`);
    const icon = data.icon ?? "FileText";
    if (!moduleIconNames.includes(icon as (typeof moduleIconNames)[number]))
      throw new Error(`Invalid icon for ${id}`);
    if (
      data.description !== undefined &&
      (typeof data.description !== "string" ||
        new TextEncoder().encode(data.description).length > 1000)
    )
      throw new Error(`Invalid description for ${id}`);
    for (const key of ["scheduling", "navigation"])
      if (data[key] !== undefined && typeof data[key] !== "boolean")
        throw new Error(`${id}.${key} must be a boolean.`);
    result[id] = {
      label: (data.label as string).trim(),
      singular: (data.singular as string).trim(),
      path,
      icon: String(icon),
      profile: profile as ModuleProfile,
      description: (data.description as string) ?? "",
      scheduling: (data.scheduling as boolean) ?? false,
      navigation: (data.navigation as boolean) ?? true,
    };
  }
  return result;
}
