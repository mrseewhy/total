import { validateModuleRegistry } from "../src/content/moduleRegistry.ts";
import ts from "typescript";
import {
  existsSync,
  mkdirSync,
  readFileSync,
  rmSync,
  writeFileSync,
  renameSync,
} from "node:fs";
import { dirname, resolve } from "node:path";
import type { Plugin } from "vite";

type Page = {
  label: string;
  title?: string;
  description?: string;
  heading?: string;
  schema?: string;
  noindex?: boolean;
};
export type PublicManifest = { version: 1; pages: Record<string, Page> };
const privatePath = /^\/(?:site-admin|admin|dashboard|api)(?:\/|$)/i;
const textKeys = ["label", "title", "description", "heading", "schema"];
function object(node: ts.Node | undefined): Map<string, ts.Expression> {
  if (!node || !ts.isObjectLiteralExpression(node))
    throw new Error("Public routes and handle.seo must use literal objects.");
  const result = new Map<string, ts.Expression>();
  for (const member of node.properties) {
    if (
      !ts.isPropertyAssignment(member) ||
      !(ts.isIdentifier(member.name) || ts.isStringLiteral(member.name))
    )
      throw new Error(
        "Public route properties must be explicit; spreads/computed keys are not supported.",
      );
    if (result.has(member.name.text))
      throw new Error(`Duplicate route property: ${member.name.text}`);
    result.set(member.name.text, member.initializer);
  }
  return result;
}
function text(node: ts.Node, name: string): string {
  if (!ts.isStringLiteral(node) && !ts.isNoSubstitutionTemplateLiteral(node))
    throw new Error(`${name} must be a literal string in publicRoutes.tsx.`);
  return node.text;
}
function defaults(node: ts.Node | undefined): Partial<Page> {
  if (!node) return {};
  const seo = object(node).get("seo");
  if (!seo) return {};
  const output: Record<string, string | boolean> = {};
  for (const [key, value] of object(seo)) {
    if (key === "noindex") {
      if (
        value.kind !== ts.SyntaxKind.TrueKeyword &&
        value.kind !== ts.SyntaxKind.FalseKeyword
      )
        throw new Error("seo.noindex must be a literal boolean.");
      output[key] = value.kind === ts.SyntaxKind.TrueKeyword;
    } else if (textKeys.includes(key)) output[key] = text(value, `seo.${key}`);
    else throw new Error(`Unknown handle.seo field: ${key}`);
  }
  if (
    output.schema &&
    !["WebPage", "AboutPage", "ContactPage"].includes(String(output.schema))
  )
    throw new Error(
      "Static page schema must be WebPage, AboutPage, or ContactPage.",
    );
  return output;
}
export function parsePublicRoutes(source: string): PublicManifest {
  const diagnostics =
    ts.transpileModule(source, {
      fileName: "publicRoutes.tsx",
      reportDiagnostics: true,
      compilerOptions: { jsx: ts.JsxEmit.ReactJSX },
    }).diagnostics ?? [];
  if (diagnostics.some((item) => item.category === ts.DiagnosticCategory.Error))
    throw new Error(
      "Cannot generate metadata: publicRoutes.tsx has a syntax error.",
    );
  const file = ts.createSourceFile(
    "publicRoutes.tsx",
    source,
    ts.ScriptTarget.Latest,
    true,
    ts.ScriptKind.TSX,
  );
  let routes: ts.Expression | undefined;
  for (const statement of file.statements)
    if (ts.isVariableStatement(statement)) {
      for (const declaration of statement.declarationList.declarations)
        if (
          ts.isIdentifier(declaration.name) &&
          declaration.name.text === "publicRoutes"
        )
          routes = declaration.initializer;
    }
  if (!routes || !ts.isArrayLiteralExpression(routes))
    throw new Error(
      "Export publicRoutes as a literal array in src/routes/publicRoutes.tsx.",
    );
  const pages: Record<string, Page> = {};
  function walk(
    array: ts.ArrayLiteralExpression,
    parent: string,
    inheritedNoindex = false,
  ) {
    for (const node of array.elements) {
      const fields = object(node);
      const segment = fields.has("path")
        ? text(fields.get("path")!, "Route path")
        : "";
      const index = fields.get("index");
      if (
        index &&
        index.kind !== ts.SyntaxKind.TrueKeyword &&
        index.kind !== ts.SyntaxKind.FalseKeyword
      )
        throw new Error("Route index must be a literal boolean.");
      if (
        segment.startsWith("/") &&
        parent !== "/" &&
        !segment.startsWith(parent + "/") &&
        segment !== parent
      )
        throw new Error(
          `Absolute child route ${segment} must remain under ${parent}.`,
        );
      const path =
        (segment.startsWith("/") ? segment : `${parent}/${segment}`)
          .replace(/\/+/g, "/")
          .replace(/\/$/, "") || "/";
      const seo = defaults(fields.get("handle"));
      const noindex = inheritedNoindex || seo.noindex === true;
      const children = fields.get("children");
      if (children) {
        if (!ts.isArrayLiteralExpression(children))
          throw new Error("Route children must be a literal array.");
        walk(children, path, noindex);
      }
      if (
        privatePath.test(path) ||
        /[:*?]/.test(path) ||
        !(index?.kind === ts.SyntaxKind.TrueKeyword || fields.has("path"))
      )
        continue;
      if (
        !/^\/(?:[a-zA-Z0-9._~-]+(?:\/[a-zA-Z0-9._~-]+)*)?$/.test(path) ||
        path.split("/").some((part) => [".", ".."].includes(part)) ||
        path.length > 200
      )
        throw new Error(`Unsupported public URL: ${path}`);
      // A layout with children has a page only if it has an index child.
      if (children) continue;
      if (pages[path]) throw new Error(`Duplicate public page: ${path}`);
      const label =
        path === "/"
          ? "Home"
          : path
              .split("/")
              .at(-1)!
              .replace(/[-_]/g, " ")
              .replace(/\b\w/g, (value) => value.toUpperCase());
      pages[path] = { label, ...seo, ...(noindex ? { noindex: true } : {}) };
    }
  }
  walk(routes, "/");
  return { version: 1, pages };
}
export function writePublicManifest(root: string): PublicManifest {
  const output = resolve(root, "public/route-manifest.json");
  try {
    const manifest = parsePublicRoutes(
      readFileSync(resolve(root, "src/routes/publicRoutes.tsx"), "utf8"),
    );
    const modules = validateModuleRegistry(
      JSON.parse(
        readFileSync(resolve(root, "php/config/content-modules.json"), "utf8"),
      ),
    );
    for (const module of Object.values(modules)) {
      const path = "/" + module.path;
      if (manifest.pages[path])
        throw new Error(`Content module conflicts with static page: ${path}`);
      manifest.pages[path] = { label: module.label + " listing" };
    }
    const json = JSON.stringify(manifest, null, 2) + "\n";
    mkdirSync(dirname(output), { recursive: true });
    if (!existsSync(output) || readFileSync(output, "utf8") !== json) {
      const temporary = resolve(
        dirname(output),
        `.route-manifest.${process.pid}.tmp`,
      );
      writeFileSync(temporary, json);
      renameSync(temporary, output);
    }
    return manifest;
  } catch (error) {
    // Never leave removed/private routes published through a stale manifest.
    rmSync(output, { force: true });
    throw error;
  }
}
export function publicPagesPlugin(): Plugin {
  let root = "";
  return {
    name: "total-public-pages",
    configResolved(config) {
      root = config.root;
      writePublicManifest(root);
    },
    handleHotUpdate(context) {
      if (
        [
          resolve(root, "src/routes/publicRoutes.tsx"),
          resolve(root, "php/config/content-modules.json"),
        ].includes(context.file)
      )
        writePublicManifest(root);
    },
    configureServer(server) {
      server.watcher.on("unlink", (file) => {
        if (
          [
            resolve(root, "src/routes/publicRoutes.tsx"),
            resolve(root, "php/config/content-modules.json"),
          ].includes(file)
        )
          rmSync(resolve(root, "public/route-manifest.json"), { force: true });
      });
    },
  };
}
