import { Link } from "react-router";
import { authAction } from "../../api/auth";
import AuthCard from "../../components/auth/AuthCard";
import {
  FieldError,
  Form,
  FormFeedback,
  SubmitButton,
  SuccessMessage,
} from "../../components/forms/Form";
import { FormValidationError, issueFields, useForm } from "../../forms/useForm";
import { forgotSchema } from "../../validation/auth";
export default function ForgotPasswordPage() {
  const form = useForm();
  async function submit(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const values = Object.fromEntries(new FormData(event.currentTarget));
    await form.submit(
      async () => {
        const parsed = forgotSchema.safeParse(values);
        if (!parsed.success)
          throw new FormValidationError(issueFields(parsed.error.issues));
        return authAction("forgot-password", parsed.data);
      },
      (r) => r.message,
    );
  }
  return (
    <AuthCard
      title="Forgot your password?"
      description="Enter your email and we'll send instructions to reset your password."
    >
      {form.message ? (
        <SuccessMessage>{form.message}</SuccessMessage>
      ) : (
        <Form state={form} onSubmit={submit} className="space-y-5">
          <label className="block text-sm">
            Email address
            <input
              {...form.attributes("email")}
              name="email"
              type="email"
              required
              maxLength={254}
              autoComplete="email"
              className="auth-input"
            />
            <FieldError state={form} name="email" />
          </label>
          <FormFeedback state={form} />
          <SubmitButton busy={form.busy} pendingLabel="Sending…">
            Send reset link
          </SubmitButton>
        </Form>
      )}
      <Link
        to="/site-admin/login"
        className="mt-6 block text-center text-xs text-gray-500 underline"
      >
        Back to sign in
      </Link>
    </AuthCard>
  );
}
