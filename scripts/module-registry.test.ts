import { spawnSync } from "node:child_process";
import { test } from "node:test";
import assert from "node:assert/strict";
import { validateModuleRegistry } from "../src/content/moduleRegistry.ts";
import { mkdtempSync, mkdirSync, writeFileSync, rmSync } from "node:fs";
import { join } from "node:path";
import { tmpdir } from "node:os";
import { writePublicManifest } from "./public-pages.ts";

test("a basic module gets shared defaults without feature mappings", () => {
  const result = validateModuleRegistry({
    testimonials: {
      label: "Testimonials",
      singular: "testimonial",
      path: "testimonials",
    },
  });
  assert.equal(result.testimonials.profile, "content");
  assert.equal(result.testimonials.icon, "FileText");
  assert.equal(result.testimonials.scheduling, false);
  assert.equal(result.testimonials.navigation, true);
});
test("rejects invalid identifiers, private/colliding paths, and unsafe options", () => {
  const basic = { label: "Items", singular: "item", path: "items" };
  for (const value of [
    [],
    null,
    { users: basic },
    { "bad;sql": basic },
    { items: { ...basic, path: "site-admin" } },
    { one: basic, two: basic },
    { items: { ...basic, scheduling: "false" } },
    { items: { ...basic, profile: "arbitrary" } },
    { items: { ...basic, icon: "unknown" } },
    { items: { ...basic, secret: "value" } },
  ])
    assert.throws(() => validateModuleRegistry(value));
});
test("the build manifest includes module listings and rejects static-route collisions", () => {
  const root = mkdtempSync(join(tmpdir(), "total-modules-"));
  mkdirSync(join(root, "src/routes"), { recursive: true });
  mkdirSync(join(root, "php/config"), { recursive: true });
  try {
    writeFileSync(
      join(root, "src/routes/publicRoutes.tsx"),
      "export const publicRoutes = [{index:true}];",
    );
    writeFileSync(
      join(root, "php/config/content-modules.json"),
      JSON.stringify({
        testimonials: {
          label: "Testimonials",
          singular: "testimonial",
          path: "testimonials",
        },
      }),
    );
    assert.equal(
      writePublicManifest(root).pages["/testimonials"].label,
      "Testimonials listing",
    );
    writeFileSync(
      join(root, "src/routes/publicRoutes.tsx"),
      'export const publicRoutes = [{path:"testimonials"}];',
    );
    assert.throws(() => writePublicManifest(root), /conflicts/);
  } finally {
    rmSync(root, { recursive: true, force: true });
  }
});

test("PHP and React normalize the same module definition", (t) => {
  const raw = {
    testimonials: {
      label: "Testimonials",
      singular: "testimonial",
      path: "testimonials",
      icon: "MessageSquare",
    },
  };
  const result = spawnSync(
    "php",
    [
      "-r",
      'require "php/config/ContentModules.php";echo json_encode(ContentModules::validate(json_decode($argv[1],true)));',
      JSON.stringify(raw),
    ],
    { encoding: "utf8" },
  );
  if (
    result.error &&
    "code" in result.error &&
    result.error.code === "ENOENT"
  ) {
    t.skip("PHP is unavailable");
    return;
  }
  assert.equal(result.status, 0, result.stderr);
  assert.deepEqual(JSON.parse(result.stdout), validateModuleRegistry(raw));
});
