import test from "node:test";
import assert from "node:assert/strict";
import {
  mkdtempSync,
  mkdirSync,
  copyFileSync,
  writeFileSync,
  readFileSync,
  rmSync,
  existsSync,
} from "node:fs";
import { tmpdir } from "node:os";
import { join, resolve } from "node:path";
import { spawnSync } from "node:child_process";
function fixture() {
  const root = mkdtempSync(join(tmpdir(), "total-mail-"));
  const php = join(root, "php");
  mkdirSync(join(php, "functions"), { recursive: true });
  for (const file of [
    "mail.config.php",
    "auth.config.php",
    "functions/mail.php",
  ])
    copyFileSync(resolve("php", file), join(php, file));
  writeFileSync(
    join(php, "auth.config.local.php"),
    '<?php return ["app_url"=>"https://site.example","mail_from"=>"legacy@example.com"];',
  );
  const configure = (value: Record<string, unknown>) =>
    writeFileSync(
      join(php, "mail.config.local.php"),
      "<?php return json_decode(" +
        JSON.stringify(JSON.stringify(value)) +
        ",true);",
    );
  const run = (code: string) =>
    spawnSync(
      "php",
      [
        "-d",
        "disable_functions=mail",
        "-r",
        code,
        php,
        join(root, "capture.json"),
      ],
      {
        encoding: "utf8",
        env: {
          ...process.env,
          MAIL_ENABLED: "0",
          MAIL_FROM: "",
          MAIL_FROM_NAME: "",
          MAIL_REPLY_TO: "",
        },
      },
    );
  return {
    root,
    php,
    configure,
    run,
    close: () => rmSync(root, { recursive: true, force: true }),
  };
}
test("mail defaults are disabled and preserve the legacy sender fallback", () => {
  const f = fixture();
  try {
    const r = f.run(
      'require $argv[1]."/functions/mail.php";$c=mailConfiguration();echo json_encode($c);',
    );
    assert.equal(r.status, 0, r.stderr);
    const c = JSON.parse(r.stdout);
    assert.equal(c.enabled, false);
    assert.equal(c.from_address, "legacy@example.com");
    assert.equal(c.from_name, "Haven");
  } finally {
    f.close();
  }
});
test("reset messages use configured branding, MIME headers and trusted URL", () => {
  const f = fixture();
  try {
    f.configure({
      enabled: true,
      from_address: "noreply@example.com",
      from_name: "Havén Homes",
      reply_to: "support@example.com",
    });
    const r = f.run(
      'function mail($to,$subject,$body,$headers){file_put_contents($GLOBALS["argv"][2],json_encode(compact("to","subject","body","headers")));return true;}require $argv[1]."/functions/mail.php";$_SERVER["HTTP_HOST"]="attacker.example";sendResetEmail("recipient@example.com",str_repeat("a",64));',
    );
    assert.equal(r.status, 0, r.stdout + r.stderr);
    const m = JSON.parse(readFileSync(join(f.root, "capture.json"), "utf8"));
    assert.equal(m.to, "recipient@example.com");
    assert.equal(m.headers["Reply-To"], "support@example.com");
    assert.equal(m.headers["Content-Type"], "text/plain; charset=UTF-8");
    assert.equal(m.headers["Content-Transfer-Encoding"], "quoted-printable");
    assert.ok(m.headers.From.includes("<noreply@example.com>"));
    const decoded = spawnSync(
      "php",
      [
        "-r",
        "echo json_encode([mb_decode_mimeheader($argv[1]),quoted_printable_decode($argv[2])]);",
        m.subject,
        m.body,
      ],
      { encoding: "utf8" },
    );
    assert.equal(decoded.status, 0, decoded.stderr);
    const [subject, body] = JSON.parse(decoded.stdout);
    assert.equal(subject, "Reset your Havén Homes password");
    assert.match(
      body,
      /https:\/\/site\.example\/site-admin\/reset-password\?token=a{64}/,
    );
    assert.ok(!body.includes("attacker.example"));
    assert.ok(!body.includes("Total"));
  } finally {
    f.close();
  }
});
test("disabled mail, unavailable function and rejected transport fail without sending", () => {
  const f = fixture();
  try {
    const cases = [
      {
        enabled: false,
        code: 'sendSiteMail("recipient@example.com","Test","Body");',
      },
      {
        enabled: true,
        code: 'sendSiteMail("recipient@example.com","Test","Body");',
      },
    ];
    for (const c of cases) {
      f.configure({ enabled: c.enabled, from_address: "noreply@example.com" });
      const r = f.run(
        'require $argv[1]."/functions/mail.php";try{' +
          c.code +
          'exit(1);}catch(RuntimeException){echo "rejected";}',
      );
      assert.equal(r.status, 0, r.stderr);
      assert.equal(r.stdout, "rejected");
    }
    f.configure({ enabled: true, from_address: "noreply@example.com" });
    const r = f.run(
      'function mail($a,$b,$c,$d){return false;}require $argv[1]."/functions/mail.php";try{sendSiteMail("recipient@example.com","Test","Body");exit(1);}catch(RuntimeException){echo "rejected";}',
    );
    assert.equal(r.status, 0, r.stderr);
    assert.equal(r.stdout, "rejected");
    assert.equal(existsSync(join(f.root, "capture.json")), false);
  } finally {
    f.close();
  }
});
test("header injection and unsafe reset origins are rejected before mail handoff", () => {
  const f = fixture();
  try {
    const injections = [
      { from_address: "noreply@example.com\r\nBcc: victim@example.com" },
      { from_name: "Haven\nBcc: victim@example.com" },
      { reply_to: "reply@example.com\r\nX-Evil: yes" },
    ];
    for (const injection of injections) {
      f.configure({
        enabled: true,
        from_address: "noreply@example.com",
        ...injection,
      });
      const r = f.run(
        'function mail($a,$b,$c,$d){exit(9);}require $argv[1]."/functions/mail.php";try{sendSiteMail("recipient@example.com","Test","Body");exit(1);}catch(RuntimeException){}',
      );
      assert.equal(r.status, 0, r.stderr);
    }
    f.configure({ enabled: true, from_address: "noreply@example.com" });
    for (const code of [
      'sendSiteMail("recipient@example.com\\r\\nBcc: victim@example.com","Test","Body");',
      'sendSiteMail("recipient@example.com","Test\\r\\nBcc: victim@example.com","Body");',
      'sendResetEmail("recipient@example.com","invalid-token");',
    ]) {
      const r = f.run(
        'function mail($a,$b,$c,$d){exit(9);}require $argv[1]."/functions/mail.php";try{' +
          code +
          "exit(1);}catch(InvalidArgumentException){}",
      );
      assert.equal(r.status, 0, r.stderr);
    }
    writeFileSync(
      join(f.php, "auth.config.local.php"),
      '<?php return ["app_url"=>"https://site.example/path?leak=yes"];',
    );
    const r = f.run(
      'function mail($a,$b,$c,$d){exit(9);}require $argv[1]."/functions/mail.php";try{sendResetEmail("recipient@example.com",str_repeat("a",64));exit(1);}catch(RuntimeException){}',
    );
    assert.equal(r.status, 0, r.stderr);
  } finally {
    f.close();
  }
});

test("two-factor mail uses a six-digit, purpose-specific message without leaking reset tokens", () => {
  const f = fixture();
  try {
    f.configure({
      enabled: true,
      from_address: "noreply@example.com",
      from_name: "Haven",
    });
    const r = f.run(
      'function mail($to,$subject,$body,$headers){file_put_contents($GLOBALS["argv"][2],json_encode(compact("to","subject","body","headers")));return true;}require $argv[1]."/functions/mail.php";sendTwoFactorEmail("recipient@example.com","123456","enable");',
    );
    assert.equal(r.status, 0, r.stderr);
    const message = JSON.parse(
      readFileSync(join(f.root, "capture.json"), "utf8"),
    );
    const decoded = spawnSync(
      "php",
      ["-r", "echo quoted_printable_decode($argv[1]);", message.body],
      { encoding: "utf8" },
    );
    assert.match(decoded.stdout, /123456/);
    assert.match(decoded.stdout, /enable email two-factor authentication/);
    assert.match(decoded.stdout, /10 minutes/);
  } finally {
    f.close();
  }
});
