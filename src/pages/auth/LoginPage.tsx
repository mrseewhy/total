import { Link, Navigate, useLocation, useNavigate } from "react-router";
import { useAuth } from "../../auth/context";
import AuthCard from "../../components/auth/AuthCard";
import PasswordField from "../../components/admin/PasswordField";
import {
  FieldError,
  Form,
  FormFeedback,
  SubmitButton,
} from "../../components/forms/Form";
import { FormValidationError, issueFields, useForm } from "../../forms/useForm";
import { authAction } from "../../api/auth";
import { loginSchema } from "../../validation/auth";
export default function LoginPage() {
  const { user, login, twoFactor, verifyTwoFactor, cancelTwoFactor } =
    useAuth();
  const navigate = useNavigate();
  const location = useLocation();
  const form = useForm();
  if (user) return <Navigate to="/site-admin" replace />;
  async function submit(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const values = Object.fromEntries(new FormData(event.currentTarget));
    await form.submit(async () => {
      if (twoFactor) {
        const code = String(values.code ?? "").trim();
        if (!/^[0-9]{6}$/.test(code))
          throw new FormValidationError({
            code: "Enter the six-digit code from your email.",
          });
        await verifyTwoFactor(code);
      } else {
        const parsed = loginSchema.safeParse(values);
        if (!parsed.success)
          throw new FormValidationError(issueFields(parsed.error.issues));
        const complete = await login(parsed.data.email, parsed.data.password);
        if (!complete) return;
      }
      const from = location.state?.from;
      navigate(
        typeof from === "string" && /^\/site-admin(?:\/|$)/.test(from)
          ? from
          : "/site-admin",
        { replace: true },
      );
    });
  }
  return (
    <AuthCard
      title={twoFactor ? "Check your email" : "Welcome back"}
      description={
        twoFactor
          ? "Enter the six-digit code sent to your account email. Complete verification within 10 minutes of entering your password."
          : "Sign in to manage your website."
      }
    >
      <Form state={form} onSubmit={submit} className="space-y-5">
        {twoFactor ? (
          <label className="block text-sm">
            Verification code
            <input
              name="code"
              type="text"
              inputMode="numeric"
              autoComplete="one-time-code"
              required
              pattern="[0-9]{6}"
              minLength={6}
              maxLength={6}
              {...form.attributes("code")}
              className="auth-input"
            />
            <FieldError state={form} name="code" />
          </label>
        ) : (
          <>
            {" "}
            <label className="block text-sm">
              Email address
              <input
                {...form.attributes("email")}
                className="auth-input"
                name="email"
                type="email"
                required
                maxLength={254}
                autoComplete="username"
              />
              <FieldError state={form} name="email" />
            </label>
            <PasswordField
              name="password"
              label="Password"
              required
              autoComplete="current-password"
              error={form.errors.password}
              onChange={() => form.clear("password")}
            />
          </>
        )}
        <FormFeedback state={form} />
        <SubmitButton
          busy={form.busy}
          pendingLabel={twoFactor ? "Verifying…" : "Signing in…"}
        >
          {twoFactor ? "Verify and sign in" : "Sign in"}
        </SubmitButton>
        {twoFactor && (
          <div className="flex justify-between gap-3 text-xs">
            <button
              type="button"
              disabled={form.busy}
              className="underline"
              onClick={() =>
                void form.submit(
                  () => authAction("two-factor/resend"),
                  (r) => r.message,
                )
              }
            >
              Send a new code
            </button>
            <button
              type="button"
              disabled={form.busy}
              className="underline"
              onClick={() =>
                void form.submit(async () => {
                  await cancelTwoFactor();
                  form.setMessage("");
                })
              }
            >
              Back to sign in
            </button>
          </div>
        )}
        <Link
          to="/site-admin/forgot-password"
          className="block text-center text-xs text-gray-500 underline"
        >
          Forgot your password?
        </Link>
      </Form>
    </AuthCard>
  );
}
