import { test } from "node:test";
import assert from "node:assert/strict";
import { spawnSync } from "node:child_process";
import { escapeCsvCell } from "../src/utils/exportCsv.ts";

test("CSV cells are quoted and formula/control prefixes are neutralized", () => {
  for (const value of [
    "=1+1",
    "+1",
    "-1",
    "@SUM(A1)",
    "\t=1",
    "\r=1",
    "\n=1",
    "  =1",
    "＝1",
    "＋1",
    "－1",
    "＠SUM(A1)",
  ])
    assert.ok(escapeCsvCell(value).startsWith("\"'"), JSON.stringify(value));
  assert.equal(escapeCsvCell("Ordinary text"), '"Ordinary text"');
  assert.equal(escapeCsvCell('Name, "quoted"'), '"Name, ""quoted"""');
});
test("PHP and React protect the same CSV values", (t) => {
  const values = ["=1", " \n=1", "\ntext", "＠SUM(A1)", "ordinary", 'a,"b"'];
  const result = spawnSync(
    "php",
    [
      "-r",
      'require "php/functions/csv.php";echo json_encode(array_map("csvCell",json_decode($argv[1],true)));',
      JSON.stringify(values),
    ],
    { encoding: "utf8" },
  );
  if (
    result.error &&
    "code" in result.error &&
    result.error.code === "ENOENT"
  ) {
    t.skip("PHP unavailable");
    return;
  }
  assert.equal(result.status, 0, result.stderr);
  const actual = JSON.parse(result.stdout) as string[];
  actual.forEach((value, index) =>
    assert.equal(
      escapeCsvCell(values[index]),
      `"${value.replaceAll('"', '""')}"`,
    ),
  );
});
test("public rich-text renderer escapes markup and discards script links", (t) => {
  const content = [
    {
      type: "paragraph",
      content: [
        { type: "text", text: "<img src=x onerror=alert(1)>", styles: {} },
      ],
    },
    {
      type: "paragraph",
      content: [
        {
          type: "link",
          href: "javascript:alert(1)",
          content: [{ type: "text", text: "unsafe", styles: {} }],
        },
      ],
    },
  ];
  const result = spawnSync(
    "php",
    [
      "-r",
      'require "php/seo/blocks.php";echo seoBlocks(json_decode($argv[1],true));',
      JSON.stringify(content),
    ],
    { encoding: "utf8" },
  );
  if (
    result.error &&
    "code" in result.error &&
    result.error.code === "ENOENT"
  ) {
    t.skip("PHP unavailable");
    return;
  }
  assert.equal(result.status, 0, result.stderr);
  assert.ok(result.stdout.includes("&lt;img"));
  assert.ok(!result.stdout.includes("<img"));
  assert.ok(!result.stdout.includes("javascript:"));
});
