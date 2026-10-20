import test from "node:test";
import assert from "node:assert/strict";
import { spawnSync } from "node:child_process";
import {
  mkdtempSync,
  cpSync,
  mkdirSync,
  readFileSync,
  readdirSync,
  rmSync,
  statSync,
} from "node:fs";
import { tmpdir } from "node:os";
import { join, resolve } from "node:path";
const cli = resolve("php/console/cli.php");
test("installation URLs reject unsafe origins and hosted HTTP", () => {
  const r = spawnSync(
    "php",
    [
      "-r",
      `require $argv[1]; foreach (['https://example.com/','http://localhost:5173'] as $u) installationUrl($u); foreach (['http://example.com','https://user:pass@example.com','https://example.com/path','javascript:alert(1)','https://example.com?x=1'] as $u) {try {installationUrl($u);exit(1);}catch(InvalidArgumentException){}}`,
      cli,
    ],
    { encoding: "utf8" },
  );
  assert.equal(r.status, 0, r.stderr);
});
test("private config writer preserves existing files and restricts permissions", () => {
  const folder = mkdtempSync(join(tmpdir(), "total-cli-"));
  try {
    const file = join(folder, "private.php");
    const r = spawnSync(
      "php",
      [
        "-r",
        `require $argv[1];writePrivateConfig($argv[2],['password'=>"test'quote"]);try{writePrivateConfig($argv[2],['password'=>'overwritten']);exit(1);}catch(RuntimeException){} $c=require $argv[2];if($c['password']!=="test'quote")exit(2);`,
        cli,
        file,
      ],
      { encoding: "utf8" },
    );
    assert.equal(r.status, 0, r.stderr);
    assert.equal(statSync(file).mode & 0o077, 0);
  } finally {
    rmSync(folder, { recursive: true, force: true });
  }
});
test("health check reports missing setup as JSON without creating files", () => {
  const folder = mkdtempSync(join(tmpdir(), "total-health-"));
  try {
    mkdirSync(join(folder, "php/commands"), { recursive: true });
    cpSync("php/console", join(folder, "php/console"), { recursive: true });
    cpSync(
      "php/commands/health-check.php",
      join(folder, "php/commands/health-check.php"),
    );
    const snapshot = readFileSync(
      join(folder, "php/commands/health-check.php"),
      "utf8",
    );
    const r = spawnSync(
      "php",
      [join(folder, "php/commands/health-check.php"), "--json"],
      { encoding: "utf8" },
    );
    assert.equal(r.status, 1);
    const report = JSON.parse(r.stdout);
    assert.equal(report.ok, false);
    assert.ok(
      report.checks.some(
        (c: { name: string; status: string }) =>
          c.name === "database-config" && c.status === "fail",
      ),
    );
    assert.deepEqual(readdirSync(join(folder, "php")).sort(), [
      "commands",
      "console",
    ]);
    assert.equal(
      readFileSync(join(folder, "php/commands/health-check.php"), "utf8"),
      snapshot,
    );
  } finally {
    rmSync(folder, { recursive: true, force: true });
  }
});
