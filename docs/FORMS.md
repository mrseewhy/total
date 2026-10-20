# Reusable form pattern

Use the same small set of helpers for new forms. Keep business-specific inputs and validation in their own feature files; the shared code manages submission state and feedback.

## Shared pieces

| File | Responsibility |
| --- | --- |
| `src/forms/useForm.ts` | Busy/error/success/field state, duplicate-submission guard, invalid-field focus and validation issue mapping. |
| `src/components/forms/Form.tsx` | Form wrapper, field errors, form feedback, success boxes and submit buttons. |
| `src/api/response.ts` | One API error format, JSON/proxy error handling, status and Retry-After information. |
| `php/validation/ValidationException.php` | Named backend field errors. |
| `php/validation/Validator.php` | `field()` and `fields()` helpers for reusable backend validation. |

The pattern is used by contact/newsletter, login/recovery/reset, profile/password, user creation/editing, all registry-based content editors, metadata, media details/uploads and delete confirmations. Search/filter forms are separate read-only controls; they do not need success messages or mutation state.

## A new React form

```tsx
import { useForm, FormValidationError, issueFields } from '../forms/useForm';
import { Form, FieldError, FormFeedback, SubmitButton } from '../components/forms/Form';
import { cmsRequest } from '../api/content';
import { exampleSchema } from '../validation/example';

export default function ExampleForm() {
  const form = useForm();

  async function submit(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault();
    // Read values before controls are disabled during submission.
    const values = Object.fromEntries(new FormData(event.currentTarget));
    await form.submit(async () => {
      const parsed = exampleSchema.safeParse(values);
      if (!parsed.success) {
        throw new FormValidationError(issueFields(parsed.error.issues));
      }
      return cmsRequest('examples', 'POST', parsed.data);
    }, 'Saved successfully.');
  }

  return (
    <Form state={form} onSubmit={submit} className="space-y-5">
      <label className="block text-sm">
        Title
        <input name="title" required maxLength={200}
          {...form.attributes('title')} className="auth-input" />
        <FieldError state={form} name="title" />
      </label>
      <FormFeedback state={form} />
      <SubmitButton busy={form.busy} pendingLabel="Saving…">Save</SubmitButton>
    </Form>
  );
}
```

Change import paths for your feature. The example schema/endpoint are placeholders, not installed modules.

### Rules to follow

1. Give each control a `name` matching its API error key. For nested content use names such as `details.location`. Add `form.attributes(name)` and a matching `FieldError` beside the control. Use normal labels and native required/type/length constraints too.
2. Put frontend business validation and the actual request **inside `form.submit()`**. Throw `FormValidationError` for a field map or map Zod issues with `issueFields()`. This guard blocks re-entry even before React has rendered the busy state.
3. Use `FormFeedback` for API/global errors and success messages. Failed requests preserve entered values. Editing a field clears that field's error and stale success feedback; other field errors remain until corrected or revalidated. A form-level API error remains until the next attempt.
4. Use `form.submit(action, 'Success message')` when the form stays open. For contact/newsletter-style completion, switch to `SuccessMessage` only after the API succeeds. For a form/modal that closes or navigates, show the parent notice or a success toast so the feedback remains visible. Do not show the same error as both a toast and inline feedback.
5. The wrapper disables native controls and exposes `aria-busy` while pending. Custom widgets must honor busy state too; the rich-text editor uses `readOnly={busy}`. Keep cancel/close actions disabled while a write is pending.
6. Keep each independent form's state separate. Profile details and password changes, for example, each have their own `useForm` state.
7. Do not automatically retry writes. A timeout or lost connection can occur after a server saves the data. The shared message asks the user to check whether it was saved before retrying. The client guard is not server-side idempotency across tabs or devices.
8. Backend validation, CSRF, authorization and rate limiting still apply. Browser validation improves feedback but is not a security boundary.

Native browser invalid events are reflected into the same field-error state; business-validation failures focus the first invalid named control. Unmapped/custom-control failures fall back to the focusable form error summary. Password controls retain their existing visibility toggle and accessible labels.

## API responses

The existing endpoints retain their success shape:

```json
{"success": true, "message": "Saved successfully."}
```

Field validation returns HTTP 422 (or HTTP 409 for a duplicate value) with named errors:

```json
{
  "success": false,
  "message": "Please check your details.",
  "errors": {"email": "That email address is already in use."}
}
```

Generic errors can omit `errors`. Some backend validators stop at the first invalid field; others collect several. Both work with the same frontend. Protocol errors, authorization failures, honeypots and general business failures remain form-level messages. HTTP 429 may include `Retry-After`; it is shown as an approximate wait, not a running countdown. HTTP 500/proxy HTML is converted into a safe generic message. Session expiry handling and CSRF token rotation remain in the authentication API helper.

Use `cmsRequest`, `authAction`, or the feature API helper. A new public unauthenticated request can use `readApiResponse(await fetch(...))`; do not attach admin CSRF requirements to public submission endpoints.

## PHP field validation

```php
$clean = Validator::fields([
    'title' => function () use ($input) {
        $value = Validator::requiredString($input['title'] ?? null, 'Title is required.');
        Validator::maxLength($value, 200, 'Title is too long.');
        return $value;
    },
    'email' => fn() => AuthValidation::email($input),
]);
```

`fields()` collects invalid fields. `Validator::field('title', fn() => ...)` wraps a single check. Both throw `ValidationException`, a subtype of `InvalidArgumentException`, so existing callers remain compatible. The API entry point returns named errors through `validationResponse()`. Controllers that catch validation exceptions themselves must also call `validationResponse($error)` to preserve those fields. Never return submitted passwords or raw database errors.

## Verification

```sh
npm run test:forms
npm run test:security:e2e
npm run build
npm run lint
```

The integration security test requires the local servers and a development database; never use production credentials. Browser checks also verified native field errors, clearing only the changed error, focus for a byte-limit validation failure, and successful contact submission replacing the form with its thank-you box.
