import { test } from "node:test";
import assert from "node:assert/strict";
import {
  mkdtempSync,
  mkdirSync,
  readFileSync,
  rmSync,
  writeFileSync,
  existsSync,
} from "node:fs";
import { tmpdir } from "node:os";
import { join } from "node:path";
import { parsePublicRoutes, writePublicManifest } from "./public-pages.ts";

test("discovers index, nested, and absolute public URLs without executing components", () => {
  const manifest = parsePublicRoutes(`export const publicRoutes = [
    { index: true, element: dangerousFunction() },
    { path: "services", element: <Services /> },
    { path: "company", children: [
      { index: true, element: <Overview /> },
      { path: "our-team", element: <Team /> },
      { path: "/company/history", element: <History /> }
    ] },
    { children: [{path: "contact", element: <Contact />}] }
  ];`);
  assert.deepEqual(Object.keys(manifest.pages), [
    "/",
    "/services",
    "/company",
    "/company/our-team",
    "/company/history",
    "/contact",
  ]);
  assert.equal(manifest.pages["/company/our-team"].label, "Our Team");
});

test("omits protected paths, parameters, wildcards, and unaddressable layout-only nodes", () => {
  const manifest = parsePublicRoutes(`export const publicRoutes = [
    {path:"site-admin", children:[{path:"secret",element:<Secret/>}]},
    {path:"/admin",element:<Admin/>},
    {path:"/dashboard",element:<Dashboard/>},
    {path:"/api/status",element:<Status/>},
    {path:"posts/:slug",element:<Post/>},
    {path:"products/:id/reviews",element:<Reviews/>},
    {path:"*",element:<Missing/>},
    {path:"layout-only",children:[]},
    {path:"public",element:<Page/>}
  ];`);
  assert.deepEqual(Object.keys(manifest.pages), ["/public"]);
});

test("keeps optional defaults and inherits a layout's noindex restriction", () => {
  const manifest = parsePublicRoutes(`export const publicRoutes = [
    {path:"services",element:<Services/>,handle:{seo:{label:"Our services",title:"Services | {siteName}",description:"What we do",schema:"WebPage"}}},
    {path:"hidden",handle:{seo:{noindex:true}},children:[{path:"thanks",element:<Thanks/>,handle:{seo:{noindex:false}}}]}
  ];`);
  assert.equal(manifest.pages["/services"].title, "Services | {siteName}");
  assert.equal(manifest.pages["/hidden/thanks"].noindex, true);
});

test("rejects ambiguous or computed declarations instead of guessing routes", () => {
  for (const source of [
    "export const publicRoutes = makeRoutes();",
    "export const publicRoutes = [{path: routeName}];",
    "export const publicRoutes = [...otherRoutes];",
    'export const publicRoutes = [{path:"a"},{path:"a"}];',
    'export const publicRoutes = [{path:"../outside"}];',
    'export const publicRoutes = [{path:"a",handle:{seo:{noindex:flag}}}];',
    'export const publicRoutes = [{path:"a",children:[{path:"/escape"}]}];',
  ])
    assert.throws(() => parsePublicRoutes(source));
});

test("regeneration removes deleted pages and invalid input clears stale output", () => {
  const root = mkdtempSync(join(tmpdir(), "total-routes-"));
  const source = join(root, "src/routes/publicRoutes.tsx");
  const output = join(root, "public/route-manifest.json");
  mkdirSync(join(root, "src/routes"), { recursive: true });
  mkdirSync(join(root, "php/config"), { recursive: true });
  writeFileSync(join(root, "php/config/content-modules.json"), "{}");
  try {
    writeFileSync(source, 'export const publicRoutes = [{path:"services"}];');
    writePublicManifest(root);
    assert.ok(JSON.parse(readFileSync(output, "utf8")).pages["/services"]);
    writeFileSync(source, 'export const publicRoutes = [{path:"contact"}];');
    writePublicManifest(root);
    assert.deepEqual(
      Object.keys(JSON.parse(readFileSync(output, "utf8")).pages),
      ["/contact"],
    );
    writeFileSync(source, "export const publicRoutes = compute();");
    assert.throws(() => writePublicManifest(root));
    assert.equal(existsSync(output), false);
  } finally {
    rmSync(root, { recursive: true, force: true });
  }
});
