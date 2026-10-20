import TwoFactorSettings from "../../components/admin/TwoFactorSettings";
import { authAction } from "../../api/auth";
import { useAuth } from "../../auth/context";
import PasswordField from "../../components/admin/PasswordField";
import {
  FieldError,
  Form,
  FormFeedback,
  SubmitButton,
} from "../../components/forms/Form";
import { FormValidationError, issueFields, useForm } from "../../forms/useForm";
import { profileSchema, passwordSchema } from "../../validation/auth";
function ProfileForm({ password = false }: { password?: boolean }) {
  const { user, setUser } = useAuth();
  const form = useForm();
  async function save(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const element = event.currentTarget;
    const values = Object.fromEntries(new FormData(element));
    await form.submit(
      async () => {
        const parsed = (password ? passwordSchema : profileSchema).safeParse(
          values,
        );
        if (!parsed.success)
          throw new FormValidationError(issueFields(parsed.error.issues));
        const result = await authAction(
          password ? "password" : "profile",
          parsed.data,
          password ? "POST" : "PATCH",
        );
        if (result.user) setUser(result.user);
        if (password) element.reset();
        return result;
      },
      (r) => r.message,
    );
  }
  return (
    <Form
      state={form}
      onSubmit={save}
      className="space-y-5 rounded-xl border border-gray-200 bg-white p-6"
    >
      <h2 className="font-semibold">
        {password ? "Change password" : "Profile details"}
      </h2>
      {password ? (
        [
          {
            name: "current_password",
            label: "Current password",
            autoComplete: "current-password",
          },
          {
            name: "password",
            label: "New password",
            autoComplete: "new-password",
          },
          {
            name: "password_confirmation",
            label: "Confirm new password",
            autoComplete: "new-password",
          },
        ].map((field) => (
          <PasswordField
            key={field.name}
            {...field}
            required
            error={form.errors[field.name]}
            onChange={() => form.clear(field.name)}
          />
        ))
      ) : (
        <>
          <label className="block text-sm">
            Name
            <input
              name="name"
              required
              maxLength={100}
              autoComplete="name"
              defaultValue={user?.name}
              {...form.attributes("name")}
              className="auth-input"
            />
            <FieldError state={form} name="name" />
          </label>
          <label className="block text-sm">
            Email
            <input
              type="email"
              value={user?.email ?? ""}
              readOnly
              className="auth-input bg-gray-50 text-gray-500"
            />
            <span className="mt-2 block text-xs text-gray-400">
              Email addresses cannot be changed.
            </span>
          </label>
          <p className="text-xs text-gray-500">Role: {user?.role}</p>
        </>
      )}
      <FormFeedback state={form} />
      <SubmitButton busy={form.busy}>
        {password ? "Change password" : "Save profile"}
      </SubmitButton>
    </Form>
  );
}
export default function AdminProfile() {
  return (
    <div className="mt-8 max-w-xl space-y-6">
      <ProfileForm />
      <ProfileForm password />
      <TwoFactorSettings />
    </div>
  );
}
