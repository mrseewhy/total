import test from "node:test";
import assert from "node:assert/strict";
import {
  ApiError,
  formFailure,
  normalizeFieldErrors,
  readApiResponse,
} from "../src/api/response.ts";
import { profileSchema, resetSchema } from "../src/validation/auth.ts";
import { spawnSync } from "node:child_process";
function response(
  body: unknown,
  status = 200,
  headers: Record<string, string> = {},
) {
  return new Response(JSON.stringify(body), {
    status,
    headers: { "Content-Type": "application/json", ...headers },
  });
}
test("API parsing preserves field errors, conflict status and rate-limit timing", async () => {
  await assert.rejects(
    readApiResponse(
      response(
        {
          success: false,
          message: "Email already subscribed",
          errors: { email: ["Already subscribed."] },
        },
        409,
      ),
    ),
    (e: unknown) =>
      e instanceof ApiError &&
      e.status === 409 &&
      e.fields.email === "Already subscribed.",
  );
  await assert.rejects(
    readApiResponse(response({ success: false }, 429, { "Retry-After": "60" })),
    (e: unknown) =>
      e instanceof ApiError &&
      e.retryAfter === 60 &&
      e.message.includes("Too many"),
  );
  const date = new Date(Date.now() + 120000).toUTCString();
  await assert.rejects(
    readApiResponse(response({ success: false }, 429, { "Retry-After": date })),
    (e: unknown) =>
      e instanceof ApiError && e.retryAfter !== null && e.retryAfter > 115,
  );
});
test("HTML/proxy responses and server details become safe form-level errors", async () => {
  await assert.rejects(
    readApiResponse(
      new Response("<html>private stack trace</html>", { status: 502 }),
    ),
    (e: unknown) => e instanceof ApiError && !e.message.includes("private"),
  );
  await assert.rejects(
    readApiResponse(
      response({ success: false, message: "secret SQL details" }, 500),
    ),
    (e: unknown) => e instanceof ApiError && !e.message.includes("secret"),
  );
  await assert.rejects(readApiResponse(response(null)), ApiError);
  await assert.rejects(
    readApiResponse(response({ success: false }, 419)),
    (e: unknown) => e instanceof ApiError && e.message.includes("Reload"),
  );
  assert.deepEqual(
    await readApiResponse(response({ success: true, message: "Saved." })),
    { success: true, message: "Saved." },
  );
});
test("field normalization rejects invalid shapes and prototype keys", () => {
  const input = JSON.parse(
    '{"__proto__":"bad","constructor":"bad","email":["Enter email"],"details.location":"Required","bad":12}',
  );
  assert.deepEqual(normalizeFieldErrors(input), {
    email: "Enter email",
    "details.location": "Required",
  });
  assert.deepEqual(normalizeFieldErrors(["wrong"]), {});
});
test("timeouts and network failures do not encourage blind duplicate submissions", () => {
  assert.match(
    formFailure(new TypeError("fetch failed")).message,
    /whether.*saved/,
  );
  assert.match(
    formFailure(new DOMException("timeout", "TimeoutError")).message,
    /whether.*saved/,
  );
});
test("auth frontend validation handles byte limits and confirmation paths", () => {
  assert.equal(
    profileSchema.safeParse({ name: "界".repeat(34) }).success,
    false,
  );
  const mismatch = resetSchema.safeParse({
    password: "a".repeat(12),
    password_confirmation: "different",
  });
  assert.equal(mismatch.success, false);
  if (!mismatch.success)
    assert.equal(mismatch.error.issues[0].path[0], "password_confirmation");
});
test("PHP public validation returns multiple field errors without weakening honeypots", () => {
  const php = spawnSync(
    "php",
    [
      "-r",
      `require 'php/validation/ContactValidation.php';require 'php/validation/SubscriberValidation.php';try{ContactValidation::create(['name'=>'','email'=>'bad','interest'=>'invalid','message'=>'short','website'=>'']);exit(1);}catch(ValidationException $e){foreach(['name','email','interest','message'] as $key)if(!isset($e->errors[$key]))exit(2);}try{SubscriberValidation::create(['email'=>'bad','website'=>'bot']);exit(3);}catch(ValidationException){exit(4);}catch(InvalidArgumentException){} try{SubscriberValidation::create(['email'=>'bad']);exit(5);}catch(ValidationException $e){if(!isset($e->errors['email']))exit(6);}`,
    ],
    { encoding: "utf8" },
  );
  assert.equal(php.status, 0, php.stdout + php.stderr);
});

test("maintenance replies preserve the public message while malformed 503s remain safe", async () => {
  await assert.rejects(
    readApiResponse(
      response(
        { success: false, maintenance: true, message: "Planned maintenance." },
        503,
        { "Retry-After": "120" },
      ),
    ),
    (error: unknown) =>
      error instanceof ApiError &&
      error.message === "Planned maintenance." &&
      error.retryAfter === 120,
  );
  await assert.rejects(
    readApiResponse(new Response("unreadable gateway", { status: 503 })),
    (error: unknown) =>
      error instanceof ApiError && !error.message.includes("gateway"),
  );
});

test("a safe mail-unavailable response remains useful to the signed-in form", async () => {
  await assert.rejects(
    readApiResponse(
      response(
        {
          success: false,
          code: "mail_unavailable",
          message: "The verification email could not be sent.",
        },
        503,
      ),
    ),
    (error: unknown) =>
      error instanceof ApiError &&
      error.message === "The verification email could not be sent.",
  );
});
