import definitions from "../../php/config/content-modules.json";
import { validateModuleRegistry } from "./moduleRegistry";
export type ContentKind = keyof typeof definitions;
const registry = validateModuleRegistry(definitions);
export const contentModules = Object.entries(registry).map(([id, value]) => ({
  id: id as ContentKind,
  ...value,
}));
export function contentModule(kind: ContentKind) {
  const module = registry[kind];
  if (!module) throw new Error(`Unknown content module: ${kind}`);
  return module;
}
