import { useState } from "react";
import { useAuth } from "../../auth/context";
import { authAction } from "../../api/auth";
import { useForm, FormValidationError } from "../../forms/useForm";
import { Form, FormFeedback, FieldError, SubmitButton } from "../forms/Form";
import PasswordField from "./PasswordField";
export default function TwoFactorSettings() {
  const { user, setUser } = useAuth();
  const form = useForm();
  const [pending, setPending] = useState<boolean | null>(null);
  const [selected, setSelected] = useState(user?.two_factor_enabled ?? false);
  const enabled = !!user?.two_factor_enabled;
  async function submit(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const element = event.currentTarget;
    const values = new FormData(element);
    await form.submit(
      async () => {
        if (pending !== null) {
          const code = String(values.get("code") ?? "").trim();
          if (!/^[0-9]{6}$/.test(code))
            throw new FormValidationError({
              code: "Enter the six-digit code from your email.",
            });
          const result = await authAction("two-factor/verify-setting", {
            code,
          });
          setUser(result.user);
          setSelected(result.user.two_factor_enabled);
          setPending(null);
          element.reset();
          return result;
        }
        if (selected === enabled)
          throw new FormValidationError({
            enabled: "Change the checkbox to update this setting.",
          });
        const password = String(values.get("current_password") ?? "");
        if (!password || new TextEncoder().encode(password).length > 72)
          throw new FormValidationError({
            current_password: "Enter your current password.",
          });
        const result = await authAction("two-factor/request-setting", {
          enabled: selected,
          current_password: password,
        });
        setPending(selected);
        element.reset();
        return result;
      },
      (r) => r.message,
    );
  }
  return (
    <Form
      state={form}
      onSubmit={submit}
      className="space-y-5 rounded-xl border border-gray-200 bg-white p-6"
    >
      <div>
        <h2 className="font-semibold">Email two-factor authentication</h2>
        <p className="mt-2 text-sm leading-6 text-gray-500">
          Optional extra protection for your account. When enabled, each sign-in
          requires your password and a code sent to your email.
        </p>
        <p className="mt-3 text-xs font-medium">
          Currently {enabled ? "enabled" : "disabled"}
        </p>
      </div>
      {pending !== null ? (
        <>
          <p className="text-sm text-gray-600">
            Enter the code sent to your account email to{" "}
            {pending ? "enable" : "disable"} two-factor authentication.
          </p>
          <label className="block text-sm">
            Verification code
            <input
              name="code"
              inputMode="numeric"
              autoComplete="one-time-code"
              required
              minLength={6}
              maxLength={6}
              pattern="[0-9]{6}"
              {...form.attributes("code")}
              className="auth-input"
            />
            <FieldError state={form} name="code" />
          </label>
        </>
      ) : (
        <>
          <label className="flex items-start gap-3 text-sm">
            <input
              name="enabled"
              type="checkbox"
              checked={selected}
              onChange={(e) => setSelected(e.target.checked)}
              {...form.attributes("enabled")}
              className="mt-1"
            />
            <span>Require an email code when I sign in</span>
          </label>
          <FieldError state={form} name="enabled" />
          <PasswordField
            name="current_password"
            label="Current password"
            required
            autoComplete="current-password"
            error={form.errors.current_password}
            onChange={() => form.clear("current_password")}
          />
        </>
      )}
      <FormFeedback state={form} />
      <SubmitButton
        busy={form.busy}
        pendingLabel={pending !== null ? "Verifying…" : "Sending code…"}
      >
        {pending !== null ? "Confirm change" : "Save two-factor setting"}
      </SubmitButton>
      {pending !== null && (
        <button
          type="button"
          disabled={form.busy}
          className="text-xs underline"
          onClick={() => {
            setPending(null);
            form.setError("");
            form.setMessage("");
            form.setErrors({});
          }}
        >
          Start again
        </button>
      )}
    </Form>
  );
}
