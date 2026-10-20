import { Link, useSearchParams } from "react-router";
import { useAuth } from "../../auth/context";
import { authAction } from "../../api/auth";
import AuthCard from "../../components/auth/AuthCard";
import PasswordField from "../../components/admin/PasswordField";
import {
  Form,
  FormFeedback,
  SubmitButton,
  SuccessMessage,
} from "../../components/forms/Form";
import { FormValidationError, issueFields, useForm } from "../../forms/useForm";
import { resetSchema } from "../../validation/auth";
export default function ResetPasswordPage() {
  const { refresh } = useAuth();
  const [params] = useSearchParams();
  const token = params.get("token") || "";
  const form = useForm();
  async function submit(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const values = Object.fromEntries(new FormData(event.currentTarget));
    await form.submit(
      async () => {
        const parsed = resetSchema.safeParse(values);
        if (!parsed.success)
          throw new FormValidationError(issueFields(parsed.error.issues));
        const result = await authAction("reset-password", {
          token,
          ...parsed.data,
        });
        await refresh();
        return result;
      },
      (r) => r.message,
    );
  }
  return (
    <AuthCard
      title="Set a new password"
      description="Use a unique password between 12 and 72 bytes."
    >
      {form.message ? (
        <SuccessMessage>{form.message}</SuccessMessage>
      ) : !/^[a-f0-9]{64}$/.test(token) ? (
        <p role="alert" className="text-sm text-red-600">
          This reset link is invalid. Request a new link.
        </p>
      ) : (
        <Form state={form} onSubmit={submit} className="space-y-5">
          {[
            { name: "password", label: "New password" },
            { name: "password_confirmation", label: "Confirm password" },
          ].map((field) => (
            <PasswordField
              key={field.name}
              {...field}
              required
              error={form.errors[field.name]}
              onChange={() => form.clear(field.name)}
            />
          ))}
          <FormFeedback state={form} />
          <SubmitButton busy={form.busy} pendingLabel="Saving…">
            Reset password
          </SubmitButton>
        </Form>
      )}
      <Link
        to="/site-admin/login"
        className="mt-6 block text-center text-xs underline"
      >
        Back to sign in
      </Link>
      <Link
        to="/site-admin/forgot-password"
        className="mt-3 block text-center text-xs underline"
      >
        Request another reset link
      </Link>
    </AuthCard>
  );
}
